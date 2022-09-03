<?php
if (!defined('ABSPATH')) {
	exit;
}

Class CRON_Importer extends RT_Import {

	private $_cron_schedule = '';

	private $_cron_row_count = '';

    private $_parent_obj = '';

	public function __construct() {
        $this->_parent_obj = new RT_Importer;
		$this->_cron_schedule = $this->_parent_obj->rt_get_settings('rt_cron_job_schedule');
		add_filter( 'cron_schedules', array($this, 'rt_add_cron_interval') );
		add_action( 'wp', array($this, 'rt_run_cron_job') );
		add_action( 'admin_init', array($this, 'rt_run_cron_job') );
		
		add_action( 'rt_action_product_cron_job', array($this, 'rt_run_product_cron_job') );
	}

	// Schedule Cron Job Event
	public function rt_run_cron_job() {
		
        $get_language = $this->_parent_obj->rt_get_settings('rt_language');
        $map_by = $this->_parent_obj->rt_get_settings('rt_mapping_mod');
        $get_language = ($get_language) ? $get_language : 'en';
        $map_by = ($map_by) ? $map_by : 'by_category';
        $mapbycategory_key      = 'rt_mapbycategory_'.$get_language.'_data';
        $mapbycategory_data     = get_option($mapbycategory_key);
        $mapbysubgroup_key      = 'rt_mapbysubgroup_'.$get_language.'_data';
        $mapbysubgroup_data     = get_option($mapbysubgroup_key);

		//wp_clear_scheduled_hook( 'rt_action_product_cron_job' );
		if ( ! wp_next_scheduled( 'rt_action_product_cron_job' ) &&  !empty($mapbycategory_data) || ! wp_next_scheduled( 'rt_action_product_cron_job' ) &&  !empty($mapbysubgroup_data)) {
            
            update_option('rt_cron_status', 'running');
			wp_schedule_event( time(), 'custom_time_cron', 'rt_action_product_cron_job' );
		}
	}

	public function rt_add_cron_interval( $schedules ) {

	    $schedules['custom_time_cron'] = array(
	            'interval'  => ($this->_cron_schedule) ? $this->_cron_schedule : 3600, // time in seconds
	            'display'   => 'Import By Setting'
	    );
	    return $schedules;
	}

	public function rt_run_product_cron_job() {
        
		$result = $this->_parent_obj->download_file();

		if ($result) {
			
			$get_language = $this->_parent_obj->rt_get_settings('rt_language');
			$map_by = $this->_parent_obj->rt_get_settings('rt_mapping_mod');
			$get_language = ($get_language) ? $get_language : 'en';
			$map_by = ($map_by) ? $map_by : 'by_category';
			$mapbycategory_key 		= 'rt_mapbycategory_'.$get_language.'_data';
			$mapbycategory_data 	= get_option($mapbycategory_key);
			$mapbysubgroup_key 		= 'rt_mapbysubgroup_'.$get_language.'_data';
			$mapbysubgroup_data 	= get_option($mapbysubgroup_key);
			if ($map_by == 'by_category' && empty($mapbycategory_data) || $map_by == 'by_subgroup' && empty($mapbysubgroup_data)) {
				return;
			}

	        if ($this->_get_page == 1) {
	            update_option('_imported_products', array());
	            update_option('_skiped_products', array());
	            update_option('rt_cron_status', 'running');
	        }
            
	        while (($row = fgetcsv($this->getFile(), 500000, ';')) !== FALSE) {

	            $this->_csvData[] = array_combine($this->_csvHeader, $row);
	        }
	        fclose($this->getFile());

            $this->_csvData = apply_filters('rt_import_csv_data', $this->_csvData);

	        $this->_cron_row_count = count($this->_csvData);

	        return $this->rt_import_auto_products();	
		}
    }

	public function rt_import_auto_products() {
        
		if(!empty($this->_csvData) && count($this->_csvData)) {
			$i = 0;
            for($index = $this->getPageStartIndex(); $index <= $this->getPageEndIndex(); $index++) {
            	
            	if ($index == $i) {
                	$this->_get_page = (int) ($this->_get_page + 1);
                }
                if(isset($this->_csvData[$index]) && is_array($this->_csvData[$index])) {
                    
                    $this->rt_create_product($this->_csvData[$index]);
                }
                $i++;
            }
        }
        
        if (!empty($this->_imported_products)) {
            $get_imported_products = get_option('_imported_products');
            $importerd_product = array_merge($this->_imported_products, $get_imported_products);
            update_option('_imported_products', $importerd_product);
        }
        if (!empty($this->_skipProducts)) {
            $get_skiped_products = get_option('_skiped_products');
            $skiped_products = array_merge($this->_skipProducts, $get_skiped_products);
            update_option('_skiped_products', $skiped_products);
        }
        if (get_option('rt_cron_status') == 'running') {
        	$this->rt_import_auto_products();
        }
	}

	public function rt_create_product($_data = array()) {

        $this->_csvDataParent = '';
        
        extract($_data);
        
        $owerwrite_product  = $this->_parent_obj->rt_get_settings('rt_owerwrite_product');
        $update_price       = $this->_parent_obj->rt_get_settings('rt_owerwrite_price');
        $update_quantity    = $this->_parent_obj->rt_get_settings('rt_owerwrite_quantity');
        $update_brand       = $this->_parent_obj->rt_get_settings('rt_owerwrite_brand');
        $owerwrite_stock    = $this->_parent_obj->rt_get_settings('rt_owerwrite_stock');

        if ($this->_current_lan == 'nl') {
            $name = $naam;
            $size = $maat;
            $colour = $kleur;
            $extended_description = $uitgebreide_beschrijving;
            $description = $beschrijving;
            $recommended_selling_price = $adviesprijs;
            $carton_quantity = $dooseenheid;
        }

        $sku = (string) preg_replace('/[^A-Za-z0-9]/', '', $sku);
        $objProduct   = $this->get_product_object( $_data );

        if ($type == 'variation') {
            $name = $name . ' ' . $size . ' ' . $colour; 
        }
        $this->_csvDataName = ($_data['id']) ? $_data['id'] : $name;
        
        $cat_ids = $this->rt_set_category($_data);
        
        $_status = $this->hasNextPage() ? 'running' : 'completed';
        update_option('rt_cron_status', $_status);

        if (empty($cat_ids)) {

            $this->_skipProducts[] = 'Category Not Assign: ' . $name .' SKU '. $sku;
            return;
        }
        $product_id = wc_get_product_id_by_sku( $sku );

        // Get product ID from SKU if created during the importation.
        if ( $product_id ) {
            $objProduct = wc_get_product( $product_id );
        }

        if (trim(strtolower($product_is_not_coming_back_in_stock)) == 'y') {
            $this->_skipProducts[] = 'Product is not coming back in stock: ' . $name .' SKU '. $sku;
            return; 
        }

        $parent_product_id = $this->rt_get_parent_product_id($_data);
        
        $this->_csvDataParent = $_data;
        
        if ($parent_product_id) {
            $objProduct->set_parent_id($parent_product_id);
        }

        if ($owerwrite_product == 'yes' && $product_id) {
            $objProduct->set_name($name); //Set product name.
        }

        if ( !$product_id ) {
            
            $objProduct->set_name($name); //Set product name.
            $objProduct->set_status('publish'); //Set product status.
            //$objProduct->set_featured(TRUE); //Set if the product is featured.                          | bool
            $objProduct->set_catalog_visibility('visible'); //Set catalog visibility.                   | string $visibility Options: 'hidden', 'visible', 'search' and 'catalog'.
            
            $objProduct->set_description($extended_description); //Set product description.
            $objProduct->set_short_description($description); //Set product short description.

            $objProduct->set_manage_stock(TRUE);
            $objProduct->set_stock_status('instock'); //Set stock status.                               | string $status 'instock', 'outofstock' and 'onbackorder'
            $objProduct->set_backorders('no');
            if (!empty($cat_ids)) {
                $objProduct->set_category_ids($cat_ids);
            }

            /*
            * Set Product image and gallery images
            */
            /*$arr_image_ids = $this->rt_get_image_ids($_data);
            if (!empty($arr_image_ids)) {
                
                $objProduct->set_image_id($arr_image_ids[0]); // set the first image as primary image of the product

                //in case we have more than 1 image, then add them to product gallery. 
                if(count($arr_image_ids) > 1){
                    unset($arr_image_ids[0]);
                    $objProduct->set_gallery_image_ids($arr_image_ids);
                }
            }*/
        }

        if ( !$product_id ) {
            $objProduct->set_sku($sku);
        }
        
        if ($update_price == 'yes' || !$product_id) {
            
            // Set price to product
            $recommended_selling_price = str_replace(',', '.', trim($recommended_selling_price));
            $objProduct->set_price($recommended_selling_price);
            $objProduct->set_regular_price($recommended_selling_price);
        }

        if ($update_quantity == 'yes' || !$product_id || $owerwrite_stock == 'yes' && $product_id) {
            update_option('test_stock_'.$product_id, $product_id);
            // Set Quantity
            if (trim(strtolower($on_stock)) == 'n') {
                
                //Set number of items available for sale.
                if (get_option('woocommerce_manage_stock') === 'yes') {
                    $objProduct->set_stock_quantity(0);   
                }
                $objProduct->set_stock_status('outofstock');
            } else {
                $objProduct->set_stock_quantity(500);   
            }
        }

        if(trim(strtolower($product_is_not_coming_back_in_stock)) == 'y' && $product_id) {
            //Set number of items available for sale.
            if (get_option('woocommerce_manage_stock') === 'yes') {
                $objProduct->set_stock_quantity(0);   
            }
            $objProduct->set_stock_status('outofstock');
        }

        $objProduct->set_reviews_allowed(TRUE);
        if ($type != 'variation') {
            $objProduct->set_sold_individually(true);
        } else {
            $objProduct->set_sold_individually(false);
        }
        
        $product_attr = $this->rt_prepare_attributes($_data);
        
        if ($update_brand == 'yes' || !$product_id) {
            if ( 'variation' === $type ) {
                $this->set_variation_data( $objProduct, $product_attr, $_data);
            } else {
                $this->set_product_data($objProduct, $product_attr);
                $this->_imported_products[] = $name .': SKU - '. $sku;
            }
        }
        
        $objProduct = apply_filters('rt_product_data_obj', $objProduct, $_data);

        $objProduct->save();
        
        if ($objProduct->get_id()) {
            update_post_meta($objProduct->get_id(), 'rt_ean_code', $ean);
        }
        if ($objProduct->get_type() == 'variable') {
            update_post_meta($objProduct->get_id(), 'rt_parent_id', $id);
        }
    }
}

new CRON_Importer;
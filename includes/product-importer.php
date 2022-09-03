<?php
if (!defined('ABSPATH')) {
	exit;
}

class RT_Import {

    protected $_file        = null;

    protected $_csvHeader   = array();

    protected $_csvData     = array();

    protected $_pageSize    = 10;

    protected $_count       = 1;

    protected $_get_page    = 1;

    protected $_csvDataName     = '';

    protected $_csvDataParent   = '';

    protected $_csvError        = '';

    protected $_skipProducts    = array();

    protected $_imported_products    = array();

    private $_current_lan = '';

	public function __construct() {

		add_action('wp_ajax_rt_import_woo_product', array($this, 'rt_import_product'));
		add_action('wp_ajax_nopriv_rt_import_woo_product', array($this, 'rt_import_product'));

        $this->_current_lan = RT_Importer::rt_get_settings('rt_language');
	}

	public function rt_import_product() {

		$nonce = $_POST['nonce'];

    	if ( ! wp_verify_nonce( $nonce, 'rt-ajax-nonce' ) )
        	die ( '_Nonce not verify');

    	if (isset($_POST['page_num'])) {
            $this->_get_page = $_POST['page_num'];
        }
        if ($this->_get_page == 1) {
            update_option('_imported_products', array());
            update_option('_skiped_products', array());
        }
        
        while (($row = fgetcsv($this->getFile(), 500000, ';')) !== FALSE) {

            $this->_csvData[] = array_combine($this->_csvHeader, $row);
        }
        fclose($this->getFile());

        $this->_csvData = apply_filters('rt_import_csv_data', $this->_csvData);

        return $this->importAjax();
    }

    protected function importAjax() {

        if(!empty($this->_csvData) && count($this->_csvData)) {

            for($index = $this->getPageStartIndex(); $index <= $this->getPageEndIndex(); $index++) {

                if(isset($this->_csvData[$index]) && is_array($this->_csvData[$index])) {
                    $this->rt_create_product($this->_csvData[$index]);
                }
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
        
        echo json_encode($this->getAjaxResponse());

        die();
    }

    public function rt_create_product($_data = array()) {

        $this->_csvDataParent = '';
        
        extract($_data);

        $owerwrite_product  = RT_Importer::rt_get_settings('rt_owerwrite_product');
        $update_price       = RT_Importer::rt_get_settings('rt_owerwrite_price');
        $update_quantity    = RT_Importer::rt_get_settings('rt_owerwrite_quantity');
        $update_brand       = RT_Importer::rt_get_settings('rt_owerwrite_brand');
        $owerwrite_stock       = RT_Importer::rt_get_settings('rt_owerwrite_stock');

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
        if (empty($cat_ids)) {
            $this->_skipProducts[] = 'Category Not Assign: ' . $name .' SKU '. $sku;
            return;
        }
        $product_id = wc_get_product_id_by_sku( $sku );

        // Get product ID from SKU if created during the importation.
        if ( $product_id ) {
            $objProduct = wc_get_product( $product_id );
        }

        if (trim(strtolower($product_is_not_coming_back_in_stock)) === 'y') {
            
            if ( $product_id ) {
                //Set number of items available for sale.
                $objProduct->set_stock_quantity(0);
                $objProduct->set_stock_status('outofstock');
            } else {
                $this->_skipProducts[] = 'Product is not coming back in stock: ' . $name .' SKU '. $sku;
                return; 
            }
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
            $recommended_selling_price = str_replace(',', '.', trim($recommended_selling_price));//preg_replace('/[^A-Za-z0-9]/', '', $recommended_selling_price);
            $objProduct->set_price($recommended_selling_price);
            $objProduct->set_regular_price($recommended_selling_price);
        }

        if ($update_quantity == 'yes' || !$product_id || trim(strtolower($product_is_not_coming_back_in_stock)) === 'n' && $product_id || $owerwrite_stock == 'yes' && $product_id) {
            
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

    public function rt_get_parent_product_id($data) {
        
        if (!empty($data['parent'])) {
            
            $args = array(
                'post_type'     =>  'product',
                'meta_query'    =>  array(
                    array(
                        'value' =>  $data['parent'],
                        'key' =>  'rt_parent_id',
                    )
                )
            );
            $get_products = get_posts($args);
            if (isset($get_products[0]) && !empty($get_products[0])) {
                return $get_products[0]->ID;
            }
        }
    }

    /**
     * Prepare a single product for create or update.
     *
     * @param  array $data     Item data.
     * @return WC_Product|WP_Error
     */
    protected function get_product_object( $data ) {

        $id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        
        // Type is the most important part here because we need to be using the correct class and methods.
        if ( isset( $data['type'] ) ) {
            $types   = array_keys( wc_get_product_types() );
            $types[] = 'variation';

            if ( ! in_array( $data['type'], $types, true ) ) {
                return $this->_csvError = __( 'Invalid product type.', 'woocommerce' );
            }

            $classname = WC_Product_Factory::get_classname_from_product_type( $data['type'] );

            if ( ! class_exists( $classname ) ) {
                $classname = 'WC_Product_Simple';
            }
            
            $product = new $classname;
            
        } elseif ( ! empty( $data['id'] ) ) {
            $product = wc_get_product( $id );

            if ( ! $product ) {
                
                return $this->_csvError = sprintf( __( 'Invalid product ID %d.', 'woocommerce' ), $id );
            }
        } else {
            $product = new WC_Product_Simple;
        }
        
        return apply_filters( 'rt_product_import_get_product_object', $product, $data );
    }

    /**
     * Set variation data.
     *
     * @param WC_Product $variation Product instance.
     * @param array      $data    Item data.
     * @return WC_Product|WP_Error
     * @throws Exception If data cannot be set.
     */
    protected function set_variation_data( &$variation, $product_attr, $data ) {
        $parent = false;

        // Check if parent exist.
        if ( isset( $data['parent'] ) && $data['parent'] != '') {
            $parent_id = $this->rt_get_parent_product_id($data);
            $parent = wc_get_product( $parent_id );
            
            if ( $parent ) {
                $variation->set_parent_id( $parent->get_id() );
            }
        }

        // Stop if parent does not exists.
        if ( ! $parent ) {
            $this->_csvError = __( 'Variation cannot be imported: Missing parent ID or parent does not exist yet.', 'woocommerce' );
            return;
        }

        // Stop if parent is a product variation.
        if ( $parent->is_type( 'variation' ) ) {
            $this->_csvError = __( 'Variation cannot be imported: Parent product cannot be a product variation', 'woocommerce' );
            return;
        }

        if ( !empty($product_attr) ) {
            $attributes        = array();
            $parent_attributes = $this->get_variation_parent_attributes( $product_attr, $parent );
            
            foreach ( $product_attr as $attribute ) {
                $attribute_id = 0;

                // Get ID if is a global attribute.
                if ( ! empty( $attribute['taxonomy'] ) ) {
                    $attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
                }

                if ( $attribute_id ) {
                    $attribute_name = wc_attribute_taxonomy_name_by_id( $attribute_id );
                } else {
                    $attribute_name = sanitize_title( $attribute['name'] );
                }

                if ( ! isset( $parent_attributes[ $attribute_name ] ) || ! $parent_attributes[ $attribute_name ]->get_variation() ) {
                    continue;
                }

                $attribute_key   = sanitize_title( $parent_attributes[ $attribute_name ]->get_name() );
                $attribute_value = isset( $attribute['value'] ) ? current( $attribute['value'] ) : '';

                if ( $parent_attributes[ $attribute_name ]->is_taxonomy() ) {
                    // If dealing with a taxonomy, we need to get the slug from the name posted to the API.
                    $term = get_term_by( 'name', $attribute_value, $attribute_name );

                    if ( $term && ! is_wp_error( $term ) ) {
                        $attribute_value = $term->slug;
                    } else {
                        $attribute_value = sanitize_title( $attribute_value );
                    }
                }

                $attributes[ $attribute_key ] = $attribute_value;
            }

            $attributes = apply_filters('rt_attributes_data', $attributes);

            $variation->set_attributes( $attributes );
        }
    }

    /**
     * Get variation parent attributes and set "is_variation".
     *
     * @param  array      $attributes Attributes list.
     * @param  WC_Product $parent     Parent product data.
     * @return array
     */
    protected function get_variation_parent_attributes( $attributes, $parent ) {
        $parent_attributes = $parent->get_attributes();
        $require_save      = false;

        foreach ( $attributes as $attribute ) {
            $attribute_id = 0;

            // Get ID if is a global attribute.
            if ( ! empty( $attribute['taxonomy'] ) ) {
                $attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
            }
            
            if ( $attribute_id ) {
                $attribute_name = wc_attribute_taxonomy_name_by_id( $attribute_id );
            } else {
                $attribute_name = sanitize_title( $attribute['name'] );
            }
            
            if ($this->_current_lan == 'nl') {

                // Check if attribute handle variations.
                if ( isset( $parent_attributes[ $attribute_name ] ) && ! $parent_attributes[ $attribute_name ]->get_variation() && $attribute_name == 'pa_kleur' || isset( $parent_attributes[ $attribute_name ] ) && ! $parent_attributes[ $attribute_name ]->get_variation() && $attribute_name == 'pa_maat') {
                    // Re-create the attribute to CRUD save and generate again.
                    $parent_attributes[ $attribute_name ] = clone $parent_attributes[ $attribute_name ];
                    $parent_attributes[ $attribute_name ]->set_variation( 1 );

                    $require_save = true;
                }

            } else {

                // Check if attribute handle variations.
                if ( isset( $parent_attributes[ $attribute_name ] ) && ! $parent_attributes[ $attribute_name ]->get_variation() && $attribute_name == 'pa_colour' || isset( $parent_attributes[ $attribute_name ] ) && ! $parent_attributes[ $attribute_name ]->get_variation() && $attribute_name == 'pa_size') {
                    // Re-create the attribute to CRUD save and generate again.
                    $parent_attributes[ $attribute_name ] = clone $parent_attributes[ $attribute_name ];
                    $parent_attributes[ $attribute_name ]->set_variation( 1 );

                    $require_save = true;
                }
            }
        }

        // Save variation attributes.
        if ( $require_save ) {
            $parent->set_attributes( array_values( $parent_attributes ) );
            $parent->save();
        }

        return $parent_attributes;
    }

    /**
     * Set product data.
     *
     * @param WC_Product $product Product instance.
     * @param array      $data    Item data.
     * @throws Exception If data cannot be set.
     */
    protected function set_product_data( &$product, $data ) {
        
        if ( !empty($data) ) {

            $data = apply_filters('rt_product_set_data', $data);

            $attributes          = array();
            $default_attributes  = array();
            $existing_attributes = $product->get_attributes();
            
            foreach ( $data as $position => $attribute ) {
                $attribute_id = 0;
                
                // Get ID if is a global attribute.
                if ( ! empty( $attribute['taxonomy'] ) ) {
                    $attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
                }

                // Set attribute visibility.
                if ( isset( $attribute['visible'] ) ) {
                    $is_visible = $attribute['visible'];
                } else {
                    $is_visible = 1;
                }

                // Get name.
                $attribute_name = $attribute_id ? wc_attribute_taxonomy_name_by_id( $attribute_id ) : $attribute['name'];

                // Set if is a variation attribute based on existing attributes if possible so updates via CSV do not change this.
                $is_variation = 0;

                if ( $existing_attributes ) {
                    foreach ( $existing_attributes as $existing_attribute ) {
                        if ( $existing_attribute->get_name() === $attribute_name ) {
                            $is_variation = $existing_attribute->get_variation();
                            break;
                        }
                    }
                }

                if ( $attribute_id ) {

                    if ( isset( $attribute['value'] ) ) {
                        $options = array_map( 'wc_sanitize_term_text_based', $attribute['value'] );
                        $options = array_filter( $options, 'strlen' );
                    } else {
                        $options = array();
                    }

                    // Check for default attributes and set "is_variation".
                    if ( ! empty( $attribute['default'] ) && in_array( $attribute['default'], $options, true ) ) {
                        $default_term = get_term_by( 'name', $attribute['default'], $attribute_name );

                        if ( $default_term && ! is_wp_error( $default_term ) ) {
                            $default = $default_term->slug;
                        } else {
                            $default = sanitize_title( $attribute['default'] );
                        }

                        $default_attributes[ $attribute_name ] = $default;
                        $is_variation                          = 1;
                    }
                   
                    if ( ! empty( $options ) ) {
                        $attribute_object = new WC_Product_Attribute();
                        $attribute_object->set_id( $attribute_id );
                        $attribute_object->set_name( $attribute_name );
                        $attribute_object->set_options( $options );
                        $attribute_object->set_position( $position );
                        $attribute_object->set_visible( $is_visible );
                        $attribute_object->set_variation( $is_variation );
                        $attributes[] = $attribute_object;
                    }

                } elseif ( isset( $attribute['value'] ) ) {
                    // Check for default attributes and set "is_variation".
                    if ( ! empty( $attribute['default'] ) && in_array( $attribute['default'], $attribute['value'], true ) ) {
                        $default_attributes[ sanitize_title( $attribute['name'] ) ] = $attribute['default'];
                        $is_variation = 1;
                    }

                    $attribute_object = new WC_Product_Attribute();
                    $attribute_object->set_name( $attribute['name'] );
                    $attribute_object->set_options( $attribute['value'] );
                    $attribute_object->set_position( $position );
                    $attribute_object->set_visible( $is_visible );
                    $attribute_object->set_variation( $is_variation );
                    $attributes[] = $attribute_object;
                }
            }

            $product->set_attributes( $attributes );

            // Set variable default attributes.
            if ( $product->is_type( 'variable' ) ) {
                $product->set_default_attributes( $default_attributes );
            }
        }
    }

    public function rt_prepare_attributes($data) {

        if ($this->_current_lan == 'nl') {
            $_csv_attr = array('maat' => 'Maat', 'kleur' => 'Kleur', 'merknaam' => 'Merknaam', 'ean' => 'Ean', 'materiaal' => 'Materiaal');
        } else {
            $_csv_attr = array('size' => 'Size', 'colour' => 'Colour', 'brandname' => 'Brand', 'ean' => 'Ean', 'material' => 'Material');
        }
        $_attr = array();
        $i = 0;
        $get_all_attr = get_option('arr_attribute_collection_by_parent');
        
        foreach ($data as $key => $_data) {
            if (isset($_csv_attr[$key])) {
                $_attr[$i]['name'] = sanitize_title($_csv_attr[$key]);
                $attr_values = explode(',', $_data);
                if ($data['type'] == 'variable' && isset($get_all_attr[$data['id']][$key])) {
                    $attr_values = $get_all_attr[$data['id']][$key]; 
                }
                $_attr[$i]['value'] = array_map('trim', $attr_values);
                $_attr[$i]['visible'] = 1;
                $_attr[$i]['taxonomy'] = 1;
                
                if ($this->_current_lan == 'nl') {

                    if ($data['type'] == 'variable' && $key == 'maat' || $data['type'] == 'variable' && $key == 'kleur') {
                        $_attr[$i]['default'] = 1; //$_data;
                    }

                } else {
                    if ($data['type'] == 'variable' && $key == 'size' || $data['type'] == 'variable' && $key == 'colour') {
                        $_attr[$i]['default'] = 1; //$_data;
                    }
                }
                $i++;
            }
        }

        $_attr = apply_filters('rt_prepare_attributes', $_attr);

        return $_attr;
    }

    /**
     * Get attribute taxonomy ID from the imported data.
     * If does not exists register a new attribute.
     *
     * @param  string $raw_name Attribute name.
     * @return int
     * @throws Exception If taxonomy cannot be loaded.
     */
    public function get_attribute_taxonomy_id( $raw_name ) {
        global $wpdb, $wc_product_attributes;

        // These are exported as labels, so convert the label to a name if possible first.
        $attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
        $attribute_name   = array_search( $raw_name, $attribute_labels, true );

        if ( ! $attribute_name ) {
            $attribute_name = wc_sanitize_taxonomy_name( $raw_name );
        }

        $attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

        // Get the ID from the name.
        if ( $attribute_id ) {
            return $attribute_id;
        }

        // If the attribute does not exist, create it.
        $attribute_id = wc_create_attribute(
            array(
                'name'         => $raw_name,
                'slug'         => $attribute_name,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => false,
            )
        );

        if ( is_wp_error( $attribute_id ) ) {
            return $this->_csvError = $attribute_id->get_error_message();
        }

        // Register as taxonomy while importing.
        $taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
        register_taxonomy(
            $taxonomy_name,
            apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
            apply_filters(
                'woocommerce_taxonomy_args_' . $taxonomy_name,
                array(
                    'labels'       => array(
                        'name' => $raw_name,
                    ),
                    'hierarchical' => true,
                    'show_ui'      => false,
                    'query_var'    => true,
                    'rewrite'      => false,
                )
            )
        );

        // Set product attributes global.
        $wc_product_attributes = array();

        foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
            $wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
        }

        return $attribute_id;
    }

    public function rt_get_image_ids($_data) {

        $arr_images_id = array();
        foreach ($_data as $pkey => $pdata) {
            
            if (strpos($pkey, 'web_img_large') !== false) {
                $arr_images_id[] = $this->setFeaturedImage($pdata);
            }
        }
        return array_filter($arr_images_id);
    }

    public function setFeaturedImage($image_url) {
        $upload_dir = wp_upload_dir();
        $attach_id = '';
        
        $ext            = pathinfo($image_url, PATHINFO_EXTENSION);
        $parts          = explode('/', $image_url);
        $filename       = $this->rt_basename($image_url);
        $at_url         = $upload_dir['path'] . '/' . $filename;
        $title          = str_replace('.'.$ext, '', $filename);
        
        if ( !file_exists($at_url) ) {
            
            if(wp_mkdir_p($upload_dir['path'])){
                $file = $upload_dir['path'] . '/' . $filename;                  
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;
            }
            
            if (copy($image_url, $file)) {
                $wp_filetype = wp_check_filetype($filename, null );
                $attachment = array(
                    'guid'           => $file,
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_title'    => $title,
                );
                $attach_id = wp_insert_attachment( $attachment, $file);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                return $attach_id;
            } else {
                return;
            }

        }else{

            $attach_id1 = $this->getImageId($upload_dir['subdir'] . '/' . $filename);
            return $attach_id1;
        }
    }

    public function rt_basename($path) {
        if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        } else if (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        }
        return '';
    }

    public function getImageId($attachment_url) { 
        //get attechment id by image url 
        global $wpdb;
        $attachment_url = ltrim($attachment_url,'/');
        $query = "SELECT post_id FROM $wpdb->postmeta WHERE `meta_key` LIKE '_wp_attached_file' AND `meta_value` LIKE '".$attachment_url."'";
        $attachment_id = $wpdb->get_var($query); 
        return $attachment_id; 
    }

    public function rt_set_category($_data) {

        extract($_data);

        $get_language = RT_Importer::rt_get_settings('rt_language');
        $category_key = 'rt_mapbycategory_'.$get_language.'_data';
        $subgroup_key = 'rt_mapbysubgroup_'.$get_language.'_data';
        $_mapping_mod = (RT_Importer::rt_get_settings('rt_mapping_mod')) ? RT_Importer::rt_get_settings('rt_mapping_mod') : 'by_category';

        if ($_mapping_mod == 'by_category') {

            $map_category = get_option($category_key);
            if ($this->_current_lan == 'nl') {
                $get_cat =  sanitize_title(trim($categorie_1));
            } else {
                $get_cat = sanitize_title(trim($category));
            }
            
            if (!empty($map_category[$get_cat])) {
                return $map_category[$get_cat];
            }

        } else {

            $map_subgroup = get_option($subgroup_key);
            if ($this->_current_lan == 'nl') {
                $get_sg = sanitize_title(trim($subgroep));
            } else {
                $get_sg = sanitize_title(trim($subgroup)); 
            }
            
            if (!empty($map_subgroup[$get_sg])) {
                return $map_subgroup[$get_sg];
            }
        }
    }

    public function get_product_type($data) {

        $arr_names = $this->get_all_name_counts();
        $final_name = RT_Importer::get_plain_product_name($data);

        if(isset($arr_names[$final_name]) && $arr_names[$final_name] > 1) {
            return 'variable';
        } else if(isset($arr_names[$final_name]) && $arr_names[$final_name] == 1) {
            return 'simple';
        }
    }

    public function get_all_name_counts() {

        $get_language = RT_Importer::rt_get_settings('rt_language');
        $count_variation_key    = 'count_variation_'.$get_language;
        $count_variation_data   = get_option($count_variation_key);

        if (empty($count_variation_data)) {
            $count_variation_data = array();
        }
        return $count_variation_data;
    }

    public function getFile(){
        //$obj = new RT_Importer
        if($this->_file == null) {
            
            if(!file_exists($this->getCsvPath())) throw new \Exception(__('File does not exist.'));

            $this->_file = fopen($this->getCsvPath(), 'r');

            $this->_csvHeader = array_map(array('RT_Importer', 'rt_sanitize_header'),fgetcsv($this->_file, 500000, ';'));
        }

        return  $this->_file;
    }

    public function getCsvPath() {

        $file_full_path = RT_ABSPATH . 'csv/assortiment-new.csv';
        return $file_full_path;
    }

    public function getPage() {

        return (int) $this->_get_page;
    }

    public function getAjaxResponse() {

        $_rercent = round((($this->getPageEndIndex() + 1) * 100) / count($this->_csvData),2);

        return [
            'page' 			=> $this->getPage() + 1,
            'status' 		=> $this->hasNextPage() ? 'running' : 'completed',
            'total_row'		=> count($this->_csvData),
            'total'         => count(get_option('_imported_products')),
            'imported' 		=> ($this->getPageEndIndex() + 1),
            'percent' 		=> $_rercent,
            'error'			=> $this->_csvError,
            'name'          => $this->_csvDataName,
            //'parent'        => $this->_csvDataParent,
            'skip_products' => count(get_option('_skiped_products')),
        ];
    }

    public function hasNextPage() {

        return ($this->_csvData && isset($this->_csvData[$this->getPageEndIndex() + 1])) ? true : false;
    }

    public function getNextPage() {

        return $this->hasNextPage() ? ($this->getPageEndIndex() + 1) : false;
    }

    public function getPageStartIndex() {

        return ($this->_pageSize * $this->getPage()) - ($this->_pageSize);
    }

    public function setCount($count) {
	    update_option('tr_import_page_count', $count);
    }

    public function getPageEndIndex() {

        return ($this->_pageSize * $this->getPage()) - 1;
    }
}

new RT_Import;
<?php
if (!defined('ABSPATH')) {
	exit;
}
session_start();

class RT_Importer {

	private $_message;

	private $_csvHeader = array();

	private $_csvData = array();

	private $_csvNewData = array();

	private $_isVariable = array();

	private $_isVariation = array();

	private $_filename = '';

	private $_file = null;

	private $_parent_ids = array();

	private $_current_lan = '';

	public $_enable_sub_menu = false;

	// Variable with the service url
	public $ServiceUrl = "http://www.royaltextile.nl/webshop/webshopapi/OrdersAPIV2.asmx";

	function __construct() {

		add_action( 'plugins_loaded', array( $this, 'rt_load_plugin_textdomain' ) );
		register_activation_hook( RT_PLUGIN_FILE , array($this, 'RT_Active'));
		
		add_action( 'admin_init', array($this, 'save_rt_importer_data'), 20 );
		add_action( 'admin_enqueue_scripts', array($this, 'rt_admin_assets'), 10, 1);
		add_action( 'admin_init', array($this, 'load_plugin_files') );
		add_action( 'init', array($this, 'load_plugin_files') );

		$this->_current_lan = $this->rt_get_settings('rt_language');

		add_action('wp_ajax_rt_save_mapped_data', array($this, 'rt_save_mapped_data'));
		add_action('wp_ajax_nopriv_rt_save_mapped_data', array($this, 'rt_save_mapped_data'));

		add_action('wp_ajax_rt_save_mapped_pagination', array($this, 'rt_save_mapped_pagination'));
		add_action('wp_ajax_nopriv_rt_save_mapped_pagination', array($this, 'rt_save_mapped_pagination'));

		//add_action('woocommerce_new_order', array($this, 'send_order_to_external_platform'), 10, 2);

		if (isset($_GET['page']) && $_GET['page'] != 'rt-mapping-tool') {
			$_SESSION['update_maped_page'] = 1;
		}
	}

	public function load_plugin_files() {

		include_once RT_ABSPATH . 'includes/product-importer.php';
		include_once RT_ABSPATH . 'includes/cron-importer.php';
		include_once RT_ABSPATH . 'includes/cron-image-importer.php';
		include_once RT_ABSPATH . 'includes/class-send-order.php';
	}

	public function send_order_to_external_platform( $order_id, $order) {
	    if ( ! $order_id )
	        return;

	    //$order = wc_get_order( $order_id );
	    $order_items = array();

	    foreach ($order->get_items() as $item_id => $item_data) {
	    	$_product = wc_get_product($item_data->get_product_id());
	    	$_ean = get_post_meta($item_data->get_product_id(), 'rt_ean_code', true);
	    	
	    	// Create two orderlines object
			$OrderLine = new OrderLine;
			$OrderLine->ProductCode = $_ean;
			$OrderLine->OrderQuantity = $item_data->get_quantity();
			$order_items[] = $OrderLine;
		}

        if($order_id){

        	// The create a new soapclient and show the functions and types
			$client = new SoapClient($this->ServiceUrl."?wsdl");

			// Create a new authentication object
			$Authorisation = new Authorisation;
			$Authorisation->LoginCode = "0055334";
			$Authorisation->AuthorisationKey = "19fe3d8adc2148c0a1b254e00f2779331000:kctgVIxHZQaefCVQOhV2wajeU3PF/EGI:KISTd3cniX";

			// Create a new order object
			$rt_order = new Order;
			$rt_order->OrderReference = "Order description";
			$rt_order->OrderComments = ($order->get_customer_note()) ? $order->get_customer_note() : '';

			$rt_order->EmailAddress = ($order->get_billing_email()) ? $order->get_billing_email() : '';
			$rt_order->PhoneNumber = ($order->get_billing_phone()) ? $order->get_billing_phone() : '';
			$rt_order->MobileNumber = ($order->get_billing_phone()) ? $order->get_billing_phone() : '';
			//Not a valid AllXsd value
			$date_created = $order->get_date_created();
			$rt_order->DeliveryDate = $date_created->date('c');

			$dinfo = new DeliveryInformation;
			$dinfo->FirstName = ($order->get_billing_first_name()) ? $order->get_billing_first_name() : '';
			$dinfo->LastName = ($order->get_billing_last_name()) ? $order->get_billing_last_name() : '';
			$dinfo->Street = ($order->get_billing_address_1()) ? $order->get_billing_address_1() : '';
			$dinfo->HouseNr = ($order->get_billing_address_2()) ? $order->get_billing_address_2() : '';
			$dinfo->City = ($order->get_billing_city()) ? $order->get_billing_city() : '';
			$dinfo->PostalCode = ($order->get_billing_postcode()) ? $order->get_billing_postcode() : '';
			$dinfo->CustomerEmail = ($order->get_billing_email()) ? $order->get_billing_email() : '';
			$dinfo->CountryCode = ($order->get_billing_country()) ? $order->get_billing_country() : '';

			$rt_order->DeliveryInformation = $dinfo;

			// Add the orderlines array to the orders object
			$rt_order->OrderLines = $order_items;

			// Create the soap parameters object
			$params = array(
				"Authorisation" => $Authorisation,
				"Order" => $rt_order,
				"DeliveryInformation" => '',
			);
			
			// Invoke webservice method with the parameters
			$response = $client->__soapCall("AddSalesOrder", array($params));
			if ($response) {
				update_post_meta($order_id, 'rt_sent_order_status', '1' );
			} else {
				update_post_meta($order_id, 'rt_sent_order_status', '0' );
			}
        }
	}

	public function rt_admin_assets() {
		
		wp_enqueue_script('rt-script-select2', RT_PLUGIN_URL . 'js/select2.min.js', array( 'jquery' ));
		wp_enqueue_script('rt-script-dataTables', RT_PLUGIN_URL . 'js/fancyTable.min.js', array( 'jquery' ));
		wp_enqueue_script('rt-scripts', RT_PLUGIN_URL . 'js/rt-scripts.js', array( 'jquery' ));
		wp_enqueue_style('rt-style-select2', RT_PLUGIN_URL . 'css/select2.min.css');
		wp_enqueue_style('rt-style', RT_PLUGIN_URL . 'css/style.css');
		$script_data = array(
								'ajax_url'	=> admin_url('admin-ajax.php'),
								'nonce' => wp_create_nonce('rt-ajax-nonce'),
								'mapped_page' => (!empty($_SESSION['update_maped_page']) && isset($_SESSION['update_maped_page'])) ? $_SESSION['update_maped_page'] : 1,
							);
		wp_localize_script('rt-scripts', 'script_data', $script_data);
	}

	public function rt_save_mapped_pagination() {
		
		$nonce = $_POST['nonce'];

    	if ( ! wp_verify_nonce( $nonce, 'rt-ajax-nonce' ) )
        	die ( '_Nonce not verify');

        if (isset($_POST['page'])) {
        	$_SESSION['update_maped_page'] = $_POST['page'];
        }
        echo json_encode(array('msg' => 'Mapping page saved'));
        die();
	}

	public function rt_admin_menus() {

		$page_title = __('Royal Textile', 'rt-importer');
	    $menu_title = __('Royal Textile', 'rt-importer');
		add_menu_page( $page_title, $menu_title,  'manage_options', 'rt-settigns', array($this, 'rt_settings_html'), '',55);
	    add_submenu_page(
	         'rt-settigns',
	         __('Settings', 'lead-rev'),
	         __('Settings', 'rt-importer'),
	         'manage_options',
	         'rt-settigns',
	         array($this, 'rt_settings_html')
		);
	    if (!empty(get_option('rt_plugin_settings')) || $this->_enable_sub_menu) {
	    	add_submenu_page(
		       'rt-settigns',
		       __('Mapping Tool', 'lead-rev'),
			   __('Mapping Tool', 'lead-rev'),
		       'manage_options',
		       'rt-mapping-tool',
		       array($this, 'rt_mapping_tool_html')
		    );
	    }
	}

	public function rt_settings_html() {
		include_once RT_ABSPATH . 'templates/settings-html.php';
	}

	public function rt_mapping_tool_html() {
		$category_structire = $this->rt_get_category_structure('product_cat', 0);
		include_once RT_ABSPATH . 'templates/mapping-tool-html.php';
	}

	public function download_file() {
		
		//Create directory if not exist
		if (!file_exists(RT_CSV_DOWNLOADS)) {
		    wp_mkdir_p(RT_CSV_DOWNLOADS, 0777, true);
		}

		//Destination file path with file name
		$destination = RT_CSV_DOWNLOADS .'/'. RT_DOWNLOADED_FILE_NAME;

		if ( copy(self::rt_get_file_url(), $destination) ) {
		    
		    add_action( 'admin_notices', array( $this, 'rt_update_settings_message' ) );

		    $this->_message = __('File is Successfully Copied.', 'rt-importer');
		    add_action( 'admin_notices', array( $this, 'rt_update_message' ) );
		    $this->rt_read_csv_for_mapping();
		    return 1;

		} else {
		    
		    $this->_message = __('File copying has been failed.', 'rt-importer');
		    add_action( 'admin_notices', array( $this, 'rt_error_message' ) );
		    return 0;
		}
	}

	public function rt_read_csv_for_mapping() {

		while (($row = fgetcsv($this->getFile(), 50000, ';')) !== FALSE) { 
			
			$map_header_value = array_combine($this->_csvHeader, $row);
			$this->_csvData[] = $map_header_value;
			$all_name[] = $this->get_plain_product_name($map_header_value);
		}

		fclose($this->getFile());
		if ($this->_current_lan == 'nl') {
			$categories = array_filter(array_unique(array_map(function ($ar) { return $ar['categorie_1']; }, $this->_csvData)));
			$Subgroup 	= array_filter(array_unique(array_map(function ($ar) { return $ar['subgroep']; }, $this->_csvData)));
		} else {
			$categories = array_filter(array_unique(array_map(function ($ar) { return $ar['category']; }, $this->_csvData)));
			$Subgroup 	= array_filter(array_unique(array_map(function ($ar) { return $ar['subgroup']; }, $this->_csvData)));
		}

		$arr_name_count = array_count_values($all_name);

		$get_language = $this->rt_get_settings('rt_language');
		$category_key = 'mapping_category_'.$get_language;
		$subgroup_key = 'mapping_subgroup_'.$get_language;
		$count_variation_key = 'count_variation_'.$get_language;
		
		update_option($category_key, $categories);
		update_option($subgroup_key, $Subgroup);
		update_option($count_variation_key, $arr_name_count);

		$this->rt_formate_csv();
	}

	public function rt_formate_csv() {
		
		$this->_filename = 'assortiment.csv'; umask(0);
		$arr_attribute_collection = array();
		$arr_names = $this->get_all_name_counts();
		$parent_id = 1000000;
		if (file_exists($this->getNewFilePath())) {
			unlink($this->getNewFilePath());
		}
		$_newFile = fopen($this->getNewFilePath(), 'w+');
		$this->_csvHeader[] = 'id';
		$this->_csvHeader[] = 'parent';
		$this->_csvHeader[] = 'type';
		update_option('arr_attribute_collection_by_parent', array());
		fputcsv($_newFile, $this->_csvHeader, ";");
		
		foreach ($this->_csvData as $key => $arr_old_data) {

			$final_name = $this->get_plain_product_name($arr_old_data);
			$get_product_count = $arr_names[$final_name];
			
			if ($get_product_count == 1) {
				
				$append_data = array();
				$append_data['id'] = $parent_id;
				$append_data['parent'] = '';
				$append_data['type'] = 'simple';
				if ($this->_current_lan == 'nl') {
					$arr_old_data['naam'] = $final_name;
				} else {
					$arr_old_data['name'] = $final_name;
				}

				$this->_csvNewData[] = array_merge($arr_old_data, $append_data);
				fputcsv($_newFile, array_merge($arr_old_data, $append_data), ";");

			} else if ($get_product_count > 1 && !isset($this->_parent_ids[$final_name])) {
				
				$this->_parent_ids[$final_name] = $parent_id;
				$append_data = array();
				$append_data['id'] = $parent_id;
				$append_data['parent'] = '';
				$append_data['type'] = 'variable';
				if ($this->_current_lan == 'nl') {
					$arr_old_data['naam'] = $final_name;
				} else {
					$arr_old_data['name'] = $final_name;
				}
				$this->_csvNewData[] = array_merge($arr_old_data, $append_data);
				fputcsv($_newFile, array_merge($arr_old_data, $append_data), ";");

			} else if ($get_product_count > 1 && isset($this->_parent_ids[$final_name])) {
				
				$append_data = array();
				$append_data['id'] = '';
				$append_data['parent'] = $this->_parent_ids[$final_name];
				$append_data['type'] = 'variation';
				if ($this->_current_lan == 'nl') {
					$arr_old_data['naam'] = $final_name;
				} else {
					$arr_old_data['name'] = $final_name;
				}
				$this->_csvNewData[] = array_merge($arr_old_data, $append_data);
				fputcsv($_newFile, array_merge($arr_old_data, $append_data), ";");

				if ($this->_current_lan == 'nl') {
					$temp_size = array();
					if (isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['maat']) && !empty($arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'])) {

						if (!in_array(trim($arr_old_data['maat']), $arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'])) {
							
							$temp_size = $arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'];

							$arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'] = array_merge($temp_size, (array) trim($arr_old_data['maat']));
						}

					} else if(!isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'])) {

						$arr_attribute_collection[$this->_parent_ids[$final_name]]['maat'] = (array) trim($arr_old_data['maat']);

					}
					$temp_colour = array();
					if (isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur']) && !empty($arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'])) {

						if (!in_array(trim($arr_old_data['kleur']), $arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'])) {
							
							$temp_colour = $arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'];

							$arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'] = array_merge($temp_colour, (array) trim($arr_old_data['kleur']));

						}
						
					} else if(!isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'])) {
						$arr_attribute_collection[$this->_parent_ids[$final_name]]['kleur'] = (array) trim($arr_old_data['kleur']);
					}

				} else {

					$temp_size = array();
					if (isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['size']) && !empty($arr_attribute_collection[$this->_parent_ids[$final_name]]['size'])) {

						if (!in_array(trim($arr_old_data['size']), $arr_attribute_collection[$this->_parent_ids[$final_name]]['size'])) {
							
							$temp_size = $arr_attribute_collection[$this->_parent_ids[$final_name]]['size'];

							$arr_attribute_collection[$this->_parent_ids[$final_name]]['size'] = array_merge($temp_size, (array) trim($arr_old_data['size']));
						}

					} else if(!isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['size'])) {

						$arr_attribute_collection[$this->_parent_ids[$final_name]]['size'] = (array) trim($arr_old_data['size']);

					}
					$temp_colour = array();
					if (isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['colour']) && !empty($arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'])) {

						if (!in_array(trim($arr_old_data['colour']), $arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'])) {
							
							$temp_colour = $arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'];

							$arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'] = array_merge($temp_colour, (array) trim($arr_old_data['colour']));
						}
						
					} else if(!isset($arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'])) {
						$arr_attribute_collection[$this->_parent_ids[$final_name]]['colour'] = (array) trim($arr_old_data['colour']);
					}
				}
			}

			$parent_id++;
		}
		
		fclose($_newFile);

		update_option('arr_attribute_collection_by_parent', $arr_attribute_collection);
		
		return $this;   
	}

	public function get_all_name_counts() {

        $get_language = $this->rt_get_settings('rt_language');
        $count_variation_key    = 'count_variation_'.$get_language;
        $count_variation_data   = get_option($count_variation_key);

        if (empty($count_variation_data)) {
            $count_variation_data = array();
        }
        return $count_variation_data;
    }

	public function getFile(){ 
		
		if($this->_file == null) { 
			
			if(!file_exists($this->getFilePath())) die('File not exist'); 
			
			$this->_file = fopen($this->getFilePath(), 'r');  
			  
			$this->_csvHeader = array_map(array($this, 'rt_sanitize_header'),fgetcsv($this->_file, 500000, ';'));
		}
		
		return  $this->_file;
	}

	public function getFilePath() {
		
		return RT_CSV_DOWNLOADS .'/'. RT_DOWNLOADED_FILE_NAME;
	}

	public function getNewFilePath() {
		
		return RT_ABSPATH . 'csv/assortiment-new.csv';
	}

	public function rt_sanitize_header($str) {
		$str = str_replace(' ', '_', trim(strtolower($str)));
		return str_replace('.', '_', $str);
	}

	public function get_plain_product_name($_data) {

		$temp_name = '';
		if ($this->_current_lan == 'nl') {

			if (isset($_data['naam'])) {
		  		$temp_name = $_data['naam'];
		  		if (isset($_data['maat'])) {
		  			$temp_name = str_replace($_data['maat'], '', $temp_name);
		  			$temp_size = str_replace(' ', '', $_data['maat']);
		  			$temp_name = str_replace($temp_size, '', $temp_name);
		  		}
		  		if (isset($_data['kleur'])) {
		  			$temp_name = str_replace($_data['kleur'], '', $temp_name);
		  		}
		  	}

		} else {

			if (isset($_data['name'])) {
		  		$temp_name = $_data['name'];
		  		if (isset($_data['size'])) {
		  			$temp_name = str_replace($_data['size'], '', $temp_name);
		  			$temp_size = str_replace(' ', '', $_data['size']);
		  			$temp_name = str_replace($temp_size, '', $temp_name);
		  		}
		  		if (isset($_data['colour'])) {
		  			$temp_name = str_replace($_data['colour'], '', $temp_name);
		  		}
		  	}
		}
	  	return trim($temp_name);
	}

	public function RT_Active() {

		if( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$this->rt_woocommerce_missing_notice();
		}
		update_option('woocommerce_price_thousand_sep', ',', 'yes');
		update_option('woocommerce_price_decimal_sep', ',', 'yes');
		//$this->rt_read_csv_for_mapping();
	}

	/**
	 * load the plugin text domain for translation.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function rt_load_plugin_textdomain() {

		$locale = apply_filters( 'rt_plugin_locale', get_locale(), 'rt-importer' );

		load_textdomain( 'rt-importer', trailingslashit( WP_LANG_DIR ) . 'languages/rt-importer' . '-' . $locale . '.mo' );
		load_plugin_textdomain( 'rt-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		return true;
	}

	public function rt_get_category_structure( $taxonomy, $parent = 0 ) {
		// only 1 taxonomy
		$taxonomy = is_array( $taxonomy ) ? array_shift( $taxonomy ) : $taxonomy;
		// get all direct decendents of the $parent
		$terms = get_terms( $taxonomy, array('parent' => $parent, 'hide_empty' => false) );
		// prepare a new array.  these are the children of $parent
		// we'll ultimately copy all the $terms into this new array, but only after they
		// find their own children
		$children = array();
		// go through all the direct decendents of $parent, and gather their children
		foreach( $terms as $term ) {
			$term->children = $this->rt_get_category_structure( $taxonomy, $term->term_id );
			$children[ $term->term_id ] = $term;
		}
		// send the results back to the caller
		return $children;
	}

	public function rt_get_category_options($value = '', $is_child = '', $category) {

		$selected = (in_array($category->term_id, $value)) ? "selected" : "";
		echo '<option value="'.$category->term_id.'" '.$selected.'>' . $is_child . $category->name . '</option>';
		$is_child = '-' . $is_child;
		if (!empty($category->children)) {

			foreach ($category->children as $id => $childrens) {

				if (!empty($childrens)) {
					$this->rt_get_category_options($value, $is_child, $childrens);
				}
			}
		}
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function rt_woocommerce_missing_notice() {
		
    	$error = new WP_Error( 'rt_active_error', sprintf( __( 'Royal textile impoter Plugin requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-additional-variation-images' ), '<a href="http://www.woocommerce.com/" target="_blank">WooCommerce</a>' ) );
		echo $error->get_error_message();
		die();
	}

	public function rt_save_mapped_data() {
		parse_str($_POST['data'], $data);

		if (isset($data['rt_mapbycategory_data_save']) && wp_verify_nonce($data['rt_mapbycategory_data_save'], 'rt_mapbycategory_action')) {

			unset($data['rt_mapbycategory_data_save']);
			unset($data['_wp_http_referer']);
			
			$get_language = $this->rt_get_settings('rt_language');
			$data_key = 'rt_mapbycategory_'.$get_language.'_data';
			$cat_data = get_option($data_key);
			$old_data_status = '';
			if (empty($cat_data)) {
				$cat_data = array();
				$old_data_status = 'empty';
			}
			$new_cat_value = array_merge($cat_data, $data);

			update_option($data_key, $new_cat_value);
			if (!get_option('rt_cron_status')) {
				update_option('rt_cron_status', 'running');
			}
			$show_import = false;
			if (!empty($new_cat_value)) {
				$show_import = true;
			}
			echo json_encode(array('status' => 'success', 'import' => $show_import, 'old_status' => $old_data_status));
		}

		if (isset($data['rt_mapbysubgroup_data_save']) && wp_verify_nonce($data['rt_mapbysubgroup_data_save'], 'rt_mapbysubgroup_action')) {

			unset($data['rt_mapbysubgroup_data_save']);
			unset($data['_wp_http_referer']);

			$get_language = $this->rt_get_settings('rt_language');
			$data_key = 'rt_mapbysubgroup_'.$get_language.'_data';
			$subgroup_data = get_option($data_key);
			
			$old_data_status = '';
			if (empty($subgroup_data)) {
				$subgroup_data = array();
				$old_data_status = 'empty';
			}

			$new_subgroup_value = array_merge($subgroup_data, $data);
			update_option($data_key, $new_subgroup_value);
			
			$show_import = false;
			if (!empty($new_subgroup_value)) {
				$show_import = true;
			}
			echo json_encode(array('status' => 'success', 'import' => $show_import, 'old_status' => $subgroup_data));
		}
		die();
	}

	public function save_rt_importer_data() {

		if (isset($_POST['rt_settings_data_save']) && wp_verify_nonce($_POST['rt_settings_data_save'], 'rt_settings_action') && isset($_POST['rt_settings_data_save'])) {
			
			unset($_POST['rt_settings_data_save']);
			unset($_POST['_wp_http_referer']);

			$get_language = $this->rt_get_settings('rt_language');
			$rt_mapping_mod = $this->rt_get_settings('rt_mapping_mod');
			update_option('rt_plugin_settings', $_POST);
			$this->_enable_sub_menu = true;
			$this->_current_lan = $_POST['rt_language'];
			if ($get_language != $_POST['rt_language']) {
				$result = $this->download_file();
			}
			if ($rt_mapping_mod != $_POST['rt_mapping_mod']) {
				update_option('update_maped_page', '0');
			}
			if (isset($_POST['rt_owerwrite_stock_status']) && $_POST['rt_owerwrite_stock_status'] === 'yes') {
				update_option('woocommerce_manage_stock', 'no');
			} else {
				update_option('woocommerce_manage_stock', 'yes');
			}
			add_action( 'admin_notices', array( $this, 'rt_update_settings_message' ) );
		}
		
		if (isset($_POST['rt_mapbycategory_data_save']) && wp_verify_nonce($_POST['rt_mapbycategory_data_save'], 'rt_mapbycategory_action')) {

			unset($_POST['rt_mapbycategory_data_save']);
			unset($_POST['_wp_http_referer']);
			
			$get_language = $this->rt_get_settings('rt_language');
			$data_key = 'rt_mapbycategory_'.$get_language.'_data';

			update_option($data_key, $_POST);
		}

		if (isset($_POST['rt_mapbysubgroup_data_save']) && wp_verify_nonce($_POST['rt_mapbysubgroup_data_save'], 'rt_mapbysubgroup_action')) {

			unset($_POST['rt_mapbysubgroup_data_save']);
			unset($_POST['_wp_http_referer']);

			$get_language = $this->rt_get_settings('rt_language');
			$data_key = 'rt_mapbysubgroup_'.$get_language.'_data';

			update_option($data_key, $_POST);
		}

		if (isset($_POST['rt_reset_cron_save']) && wp_verify_nonce($_POST['rt_reset_cron_save'], 'rt_reset_cron_action')) {

			wp_clear_scheduled_hook( 'rt_action_product_image_cron_job' );
			wp_clear_scheduled_hook( 'rt_action_product_cron_job' );
			update_option('rt_cron_status', 'running');
		}
		if (empty(get_option('rt_plugin_settings'))) {
         	remove_submenu_page('rt-settigns', 'rt-mapping-tool');   
        }
	}

	public static function rt_get_file_url() {

		if (self::rt_get_settings('rt_language') == 'nl') {
			return 'https://www.royaltextile.nl/feed/assortmentfeeddownload.aspx?language=nl';
		} else {
			return 'https://www.royaltextile.nl/feed/assortmentfeeddownload.aspx?language=en';
		}
	}

	public static function rt_get_settings($setting_id = '') {
		$get_settings = get_option('rt_plugin_settings');
		return (isset($get_settings[$setting_id])) ? $get_settings[$setting_id] : '';
	}

	public function rt_update_settings_message() {

		printf( '<div class="updated notice"><p>%s</p></div>', __('Settings Saved.', 'rt-importer') );
	}

	public function rt_update_message() {

		printf( '<div class="updated notice"><p>%s</p></div>', $this->_message );
	}

	public function rt_error_message() {

		printf( '<div class="error notice"><p>%s</p></div>', $this->_message );
	}
}

new RT_Importer;
<?php
if (!defined('ABSPATH')) {
	exit;
}

Class CRON_Image_Importer extends RT_Import {

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

	private $_cron_schedule = '';

	private $_cron_row_count = '';

	private $_parent_obj = '';

	public function __construct() {

		$this->_parent_obj = new RT_Importer;
		add_filter( 'cron_schedules', array($this, 'rt_add_cron_interval') );
		add_action( 'wp', array($this, 'rt_run_cron_job') );
		add_action( 'admin_init', array($this, 'rt_run_cron_job') );
		add_action( 'rt_action_product_image_cron_job', array($this, 'rt_action_product_image_assign_cron_job') );
	}

	// Schedule Cron Job Event
	public function rt_run_cron_job() {

		if ( ! wp_next_scheduled( 'rt_action_product_image_cron_job' ) ) {
            wp_schedule_event( time(), 'every_fifteen', 'rt_action_product_image_cron_job' );
            update_option('rt_cron_image_status', 'running');
		}
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
            }else{
                $file = $upload_dir['basedir'] . '/' . $filename;
            }

            $opts = array('http'=>array('header' => "User-Agent:MyAgent/1.0\r\n"));
			$context = stream_context_create($opts);
			$header = file_get_contents($image_url,false,$context);
		
            if ($header) {
            	file_put_contents($file, $header);
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

	public function rt_add_cron_interval( $schedules ) {

	    $schedules['every_fifteen'] = array(
	            'interval'  => 900, // time in seconds
	            'display'   => 'Every Fifteen Minutes'
	    );
	    return $schedules;
	}

	public function rt_action_product_image_assign_cron_job() {
		
		if ($this->_get_page == 1) {
            update_option('rt_cron_image_status', 'running');
        }

        while (($row = fgetcsv($this->getFile(), 500000, ';')) !== FALSE) {

            $this->_csvData[] = array_combine($this->_csvHeader, $row);
        }
        fclose($this->getFile());
        
        $this->_cron_row_count = count($this->_csvData);
        return $this->rt_import_auto_products_image();
	}

	public function getFile(){

        //$obj = new RT_Importer
        if($this->_file == null) {
            
            if(!file_exists($this->getCsvPath())) throw new \Exception(__('File does not exist.'));

            $this->_file = fopen($this->getCsvPath(), 'r');

            $this->_csvHeader = fgetcsv($this->_file, 500000, ';');
        }

        return  $this->_file;
    }

	public function rt_import_auto_products_image() {
		
		if(!empty($this->_csvData) && count($this->_csvData)) {
			$i = 0;
            for($index = $this->getPageStartIndex(); $index <= $this->getPageEndIndex(); $index++) {
            	
            	if ($index == $i) {
                	$this->_get_page = (int) ($this->_get_page + 1);
                }
                if(isset($this->_csvData[$index]) && is_array($this->_csvData[$index])) {

                    $this->rt_assign_image_to_product($this->_csvData[$index]);
                }
                $i++;
            }
        }
        if (get_option('rt_cron_image_status') == 'running') {
        	$this->rt_import_auto_products_image();
        }
	}

	public function rt_assign_image_to_product($_data = array()) {

		$this->_csvDataParent = '';
        
        extract($_data);

        $sku = (string) preg_replace('/[^A-Za-z0-9]/', '', $sku);
        
        $_status = $this->hasNextPage() ? 'running' : 'completed';
        update_option('rt_cron_image_status', $_status);

        $product_id = wc_get_product_id_by_sku( $sku );

        // Get product ID from SKU if created during the importation.
        if ( $product_id ) {

            $objProduct = wc_get_product( $product_id );
            /*
	        * Set Product image and gallery images
	        */
	        $arr_image_ids = $this->rt_get_image_ids($_data);
	        
	        if (!empty($arr_image_ids)) {
	            
	            $objProduct->set_image_id($arr_image_ids[0]); // set the first image as primary image of the product

	            //in case we have more than 1 image, then add them to product gallery. 
	            if(count($arr_image_ids) > 1){
	                unset($arr_image_ids[0]);
	                $objProduct->set_gallery_image_ids($arr_image_ids);
	            }
	        }
	        $objProduct->save();

        } else {
            return;
        }
	}
}

new CRON_Image_Importer;
<?php
if (!defined('ABSPATH')) {
	exit;
}


$get_language = $this->rt_get_settings('rt_language');
$map_by = $this->rt_get_settings('rt_mapping_mod');
$get_language = ($get_language) ? $get_language : 'en';
$map_by = ($map_by) ? $map_by : 'by_category';
$mapbycategory_key 		= 'rt_mapbycategory_'.$get_language.'_data';
$mapbycategory_data 	= get_option($mapbycategory_key);
$mapbysubgroup_key 		= 'rt_mapbysubgroup_'.$get_language.'_data';
$mapbysubgroup_data 	= get_option($mapbysubgroup_key);

$arr_category = get_option('mapping_category_'.$get_language);
$arr_subgroup = get_option('mapping_subgroup_'.$get_language);
$is_have_cat = (empty($mapbycategory_data)) ? 'disabled' : '';
$is_have_grp = (empty($mapbysubgroup_data)) ? 'disabled' : '';

?>
<div class="rt-mapping-outer-wrapper">
	<?php if ($map_by == 'by_category' && !empty($mapbycategory_data) || $map_by == 'by_subgroup' && !empty($mapbysubgroup_data)) { ?>
		<table class="wp-list-table rt-import-table" id="rt-importer-table">
			<tbody>
				<tr>
					<td style="width: 130px;">
						<form method="post">
							<?php wp_nonce_field( 'rt_reset_cron_action', 'rt_reset_cron_save' ); ?>
							<button type="submit" class="button button-primary button-large rt-cron-importer-btn"><?php _e('Reset Import', 'rt-importer'); ?></button>
						</form>
					</td>
					<td>
						<h2 class="backend-importing-result"><?php echo __('Product Importing status: ', 'rt-importer'); ?> <span style="color: green;"><?php echo get_option('rt_cron_status'); ?></span></h2>	
					</td>
				</tr>
			</tbody>
		</table>
	<?php } ?>
	<table class="wp-list-table" id="rt-importer-table">
		<tbody>
			<?php if($map_by == 'by_category' && !empty($mapbycategory_data) || $map_by == 'by_subgroup' && !empty($mapbysubgroup_data)) { ?>
				<tr>
					<td style="width: 80px;"><button type="button" class="button button-primary button-large rt-importer-btn" onclick="rt_import_product(1, 'running');"><?php echo __('Import', 'rt-importer'); ?></button></td>
					<td>
	                    <div class="rt-progress">
	                        <div class="rt-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%">
	                            0%
	                        </div>
	                    </div>
	                </td>
				</tr>
			<?php } else { ?>
				<tr>
					<td>
						<?php _e('Please map categories.', 'rt-importer'); ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
		
	<h2 class="rt-admin-mapping-title">
		<?php _e('Category Mapping', 'rt-importer'); ?>
		<?php if($map_by == 'by_subgroup') { ?>
			<a href="#mapbysubgroup" class="active"><?php _e('Map By Subgroup', 'rt-importer'); ?></a>
		<?php } if($map_by == 'by_category') { ?>
			<a href="#mapbycategory" class="active"><?php _e('Map By Category', 'rt-importer'); ?></a>
		<?php } ?>
	</h2>
	<?php if($map_by == 'by_category') { ?>
		<div id="mapbycategory" class="rt-mapping-content active">
			<form method="post">
				<?php wp_nonce_field( 'rt_mapbycategory_action', 'rt_mapbycategory_data_save' ); ?>
				<table class="wp-list-table rt-mapping-table" id="rt-mapping--categories-table">
					<thead>
						<tr>
							<td>
								<?php _e('Supplier\'s category', 'rt-importer'); ?>
							</td>
							<td style="width: 300px;">
								<?php _e('Store category', 'rt-importer'); ?>
							</td>
						</tr>
					</thead>
					<tbody>
						<?php
						if (!empty($arr_category)) {
							
							foreach ($arr_category as $supplier_category) {
								
								$sup_cat_name = sanitize_title(trim($supplier_category));
								?>
								<tr>
									<td>
										<label><?php echo $supplier_category; ?></label>
									</td>
									<td>
										<select name="<?php echo $sup_cat_name; ?>[]" class="rt-mapping-cat-list js-states form-control" multiple="multiple">
											<option value=""><?php _e('Select category', 'rt-importer'); ?></option>
											<?php
												foreach ($category_structire as $id => $category) {
													$mapbycategory_data_value = (isset($mapbycategory_data[$sup_cat_name])) ? $mapbycategory_data[$sup_cat_name] : array();

													$this->rt_get_category_options($mapbycategory_data_value, '', $category);
												}
											?>
										</select>
									</td>
								</tr>
								<?php
							}
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<td></td>
							<td style="text-align: right;"><button type="submit" class="button button-primary button-large save-mapped-data"><?php _e('Save', 'rt-importer'); ?></button></td>
						</tr>
					</tfoot>
				</table>
			</form>
		</div>
	<?php } if($map_by == 'by_subgroup') { ?>
		<div id="mapbysubgroup" class="rt-mapping-content active">
			<form method="post">
				<?php wp_nonce_field( 'rt_mapbysubgroup_action', 'rt_mapbysubgroup_data_save' ); ?>
				<table class="wp-list-table rt-mapping-table" id="rt-mapping--subgroup-table">
					<thead>
						<tr>
							<td>
								<?php _e('Supplier\'s Subgroup', 'rt-importer'); ?>
							</td>
							<td style="width: 300px;">
								<?php _e('Store category', 'rt-importer'); ?>
							</td>
						</tr>
					</thead>
					<tbody>
						<?php
						if (!empty($arr_subgroup)) {
							
							foreach ($arr_subgroup as $supplier_subgroup) {
								$supsubgroup_name = sanitize_title(trim($supplier_subgroup));
								?>
								<tr>
									<td>
										<label><?php echo $supplier_subgroup; ?></label>
									</td>
									<td>
										<select name="<?php echo $supsubgroup_name; ?>[]" class="rt-mapping-cat-list js-states form-control" multiple="multiple">
											<option value=""><?php _e('Select category', 'rt-importer'); ?></option>
											<?php
												foreach ($category_structire as $id => $category) {
													$supsubgroup_value = (isset($mapbysubgroup_data[$supsubgroup_name])) ? $mapbysubgroup_data[$supsubgroup_name] : array();
													$this->rt_get_category_options($supsubgroup_value, '', $category);
												}
											?>
										</select>
									</td>
								</tr>
								<?php
							}
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<td></td>
							<td style="text-align: right;"><button type="submit" class="button button-primary button-large save-mapped-data"><?php _e('Save', 'rt-importer'); ?></button></td>
						</tr>
					</tfoot>
				</table>
			</form>
		</div>
	<?php } ?>
</div>
<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('.rt-admin-mapping-title a').click(function(e){
			e.preventDefault();
			var attr_href = jQuery(this).attr('href');
			console.log(attr_href);
			jQuery('.rt-admin-mapping-title a').removeClass('active');
			jQuery('.rt-mapping-content').removeClass('active');
			jQuery(attr_href).addClass('active');
			jQuery(this).addClass('active');
		});
	});
</script>
<style type="text/css">
.rt-mapping-outer-wrapper {
    margin-top: 25px;
    margin-bottom: 25px;
    width: 95%;
    margin-left: 15px;
}
.rt-admin-mapping-title {
    background: #0073aa;
    padding: 15px;
    color: #fff;
    font-size: 20px;
    margin: 0;
}
.wp-list-table.rt-mapping-table {
    width: 100%!important;
}
.rt-mapping-table label {
    font-size: 15px;
    font-weight: 500;
}
.wp-admin .rt-mapping-table select {
    width: 160px;
}
.rt-mapping-table td {
    padding: 5px 10px;
    border: 0;
}
.rt-mapping-table tfoot {
    background-color: #ddd;
}
.rt-admin-mapping-title a:focus,
.rt-admin-mapping-title a {
    color: #fff;
    border: 0;
    text-decoration: none;
    float: right;
    margin-left: 15px;
    font-size: 15px;
    outline: none;
    text-shadow: none;
    box-shadow: none;
}
.rt-mapping-table thead {
    background-color: #ddd;
    font-size: 17px;
    font-weight: bold;
}
.rt-mapping-table thead td {
    padding: 10px;
}
.rt-mapping-content {
	display: none;
}
.rt-mapping-content.active {
	display: block;
	background-color: #fff;
}
.rt-admin-mapping-title a.active {
    color: #F48024;
}
.select2-container--default .select2-search--inline .select2-search__field,
.rt-mapping-table .select2-container {
    width: 100%!important;
}
.select2-container--default .select2-search--inline .select2-search__field::placeholder { /* Chrome, Firefox, Opera, Safari 10.1+ */
  font-size: 15px;
  opacity: 1; /* Firefox */
}

.select2-container--default .select2-search--inline .select2-search__field:-ms-input-placeholder { /* Internet Explorer 10-11 */
	font-size: 15px;
  	color: red;
}

.select2-container--default .select2-search--inline .select2-search__field::-ms-input-placeholder { /* Microsoft Edge */
  	font-size: 15px;
  	color: red;
}
.select2-container .select2-search--inline {
    margin: 0;
}
.rt-import-table td,
#rt-importer-table td {
    padding: 8px;
}
.rt-import-table,
#rt-importer-table {
    background-color: #fff;
    width: 100%;
    margin-bottom: 15px;
    box-shadow: 0 0 2px 0px rgba(0,0,0,0.2);
}
.button.rt-importer-btn {
    font-size: 16px;
    line-height: 30px!important;
}
.rt-progress {
    height: 20px;
    overflow: hidden;
    background-color: #f5f5f5;
    border-radius: 4px;
    -webkit-box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
    box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
    display: none;
}
.rt-progress-bar {
    float: left;
    width: 0%;
    height: 100%;
    font-size: 12px;
    line-height: 20px;
    color: #fff;
    text-align: center;
    background-color: #337ab7;
    -webkit-box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
    box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
    -webkit-transition: width .6s ease;
    -o-transition: width .6s ease;
    transition: width .6s ease;
}
.backend-importing-result {
    text-transform: capitalize;
    margin: 0;
}
</style>
<?php
if (!defined('ABSPATH')) {
	exit;
}
$get_settings = get_option('rt_plugin_settings');
?>
<div class="rt-admin-setttings-out-wrapper">
	<h2 class="rt-admin-setting-title"><?php _e('Settings', 'rt-importer'); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'rt_settings_action', 'rt_settings_data_save' ); ?>
		<table class="wp-list-table rt-settings-table">
			<tbody>
				<tr>
					<td><label for="rt_language"><?php _e('CSV language', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_language" id="rt_language">
							<option value="en" <?php echo (isset($get_settings['rt_language']) && $get_settings['rt_language'] == 'en') ? 'selected' : ''; ?>><?php _e('English', 'rt-importer'); ?></option>
							<option value="nl" <?php echo (isset($get_settings['rt_language']) && $get_settings['rt_language'] == 'nl') ? 'selected' : ''; ?>><?php _e('Dutch', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_mapping_mod"><?php _e('Category Maping By', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_mapping_mod" id="rt_mapping_mod">
							<option value="by_category" <?php echo (isset($get_settings['rt_mapping_mod']) && $get_settings['rt_mapping_mod'] == 'by_category') ? 'selected' : ''; ?>><?php _e('Category', 'rt-importer'); ?></option>
							<option value="by_subgroup" <?php echo (isset($get_settings['rt_mapping_mod']) && $get_settings['rt_mapping_mod'] == 'by_subgroup') ? 'selected' : ''; ?>><?php _e('Subgroup', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_product"><?php _e('Overwrite Product?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_product" id="rt_owerwrite_product">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_product']) && $get_settings['rt_owerwrite_product'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_product']) && $get_settings['rt_owerwrite_product'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_price"><?php _e('Overwrite Price?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_price" id="rt_owerwrite_price">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_price']) && $get_settings['rt_owerwrite_price'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_price']) && $get_settings['rt_owerwrite_price'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_stock"><?php _e('Overwrite Stock?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_stock" id="rt_owerwrite_stock">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_stock']) && $get_settings['rt_owerwrite_stock'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_stock']) && $get_settings['rt_owerwrite_stock'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_stock_status"><?php _e('Overwrite Stock By Status?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_stock_status" id="rt_owerwrite_stock_status">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_stock_status']) && $get_settings['rt_owerwrite_stock_status'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_stock_status']) && $get_settings['rt_owerwrite_stock_status'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_brand"><?php _e('Overwrite Brand?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_brand" id="rt_owerwrite_brand">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_brand']) && $get_settings['rt_owerwrite_brand'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_brand']) && $get_settings['rt_owerwrite_brand'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_owerwrite_quality"><?php _e('Overwrite Quantity?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_owerwrite_quantity" id="rt_owerwrite_quantity">
							<option value="no" <?php echo (isset($get_settings['rt_owerwrite_quantity']) && $get_settings['rt_owerwrite_quantity'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_owerwrite_quantity']) && $get_settings['rt_owerwrite_quantity'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="rt_cron_job_schedule"><?php _e('Cron Job Schedule', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_cron_job_schedule" id="rt_cron_job_schedule">
							<option value="3600" <?php echo (isset($get_settings['rt_cron_job_schedule']) && $get_settings['rt_cron_job_schedule'] == '3600') ? 'selected' : ''; ?>><?php _e('1 Hour', 'rt-importer'); ?></option>
							<option value="7200" <?php echo (isset($get_settings['rt_cron_job_schedule']) && $get_settings['rt_cron_job_schedule'] == '7200') ? 'selected' : ''; ?>><?php _e('2 Hours', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr>
				<!-- <tr>
					<td><label for="rt_disable_cron_job"><?php _e('Disable Cron Job?', 'rt-importer'); ?></label></td>
					<td>
						<select name="rt_disable_cron_job" id="rt_disable_cron_job">
							<option value="no" <?php echo (isset($get_settings['rt_disable_cron_job']) && $get_settings['rt_disable_cron_job'] == 'no') ? 'selected' : ''; ?>><?php _e('No', 'rt-importer'); ?></option>
							<option value="yes" <?php echo (isset($get_settings['rt_disable_cron_job']) && $get_settings['rt_disable_cron_job'] == 'yes') ? 'selected' : ''; ?>><?php _e('Yes', 'rt-importer'); ?></option>
						</select>
					</td>
				</tr> -->			
				<tr>
					<td></td>
					<td></td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td></td>
					<td style="text-align: right;"><button type="submit" class="button button-primary button-large"><?php _e('Save', 'rt-importer'); ?></button></td>
				</tr>
			</tfoot>
		</table>
	</form>
</div>

<style type="text/css">
.rt-admin-setttings-out-wrapper {
    margin-top: 25px;
    margin-bottom: 25px;
    width: 90%;
    background-color: #fff;
    margin-left: 15px;
}
.rt-admin-setting-title {
    background: #0073aa;
    padding: 15px;
    color: #fff;
    font-size: 20px;
    margin: 0 0 10px 0;
}
.wp-list-table.rt-settings-table {
    width: 100%;
}
.rt-settings-table label {
    font-size: 15px;
    font-weight: 600;
}
.wp-admin .rt-settings-table select {
    width: 160px;
}
.rt-settings-table td {
    padding: 5px 10px;
    border: 0;
}
.rt-settings-table tfoot {
    background-color: #ccc;
}
</style>
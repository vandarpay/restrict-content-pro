<?php
/**
 * Vandar gateway settings.
 *
 * @package RCP_Vandar
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function rcp_vandar_settings( $rcp_options ) {
	?>
	<hr>

	<table class="form-table">
		<tr valign="top">
			<th colspan="2">
				<h3><?php _e( 'Vandar gateway settings', 'vandar-for-rcp' ); ?></h3>
			</th>
		</tr>
		<tr valign="top">
			<th>
				<label for="rcp_settings[vandar_api_key]" id="vandarApiKey"><?php _e( 'API Key', 'vandar-for-rcp' ); ?></label>
			</th>
			<td>
				<input class="regular-text" name="rcp_settings[vandar_api_key]" id="vandarApiKey" value="<?php echo isset( $rcp_options['vandar_api_key'] ) ? $rcp_options['vandar_api_key'] : ''; ?>">
				<p class="description"><?php _e( 'You can create an API Key by going to your <a href="https://dash.vandar.io/">Vandar account</a>.', 'vandar-for-rcp' ); ?></p>
			</td>
		</tr>
	</table>
    <hr>
	<?php
}

add_action('rcp_payments_settings', 'rcp_vandar_settings');

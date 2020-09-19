<?php
/**
 * Required functions.
 *
 * @package RCP_Vandar
 * @since 1.0
 */

/**
 * Call the gateway endpoints.
 *
 * Try to get response from the gateway for 4 times.
 *
 * @param string $url
 * @param array $args
 * @return array|WP_Error
 */
function rcp_vandar_call_gateway_endpoint( $url, $args ) {
	$tries = 4;

	while ( $tries ) {
		$response = wp_safe_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			$tries--;
			continue;
		} else {
			break;
		}
	}

	return $response;
}

/**
 * Check the payment ID in the system.
 *
 * @param string $id
 * @return void
 */
function rcp_vandar_check_verification( $id ) {

	global $wpdb;

	if ( ! function_exists( 'rcp_get_payment_meta_db_name' ) ) {
		return;
	}

	$table = rcp_get_payment_meta_db_name();

	$check = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE meta_key='_verification_params' AND meta_value='vandar-%s'",
			$id
		)
	);

	if ( ! empty( $check ) ) {
		wp_die( __( 'Duplicate payment record', 'vandar-for-rcp' ) );
	}
}

/**
 * Set the payment ID for later verifications.
 *
 * @param int $payment_id
 * @param string $param
 * @return void
 */
function rcp_vandar_set_verification( $payment_id, $params ) {
	global $wpdb;

	if ( ! function_exists( 'rcp_get_payment_meta_db_name' ) ) {
		return;
	}

	$table = rcp_get_payment_meta_db_name();

	$wpdb->insert(
		$table,
		array(
			'payment_id'	=> $payment_id,
			'meta_key'		=> '_verification_params',
			'meta_value'	=> $params,
		), 
		array('%d', '%s', '%s')
	);
}

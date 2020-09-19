<?php
/**
 * Filters.
 *
 * @package RCP_Vandar
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register Vandar payment gateway.
 *
 * @param array $gateways
 * @return array
 */
function rcp_vandar_register_gateway( $gateways ) {

	$gateways['vandar']	= [
		'label'			=> __( 'Vandar Secure Gateway', 'vandar-for-rcp' ),
		'admin_label'	=> __( 'Vandar Secure Gateway', 'vandar-for-rcp' ),
	];

	return $gateways;

}

add_filter( 'rcp_payment_gateways', 'rcp_vandar_register_gateway' );

/**
 * Add IRR and IRT currencies to RCP.
 *
 * @param array $currencies
 * @return array
 */
function rcp_vandar_currencies( $currencies ) {
	unset( $currencies['RIAL'], $currencies['IRR'], $currencies['IRT'] );

	return array_merge( array(
		'IRT'		=> __( 'تومان ایران', 'vandar-for-rcp' ),
		'IRR'		=> __( 'ریال ایران', 'vandar-for-rcp' ),
	), $currencies );
}

/**
 * Format IRR currency displaying.
 *
 * @param string $formatted_price
 * @param string $currency_code
 * @param int $price
 * @return string
 */
function rcp_vandar_irr_before( $formatted_price, $currency_code = null, $price = null ) {
	return __( 'ریال', 'vandar-for-rcp' ) . ' ' . ( $price ? $price : $formatted_price );
}

add_filter( 'rcp_irr_currency_filter_before', 'rcp_vandar_irr_before' );

/**
 * Format IRR currency displaying.
 *
 * @param string $formatted_price
 * @param string $currency_code
 * @param int $price
 * @return string
 */
function rcp_vandar_irr_after( $formatted_price, $currency_code = null, $price = null ) {
	return ( $price ? $price : $formatted_price ) . ' ' . __( 'ریال', 'vandar-for-rcp' );
}

add_filter( 'rcp_irr_currency_filter_after', 'rcp_vandar_irr_after' );

/**
 * Format IRT currency displaying.
 *
 * @param string $formatted_price
 * @param string $currency_code
 * @param int $price
 * @return string
 */
function rcp_vandar_irt_after( $formatted_price, $currency_code = null, $price = null ) {
	return ( $price ? $price : $formatted_price ) . ' ' . __( 'تومان', 'vandar-for-rcp' );
}

add_filter( 'rcp_irt_currency_filter_after', 'rcp_vandar_irt_after' );

/**
 * Format IRT currency displaying.
 *
 * @param string $formatted_price
 * @param string $currency_code
 * @param int $price
 * @return string
 */
function rcp_vandar_irt_before( $formatted_price, $currency_code = null, $price = null ) {
	return __( 'تومان', 'vandar-for-rcp' ) . ' ' . ( $price ? $price : $formatted_price );
}

add_filter( 'rcp_irt_currency_filter_before', 'rcp_vandar_irt_before' );

/**
 * Save old roles of a user when updating it.
 *
 * @param WP_User $user
 * @return WP_User
 */
function rcp_vandar_registration_data( $user ) {
	$old_subscription_id = get_user_meta( $user['id'], 'rcp_subscription_level', true );
	if ( ! empty( $old_subscription_id ) ) {
		update_user_meta( $user['id'], 'rcp_subscription_level_old', $old_subscription_id );
	}

	$user_info     = get_userdata( $user['id'] );
	$old_user_role = implode( ', ', $user_info->roles );
	if ( ! empty( $old_user_role ) ) {
		update_user_meta( $user['id'], 'rcp_user_role_old', $old_user_role );
	}

	return $user;
}

add_filter( 'rcp_user_registration_data', 'rcp_vandar_registration_data' );

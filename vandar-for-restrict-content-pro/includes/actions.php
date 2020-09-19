<?php
/**
 * Actions.
 *
 * @package RCP_Vandar
 * @since 1.0
 */

/**
 * Creates a payment record remotely and redirects
 * the user to the proper page.
 *
 * @param array|object $subscription_data
 * @return void
 */
function rcp_vandar_create_payment( $subscription_data ) {

	global $rcp_options;

	$new_subscription_id = get_user_meta( $subscription_data['user_id'], 'rcp_subscription_level', true );
	if ( ! empty( $new_subscription_id ) ) {
		update_user_meta( $subscription_data['user_id'], 'rcp_subscription_level_new', $new_subscription_id );
	}

	$old_subscription_id = get_user_meta( $subscription_data['user_id'], 'rcp_subscription_level_old', true );
	update_user_meta( $subscription_data['user_id'], 'rcp_subscription_level', $old_subscription_id );

	// Start the output buffering.
	ob_start();

	$amount = str_replace( ',', '', $subscription_data['price'] );

	// Check if the currency is in Toman.
	if ( in_array( $rcp_options['currency'], array(
		'irt',
		'IRT',
		'تومان',
		__( 'تومان', 'rcp' ),
		__( 'تومان', 'vandar-for-rcp' )
	) ) ) {
		$amount = $amount * 10;
	}

	// Send the request to Vandar.
	$api_key = isset( $rcp_options['vandar_api_key'] ) ? $rcp_options['vandar_api_key'] : wp_die( __( 'Vandar API key is missing' ) );
	$callback = add_query_arg( [
	    'gateway'       => 'vandar-for-rcp',
	    'factorNumber'  => $subscription_data['payment_id']
    ], $subscription_data['return_url'] );

	$data = array(
		'api_key'			=> $api_key,
		'amount'			=> intval( $amount ),
        'callback_url'		=> $callback,
        'mobile_number'		=> '',
        'factorNumber'		=> $subscription_data['payment_id'],
        'description'		=> "{$subscription_data['subscription_name']} - {$subscription_data['key']}", //$subscription_data['user_name'] $subscription_data['user_email']
	);

	$args = array(
		'body'				=> json_encode( $data ),
		'headers'			=> ['Content-Type' => 'application/json'],
		'timeout'			=> 15,
	);

	$response = rcp_vandar_call_gateway_endpoint( 'https://ipg.vandar.io/api/v3/send', $args );
	if ( is_wp_error( $response ) ) {
		wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $response->get_error_message() ) );
	}

	$http_status	= wp_remote_retrieve_response_code( $response );
	$result			= wp_remote_retrieve_body( $response );
	$result			= json_decode( $result );

	if ( 200 !== $http_status || empty( $result ) || empty( $result->token ) || $result->status == 0) {
	    $msg = '';
	    foreach ($result->errors as $err){
            $msg .= $err . '<br>';
        }
		wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $msg ) );
	}

	// Update transaction id into payment
	$rcp_payments = new RCP_Payments();
	$rcp_payments->update( $subscription_data['payment_id'], array( 'transaction_id' => $result->token ) );

	ob_end_clean();
	if ( headers_sent() ) {
		echo '<script type="text/javascript">window.onload = function() { top.location.href = "https://ipg.vandar.io/v3/' . $result->token . '"; };</script>';
	} else {
		wp_redirect( 'https://ipg.vandar.io/v3/' . $result->token );
	}

	exit;
}

add_action( 'rcp_gateway_vandar', 'rcp_vandar_create_payment' );

/**
 * Verify the payment when returning from the IPG.
 *
 * @return void
 */
function rcp_vandar_verify() {

	if ( ! isset( $_GET['gateway'] ) )
		return;

	if ( ! class_exists( 'RCP_Payments' ) )
		return;

	if ( ! isset( $_GET['factorNumber'] ) )
		return;

	global $rcp_options, $wpdb, $rcp_payments_db_name;

	if ( 'vandar-for-rcp' !== sanitize_text_field( $_GET['gateway'] ) )
		return;

	$rcp_payments = new RCP_Payments();
	$payment_data = $rcp_payments->get_payment(sanitize_text_field( $_GET['factorNumber'] ));

	if ( empty( $payment_data ) )
		return;

	extract( (array) $payment_data );
	$user_id = intval( $user_id );
	$subscription_name = $subscription;

	if ( $payment_data->status == 'pending'
		&& $payment_data->gateway == 'vandar'
		&& $payment_data->transaction_id == sanitize_text_field( $_GET['token'] ) ) {

		$api_key  = isset( $rcp_options['vandar_api_key'] ) ? $rcp_options['vandar_api_key'] : wp_die( __( 'Vandar API key is missing' ) );
		$status   = sanitize_text_field( $_GET['payment_status'] );
		$token    = sanitize_text_field( $_GET['token'] );
		$order_id = sanitize_text_field( $_GET['factorNumber'] );
        $fault    = '';
        $transId  = '';
        $payment_method = 'vandar';

        if ( 'OK' != $status ) {
			$status = 'failed';
            $fault  = __( 'User canceled the payment.', 'vandar-for-rcp' );
		}
		else {

			rcp_vandar_check_verification( $token );

			$data = array(
				'token'		=> $token,
				'api_key'	=> $api_key,
			);
			$args = array(
				'body'				=> json_encode( $data ),
				'headers'			=> ['Content-Type' => 'application/json'],
				'timeout'			=> 15,
			);
			$response = rcp_vandar_call_gateway_endpoint( 'https://ipg.vandar.io/api/v3/verify', $args );
			if ( is_wp_error( $response ) ) {
				wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $response->get_error_message() ) );
			}

			$http_status	= wp_remote_retrieve_response_code( $response );
			$result			= wp_remote_retrieve_body( $response );
			$result			= json_decode( $result );

			if ( 200 !== $http_status || $result->status == 0 ) {
				$status = 'failed';
                $msg = '';
                foreach ($result->errors as $err){
                    $msg .= $err . '<br>';
                }
                $fault = $msg;
			}
			else {
                $status = 'complete';
                $amount = $result->amount;
                $transId = $result->transId;
            }
		}

		// Let RCP plugin acknowledge the payment.
		if ( 'complete' === $status ) {
			$payment_data = array(
				'date'				=> date( 'Y-m-d g:i:s' ),
				'subscription'		=> $subscription_name,
				'payment_type'		=> $payment_method,
				'subscription_key'	=> $subscription_key,
				'amount'			=> $amount,
				'user_id'			=> $user_id,
				'transaction_id'	=> $transId,
			);

			$rcp_payments = new RCP_Payments();
			$payment_id = $rcp_payments->insert( $payment_data );
			$rcp_payments->update( $order_id, array( 'status' => $status ) );
			rcp_vandar_set_verification( $payment_id, $token );

			$new_subscription_id = get_user_meta( $user_id, 'rcp_subscription_level_new', true );
			if ( ! empty( $new_subscription_id ) ) {
				update_user_meta( $user_id, 'rcp_subscription_level', $new_subscription_id );
			}

			rcp_set_status( $user_id, 'active' );

			if ( version_compare( RCP_PLUGIN_VERSION, '2.1.0', '<' ) ) {
				rcp_email_subscription_status( $user_id, 'active' );
				if ( ! isset( $rcp_options['disable_new_user_notices'] ) ) {
					wp_new_user_notification( $user_id );
				}
			}

			update_user_meta( $user_id, 'rcp_payment_profile_id', $user_id );
			update_user_meta( $user_id, 'rcp_signup_method', 'live' );
			update_user_meta( $user_id, 'rcp_recurring', 'no' );

			$subscription          = rcp_get_subscription_details( rcp_get_subscription_id( $user_id ) );
			$member_new_expiration = date( 'Y-m-d H:i:s', strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59' ) );
			rcp_set_expiration_date( $user_id, $member_new_expiration );
			delete_user_meta( $user_id, '_rcp_expired_email_sent' );

			$log_data = array(
				'post_title'   => __( 'Payment complete', 'vandar-for-rcp' ),
				'post_content' => __( 'Transaction ID: ', 'vandar-for-rcp' ) . $transId . __( ' / Payment method: ', 'vandar-for-rcp' ) . $payment_method,
				'post_parent'  => 0,
				'log_type'     => 'gateway_error'
			);

			$log_meta = array(
				'user_subscription' => $subscription_name,
				'user_id'           => $user_id
			);

			WP_Logging::insert_log( $log_data, $log_meta );
		}

		if ( 'failed' === $status ) {

			$rcp_payments = new RCP_Payments();
			$rcp_payments->update( $order_id, array( 'status' => $status ) );

			$log_data = array(
				'post_title'   => __( 'Payment failed', 'vandar-for-rcp' ),
				'post_content' => __( 'Transaction did not succeed due to following reason:', 'vandar-for-rcp' ) . $fault . __( ' / Payment method: ', 'vandar-for-rcp' ) . $payment_method,
				'post_parent'  => 0,
				'log_type'     => 'gateway_error'
			);

			$log_meta = array(
				'user_subscription' => $subscription_name,
				'user_id'           => $user_id
			);

			WP_Logging::insert_log( $log_data, $log_meta );
		}

		add_filter( 'the_content', function( $content ) use( $status, $transId, $fault ) {
			$message = '';

			if ( $status == 'complete' ) {
				$message = '<br><center class="alert alert-success vandar-alert">' . __( 'Payment was successful. Transaction tracking number is: ', 'vandar-for-rcp' ) . $transId . '</center>';
			}
			if ( $status == 'failed' ) {
				$message = '<br><center class="alert alert-danger vandar-alert">' . __( 'Payment failed due to the following reason: ', 'vandar-for-rcp' ) . $fault . ( !empty($transId)? '<br>' . __( 'Your transaction tracking number is: ', 'vandar-for-rcp' ) . $transId : '') . '</center>';
			}

            $message .= '<style>
                .vandar-alert {
                    border-radius: 5px;
                    padding: 5px;
                }
                .vandar-alert.alert-success {
                    background: #C8E6C9;
                    border: 1px solid #4CAF50;
                }
                .vandar-alert.alert-danger {
                    background: #FFCDD2;
                    border: 1px solid #F44336;
            }
            </style>';
			return $content . $message;
		} );
	}
}

add_action( 'init', 'rcp_vandar_verify' );

/**
 * Change a user status to expired instead of cancelled.
 *
 * @param string $status
 * @param int $user_id
 * @return boolean
 */
function rcp_vandar_change_cancelled_to_expired( $status, $user_id ) {
	if ( 'cancelled' == $status ) {
		rcp_set_status( $user_id, 'expired' );
	}

	return true;
}

add_action( 'rcp_set_status', 'rcp_vandar_change_cancelled_to_expired', 10, 2 );

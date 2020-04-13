<?php
/**
 * Plugin Name:OAuth Bearer Token Generation
 * Plugin URI: https://www.cloudesign.com
 * Text Domain: bearer-access-token-generate
 * Description: Bearer Access Token Generate and Cron Schedule to Generate Token Automatically.
 * Author: Amit Bauriya
 * Version: 1.0
 * Author URI: https://www.cloudesign.com
 *
 * @package WordPress
 * @author WP Amit Bauriya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Basic plugin definitions
 *
 * @package Bearer Token Generation
 * @since 1.0.0
 */
if( !defined( 'eshopauth_VERSION' ) ) {
	define( 'eshopauth_VERSION', '1.0' ); // Version of plugin
}
if( !defined( 'eshopauth_DIR' ) ) {
	define( 'eshopauth', dirname( __FILE__ ) ); // Plugin dir
}
if( !defined( 'eshopauth_URL' ) ) {
	define( 'eshopauth_URL', plugin_dir_url( __FILE__ ) ); // Plugin url
}
if( !defined( 'eshopauth_PLUGIN_BASENAME' ) ) {
	define( 'eshopauth_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Plugin base name
}

/**
 * Activation Hook
 *
 * Register plugin activation hook.
 *
 * @package Bearer Token Generation
 * @since 1.0.0
 */
register_activation_hook( __FILE__, 'eshopauth_install' );

/**
 * Deactivation Hook
 *
 * Register plugin deactivation hook.
 *
 * @package Bearer Token Generation
 * @since 1.0.0
 */
register_deactivation_hook( __FILE__, 'eshopauth_uninstall');

/**
 * Plugin Activation Function
 * Does the initial setup, sets the default values for the plugin options
 *
 * @package Bearer Token Generation
 * @since 1.0.0
 */
function eshopauth_install() {
}

function eshopauth_uninstall() {
}

/**
 * Function to display admin notice of activated plugin.
 *
 * @package Bearer Token Generation
 * @since 1.0.0
 */
function eshopauth_admin_notice() {
	global $pagenow;
	// Generate Notice for installing plugin for first time
	$notice_link        = add_query_arg( array('message' => 'eshopauth-plugin-notice'), admin_url('plugins.php') );
	$notice_transient   = get_transient( 'eshopauth_install_notice' );

	if ( $notice_transient == false &&  $pagenow == 'plugins.php' && file_exists($dir) && current_user_can( 'install_plugins' ) ) {
		echo '<div class="updated notice" style="position:relative;">
				<p>
					<strong>'.sprintf( __('Thank you for activating %s', 'oauth-api-woocommerce-access-token'), 'OAuth Baerer Token Generation').'</strong>
				</p>
				<a href="'.esc_url( $notice_link ).'" class="notice-dismiss" style="text-decoration:none;"></a>
			</div>';
	}
}

function get_transient_timeout( $transient ) {
    global $wpdb;
    $transient_timeout = $wpdb->get_col( "
      SELECT option_value
      FROM $wpdb->options
      WHERE option_name
      LIKE '%_transient_timeout_$transient%'
    " );
    return $transient_timeout[0];
}

//add_action('init', 'eshop_token_generation');


add_filter( 'cron_schedules', 'eshop_token_generation_cron' );
function eshop_token_generation_cron( $schedules ) {
    $schedules['every_three_weeks'] = array(
            'interval'  => 1814400,
            'display'   => __( 'Every three weeks', 'textdomain' )
    );
    return $schedules;
}


// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'eshop_token_generation_cron' ) ) {
    wp_schedule_event( time(), 'every_three_weeks', 'eshop_token_generation_cron' );
}

// Hook into that action that'll fire every three minutes
add_action( 'eshop_token_generation_cron', 'eshop_token_event_func' );
function eshop_token_event_func() {

	$es_client_id = ESHOP_CLIENT_ID;
	$es_client_secret = ESHOP_ACCOUNT_SECRET_KEY;
	$es_username = ESHOP_USERNAME;
	$es_password = ESHOP_PASSWORD;

	$transient_key = 'eshop_token_keys';
	$output = get_transient( $transient_key );

	$body = array(
			'client_id' => $es_client_id,
			'client_secret' => $es_client_secret,
			'username' => $es_username,
			'password' => $es_password,
			'grant_type' => 'password',
			'scope' => 'openid',
			'audience' => 'http://wms.eshopbox.com'
		);
		$jsonbody=json_encode($body);
		$args = array(
        'headers'     => array(
            'Content-Type'  => 'application/json'
        ),
        'body'        => json_encode($body),
    );
    
		$request_uri = 'http://wms.eshopbox.com/api/oauth/token';
		$request = wp_remote_post( $request_uri, $args );
		$reqcode = wp_remote_retrieve_response_code($request);
		if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
        	//echo "<pre>";print_r( $request);exit;
					set_transient( 'eshop_testingss', $reqcode, 3600 );
		}

		$response_token = wp_remote_retrieve_body( $request );
		if ( wp_remote_retrieve_response_code( $request ) == 200 ) {
					//echo "<pre>";print_r( $request);exit;
				}
		if( empty( $response_token ) ){
				$response_token = wp_remote_retrieve_body( $request );
		}
		$response_token_decode = json_decode( $response_token ,true );
		//update_post_meta('4649', 'shipment_activity', 	$response_token_decode);
		$response_token_value = $response_token_decode["access_token"];
		$response_token_expiry = $response_token_decode["expires_in"];
		$response_token_type = $response_token_decode["token_type"];

		set_transient( $transient_key, $response_token_value, $response_token_expiry);
}


add_filter( 'cron_schedules', 'eshop_token_regeneration_cron' );
function eshop_token_regeneration_cron( $schedules ) {
    $schedules['es_every_three_weeks'] = array(
            'interval'  => 3600,
            'display'   => __( 'Every day weeks', 'textdomain' )
    );
    return $schedules;
}


// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'eshop_token_regeneration_cron' ) ) {
    wp_schedule_event( time(), 'es_every_three_weeks', 'eshop_token_regeneration_cron' );
}

// Hook into that action that'll fire every three minutes
add_action( 'eshop_token_regeneration_cron', 'eshop_token_reevent_func' );
function eshop_token_reevent_func() {
	$transient_key = 'eshop_token_keys';
	$output = get_transient( $transient_key );
	if($output == false){
		//set_transient( $transient_key);
		eshop_token_event_func();
	}

}

add_filter( 'cron_schedules', 'shiprocket_token_generation_cron' );
function shiprocket_token_generation_cron( $schedules ) {
    $schedules['every_eight_days'] = array(
            'interval'  => 691200,
            'display'   => __( 'Every eight days', 'textdomain' )
    );
    return $schedules;
}


// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'shiprocket_token_generation_cron' ) ) {
    wp_schedule_event( time(), 'every_eight_days', 'shiprocket_token_generation_cron' );
}

// Hook into that action that'll fire every three minutes
add_action( 'shiprocket_token_generation_cron', 'shiprocket_token_event_func' );
function shiprocket_token_event_func() {

	$shiprocket_email = SHIPROCKET_EMAIL_ACCOUNT;
	$shiprocket_password = SHIPROCKET_PASWWORD;

	$transient_key = 'shiprocket_token_keys';
	$output = get_transient( $transient_key );
		$body = array(
			'email' => $shiprocket_email,
			'password' => $shiprocket_password,
		);
		$args = array(
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
    );

		$request_uri = 'https://apiv2.shiprocket.in/v1/external/auth/login';
		$request = wp_remote_post( $request_uri, $args );
		if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
    //Print Error
    }

		//echo $request;
		//$last_modified = wp_remote_retrieve_header( $response, 'last-modified' );
		$response_token = wp_remote_retrieve_body( $request );
		if ( wp_remote_retrieve_response_code( $request ) === 200 ) {
					//echo "<pre>";print_r( $request);exit;
				}
		$response_token_decode = json_decode( $response_token ,true );
		$response_token_value = $response_token_decode['token'];
		$response_token_expiry = '691200';
		//echo "<pre>";print_r($response_token_decode);exit;
		//$response_token_validity = json_decode( wp_remote_retrieve_body( $request, 'expires_in' ) );
		if( empty( $response_token ) ){
		//return;
}
		//echo "<pre>";print_r($response_token);exit;
		set_transient( $transient_key, $response_token_value, $response_token_expiry);

}

add_filter( 'cron_schedules', 'shiprocket_token_regeneration_cron' );
function shiprocket_token_regeneration_cron( $schedules ) {
    $schedules['shiprocket_token_regenerate'] = array(
            'interval'  => 3600,
            'display'   => __( 'Every day weeks', 'textdomain' )
    );
    return $schedules;
}


// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'shiprocket_token_regeneration_cron' ) ) {
    wp_schedule_event( time(), 'shiprocket_token_regenerate', 'shiprocket_token_regeneration_cron' );
}

// Hook into that action that'll fire every three minutes
add_action( 'shiprocket_token_regeneration_cron', 'shiprocket_token_reevent_func' );
function shiprocket_token_reevent_func() {
	$transient_key = 'shiprocket_token_keys';
	$output = get_transient( $transient_key );
	$shiprocket_timeout = get_transient_timeout( $transient_key );
	if($shiprocket_timeout < 172800){
		//set_transient( $transient_key);
		shiprocket_token_event_func();
	}

}

<?php
/**
 * The WC Software License Client Library 
 *
 * This class defines all code necessary to check for a valid license and available updates stored on your WooCommerce Software License Server  
 * 
 * Documentation can be found here : http://docs.wcvendors.com/wc-software-license-server
 * 
 * To integrate this into your software product include the following code in your MAIN plugin file, do not attempt 
 * to add this code in any other file but your main plugin file. 
 *
 * 		// Required Parameters 
 * 		 
 *		 @param string  required $license_server_url - The base url to your woocommerce shop 
 *		 @param string  required $version - the software version currently running 
 *		 @param string  required $text_domain - the text domain of the plugin - do we need this? 
 *		 @param string  required $plugin_file - path to the plugin file or directory, relative to the plugins directory
 *		 @param string  required $plugin_nice_name - A nice name for the plugin for use in messages 
 * 
 * 	 	 // Optional Parameters 		
 *		 @param string optional $slug - the plugin slug if your class file name is different to the specified slug on the WooCommerce Product 
 *		 @param integer optional $update_interval - time in hours between update checks 
 *		 @param bool optional $debug - enable debugging in the client library. 
 * 
 *  require_once plugin_dir_path( __FILE__ ) . 'path/to/wc-software-license-client/class-wc-software-license-client.php'; 
 *
 *	function wcslc_instance(){ 
 *		return WC_Software_License_Client::get_instance( 'http://yourshopurl.here.com', 1.0.0, 'your-text-domain', __FILE__, 'My Cool Plugin' ); 
 *	} // wcslc_instance()
 *
 *	wcslc_instance(); 
 * 
 *
 * @version  	1.0.1 
 * @since      	1.0.0
 * @package    	WC_Software_License_Client
 * @author     	Jamie Madden <support@wcvendors.com>
 * @link       	http://www.wcvendors.com/wc-software-license-server 
 * @todo  		Need to cache results and updates to reduce load 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Software_License_Client' ) ) :

class WC_Software_License_Client { 

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	private static $instances = array();

	/**
	 * Version - current plugin version 
	 * @since 1.0.0 
	 */
	public $version; 

	/**
	 * License URL - The base URL for your woocommerce install 
	 * @since 1.0.0 
	 */
	public $license_server_url; 

	/**
	 * Slug - the plugin slug to check for updates with the server 
	 * @since 1.0.0 
	 */
	public $slug; 

	/**
	 * Plugin text domain 
	 * @since 1.0.0 
	 */
	public $text_domain; 

	/**
	 * Path to the plugin file or directory, relative to the plugins directory
	 * @since 1.0.0 
	 */
	public $plugin_file; 

	/**
	 * Path to the plugin file or directory, relative to the plugins directory
	 * @since 1.0.0 
	 */
	public $plugin_nice_name; 

	/**
	 * Update interval - what period in hours to check for updates defaults to 12; 
	 * @since 1.0.0 
	 */
	public $update_interval; 

	/**
	 * Option name - wp option name for license and update information stored as $slug_wc_software_license
	 * @since 1.0.0 
	 */
	public $option_name; 

	/**
	 * The license server host 
	 */
	private $license_server_host; 

	/**
	 * The plugin license key 
	 */
	private $license_key; 

	/**
	 * The domain the plugin is running on 
	 */
	private $domain; 

	/**
	 * The plugin license key 
	 * 
	 * @since 1.0.0 
	 * @access private 
	 */
	private $admin_notice; 

	/**
	 * Don't allow cloning 
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Don't allow unserializing instances of this class
	 *
	 * @since 1.0.0 
	 */
	private function __wakeup() {}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0.0 
	 * @param string  $license_server_url - The base url to your woocommerce shop 
	 * @param string  $version - the software version currently running 
	 * @param string  $text_domain - the text domain of the plugin
	 * @param string  $plugin_file - path to the plugin file or directory, relative to the plugins directory
	 * @param string  $plugin_nice_name - A nice name for the plugin for use in messages 
	 * @param integer $update_interval - time in hours between update checks 
	 * @param bool 	  $debug - pass if the plugin is in debug mode 
	 * @return object A single instance of this class.
	 */
	public static function get_instance( $license_server_url, $version, $text_domain, $plugin_file, $plugin_nice_name, $slug = '', $update_interval = 12, $debug = false ){

		if ( !array_key_exists( $text_domain, self::$instances ) ) {
			 self::$instances[ $text_domain ] = new self( $license_server_url, $version, $text_domain, $plugin_file,  $plugin_nice_name, $slug, $update_interval, $debug );
		} 

		return self::$instances;

	} // get_instance()

	/**
	 * Initialize the class actions 
	 * 
	 * @since 1.0.0 
	 * @param string  $license_server_url - The base url to your woocommerce shop 
	 * @param string  $version - the software version currently running 
	 * @param string  $text_domain - the text domain of the plugin
	 * @param string  $plugin_file - path to the plugin file or directory, relative to the plugins directory
	 * @param string  $plugin_nice_name - A nice name for the plugin for use in messages 
	 * @param integer $update_interval - time in hours between update checks 
	 */
	private function __construct( $license_server_url, $version, $text_domain, $plugin_file, $plugin_nice_name, $slug, $update_interval, $debug ){

		
		$this->plugin_nice_name		= $plugin_nice_name; 
		$this->license_server_url 	= $license_server_url; 
		$this->version 				= $version; 
		$this->text_domain			= $text_domain; 
		$this->plugin_file			= plugin_basename( $plugin_file ); 
		$this->update_interval		= $update_interval; 
		$this->debug 				= defined( 'WP_DEBUG' ) && WP_DEBUG ? true : $debug; 
		$this->slug 				= empty( $slug ) ? basename( $this->plugin_file, '.php' ) : $slug; 
		$this->option_name 			= $this->slug . '_license_manager'; 
		$this->domain 				= str_ireplace( array( 'http://', 'https://' ), '', home_url() );
		$this->license_details 		= get_option( $this->option_name ); 
		$this->license_manager_url 	= esc_url( admin_url( 'options-general.php?page='. $this->slug . '_license_manager' ) ); 

		// Get the license server host 
		$this->license_server_host 	= @parse_url( $this->license_server_url, PHP_URL_HOST ); 

		// Don't run the license activation code if running on local host.
		$whitelist = apply_filters( 'wcv_localhost_whitelist', array( '127.0.0.1', '::1' ) );

    	if ( in_array( $_SERVER[ 'REMOTE_ADDR' ], $whitelist ) ){ 

    		add_action( 'admin_notices', array( $this, 'license_localhost' ) ); 

    	} else { 

			// Initilize wp-admin interfaces
			add_action( 'admin_init', 								array( $this, 'check_install' ) );
			add_action( 'admin_menu', 								array( $this, 'add_license_menu' ) ); 
			add_action( 'admin_init', 								array( $this, 'add_license_settings' ) ); 

			// Internal methods 		
			add_filter( 'http_request_host_is_external', 			array( $this, 'fix_update_host' ), 10, 2 ); 

			// Only allow updates if they have a valid license key need 
			if ( 'active' === $this->license_details[ 'license_status' ] ){ 

				add_filter( 'pre_set_site_transient_update_plugins',	array( $this, 'update_check') ); 
				add_filter( 'plugins_api', 								array( $this, 'add_plugin_info' ), 10, 3 ); 
				add_filter( 'plugin_row_meta', 							array( $this, 'check_for_update_link' ), 10, 2 ); 
				
				add_action( 'admin_init', 								array( $this, 'process_manual_update_check' ) ); 
				add_action( 'all_admin_notices',						array( $this, 'output_manual_update_check_result' ) ); 

			} 
		} 

	} // __construct()


	/**
	 * Check the installation and configure any defaults that are required 
	 * @since 1.0.0 
	 * @todo move this to a plugin activation hook 
	 */
	public function check_install(){ 

		// Set defaults 
		if ( empty( $this->license_details ) ) { 
			$default_license_options = array( 
				'license_status'		=> 'inactive', 
				'license_key'			=> '', 
				'license_expires'		=> '', 
				'deactivate_license'	=> '', 
				'current_version'		=> $this->version, 
			); 

			update_option( $this->option_name, $default_license_options ); 

		}

		if ( $this->license_details == '' || $this->license_details[ 'license_status' ] == 'inactive' || $this->license_details[ 'license_status' ] == 'deactivated' || $this->license_details[ 'license_status' ] == 'expired' ){ 
			add_action( 'admin_notices', array( $this, 'license_inactive' ) );  
		}

	} // check_install() 


	/**
	 * Display a license inactive notice 
	 */
	public function license_inactive(){ 

		if ( ! current_user_can( 'manage_options' ) ) return; 
		
  	
		echo '<div class="error notice is-dismissible"><p>'. 
			sprintf( __( 'The %s license key has not been activated, so you will be unable to get automatic updates or support! %sClick here%s to activate your support and updates license key.', $text_domain ), $this->plugin_nice_name, '<a href="' . $this->license_manager_url . '">', '</a>' ) . 
		'</p></div>'; 

	} // license_inactive() 


	/**
	 * Display the localhost detection notice 
	 */
	public function license_localhost(){ 

		if ( ! current_user_can( 'manage_options' ) ) return; 	

		echo '<div class="error notice is-dismissible"><p>'. sprintf( __( '%s has detected you are running on your localhost. The license activation system has been disabled. ', $text_domain ), $this->plugin_nice_name ) . '</p></div>'; 

	} // license_localhost() 

	/**
	 * Check for updates with the license server 
	 * @since 1.0.0 
	 * @param object transient object from the update api 
	 * @return object transient object possibly modified 
	 */
	public function update_check( $transient ){ 

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$server_response = $this->server_request( 'check_update' ); 

		if ( $this->check_license( $server_response ) ){ 

			if ( isset( $server_response ) && is_object( $server_response->software_details ) ) { 

				$plugin_update_info = $server_response->software_details; 

				if ( isset( $plugin_update_info->new_version ) ){ 
					if ( version_compare( $plugin_update_info->new_version, $this->version, '>' ) ){ 
						$transient->response[ $this->plugin_file ] = $plugin_update_info; 
					}

				}

			}
		} 

		return $transient; 

	} // update_check() 

	/**
	 * Add the plugin information to the WordPress Update API 
	 *
	 * @since 1.0.0 
	 * @param bool|object The result object. Default false.
	 * @param string The type of information being requested from the Plugin Install API.
	 * @param object Plugin API arguments. 
	 * @return object  
	 */
	public function add_plugin_info( $result, $action = null, $args = null ){ 

		// Is this about our plugin? 
		if ( isset( $args->slug ) ){ 

			if ( $args->slug != $this->slug ){ 
				return $result; 		
			}

		} else { 
			return $result; 
		}

		$server_response = $this->server_request();  
		$plugin_update_info = $server_response->software_details; 

		if ( isset( $plugin_update_info ) && is_object( $plugin_update_info ) && $plugin_update_info !== false ){ 
			return $plugin_update_info;
		}

		return $result; 
		
	} // add_plugin_info() 

	/**
	 * Send a request to the server 
	 * @param $action string activate|deactivate|check_update
	 */
	public function server_request( $action = 'check_update' ){ 

		$request_info[ 'slug' ] 				= $this->slug; 
		$request_info[ 'license_key' ] 			= $this->license_details[ 'license_key' ]; 
		$request_info[ 'domain' ]				= $this->domain; 
		$request_info[ 'version' ]				= $this->version; 

		// Allow filtering the request info for plugins 
		$request_info = apply_filters( 'wcsl_request_info_' . $this->slug, $request_info ); 

		// Build the server url api end point fix url build to support the WordPress API 
		$server_request_url = esc_url_raw( $this->license_server_url . 'wp-json/wpsls/v1/' . $action . '?' . http_build_query( $request_info ) ); 

		// Options to parse the wp_safe_remote_get() call 
		$request_options = array(  'timeout' => 20 ); 

		// Allow filtering the request options
		$request_options = apply_filters( 'wcsl_request_options_' . $this->slug, $request_options ); 

		// Query the license server 
		$response = wp_safe_remote_get( $server_request_url, $request_options ); 

		// Validate that the response is valid not what the response is 
		$result = $this->validate_response( $response ); 
		
		// Check if there is an error and display it if there is one, otherwise process the response. 
		if ( ! is_wp_error( $result ) ){ 

			$response_body = json_decode( wp_remote_retrieve_body( $response ) ); 

			// Check the status of the response 
			$continue = $this->check_response_status( $response_body ); 

			if ( $continue ){ 
				return $response_body;
			} 

		} else { 

			// Display the error message in admin 
			add_settings_error( 
				 $this->option_name, 
				 esc_attr( 'settings_updated' ),
				 $result->get_error_message(), 
				 'error'
			); 

			// Return null to halt the execution 
			return null; 

		} 

	} // server_request() 


	/**
	 * Validate the license server response to ensure its valid response not what the response is 
	 * 
	 * @since 1.0.0 
	 * @access public 
	 * @param WP_Error | Array The response or WP_Error 
	 */
	public function validate_response( $response ){ 

		if ( !empty ( $response ) ) { 

			// Can't talk to the server at all, output the error 
			if ( is_wp_error( $response ) ){ 
				return new WP_Error( $response->get_error_code(), sprintf( __( 'HTTP Error: %s', $this->text_domain ), $response->get_error_message() ) ); 
			}

			// There was a problem with the initial request 
			if ( !isset( $response[ 'response'][ 'code'] ) ){ 
				return new WP_Error( 'wcsl_no_response_code', __( 'wp_safe_remote_get() returned an unexpected result.', $this->text_domain ) ); 
			}

			// There is a validation error on the server side, output the problem 
			if ( $response[ 'response'][ 'code'] == 400 ) { 

				$body = json_decode( $response[ 'body' ] );

				foreach ( $body->data->params as $param => $message ) {
					return new WP_Error( 'wcsl_validation_failed', sprintf( __( 'There was a problem with your license: %s', $this->text_domain ), $message ) ); 
				}
				
			}

			// The server is broken 
			if ( $response[ 'response'][ 'code'] == 500 ) { 
				return new WP_Error( 'wcsl_internal_server_error', sprintf( __( 'There was a problem with the license server: HTTP response code is : %s', $this->text_domain ), $response['response']['code' ] ) ); 
			}

			if ( $response[ 'response'][ 'code'] !== 200 ){ 
				return new WP_Error( 'wcsl_unexpected_response_code', sprintf( __( 'HTTP response code is : % s, expecting ( 200 )', $this->text_domain ), $response['response']['code'] ) ); 
			}

			if ( empty( $response[ 'body' ] ) ){ 
				return new WP_Error( 'wcsl_no_response', __( 'The server returned no response.', $this->text_domain ) ); 
			}

			return true; 

		} 

	} // validate_response() 


	/**
	 * Validate the license server response to ensure its valid response not what the response is 
	 * 
	 * @since 1.0.0 
	 * @access public 
	 * @param object $response_body
	 */
	public function check_response_status( $response_body ){ 

		if ( is_object( $response_body ) && ! empty( $response_body ) ) { 

			$license_status_types 	= $this->license_status_types(); 
			$status 				= $response_body->status; 

			return ( array_key_exists( $status, $license_status_types ) ) ? true : false; 
		} 

		return false; 

	}  // check_response_status() 


	/**
	 * Validate the license is active and if not, set the status and return false
	 * 
	 * @since 1.0.0 
	 * @access public 
	 * @param object $response_body
	 */
	 public function check_license( $response_body ){ 
	 	
	 	$status = $response_body->status; 

	 	if ( 'active' === $status || 'expiring' === $status ){ 
	 		return true; 
	 	} 

 		$this->set_license_status( $status ); 
 		$this->set_license_expires( $response_body->expires ); 
 		$this->save(); 

 		return false; 

	 } // check_license() 


	/**
	 * Add a check for update link on the plugins page. You can change the link with the supplied filter. 
	 * returning an empty string will disable this link   
	 * 
	 * @since 1.0.0 
	 * @access public 
	 * @param array  $links The array having default links for the plugin.
	 * @param string $file The name of the plugin file.
	 */
	public function check_for_update_link( $links, $file ){ 

		// Only modify the plugin meta for our plugin 
		if ( $file == $this->plugin_file && current_user_can( 'update_plugins' ) ){ 

			$update_link_url = wp_nonce_url( 
				add_query_arg( array( 
						'wcsl_check_for_update' => 1, 
						'wcsl_slug' => $this->slug 
					), 
					self_admin_url( 'plugins.php' ) 
				), 
				'wcsl_check_for_update'
			); 

			$update_link_text = apply_filters( 'wcsl_update_link_text_'. $this->slug, __( 'Check for updates', $this->text_domain ) ); 

			if ( !empty ( $update_link_text ) ){ 
				$links[] = sprintf( '<a href="%s">%s</a>', esc_attr( $update_link_url ), $update_link_text ); 
			}

		}

		return $links; 

	} // check_for_update_link() 

	/**
	 * Process the manual check for update if check for update is clicked on the plugins page. 
	 * 
	 * @since 1.0.0 
	 * @access public 
	 */
	public function process_manual_update_check(){ 

		if ( isset( $_GET[ 'wcsl_check_for_update' ], $_GET[ 'wcsl_slug' ]) && $_GET[ 'wcsl_slug' ] == $this->slug && current_user_can( 'update_plugins') && check_admin_referer( 'wcsl_check_for_update' ) ){ 

			// Check for updates
			$server_response = $this->server_request();  

			if ( $this->check_license( $server_response ) ){ 

				$plugin_update_info = $server_response->software_details; 
				
				if ( isset( $plugin_update_info ) && is_object( $plugin_update_info ) 	){ 
						
					if ( version_compare( ( string ) $plugin_update_info->new_version, ( string ) $this->version, '>' ) ){ 

						$update_available = true; 

					} else { 

						$update_available = false; 
					}

				} else { 

					$update_available = false; 
				}

				$status = ( $update_available == null ) ? 'no' : 'yes'; 

				wp_redirect( add_query_arg( 
						array( 
							'wcsl_update_check_result' => $status, 
							'wcsl_slug'	=> $this->slug, 
						), 
						self_admin_url('plugins.php')
					)
				); 
			}
		}

	} // process_manual_update_check() 


	/**
	 * Out the results of the manual check 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function output_manual_update_check_result(){ 

		if ( isset( $_GET[ 'wcsl_update_check_result'], $_GET[ 'wcsl_slug' ] ) && ( $_GET[ 'wcsl_slug'] == $this->slug ) ){ 

			$check_result = $_GET[ 'wcsl_update_check_result' ]; 

			switch ( $check_result ) {
				case 'no':
					$admin_notice = __( 'This plugin is up to date. ', $this->text_domain ); 
					break; 
				case 'yes': 
					$admin_notice = sprintf( __( 'An update is available for %s.', $this->text_domain ), $this->plugin_nice_name ); 
					break;
				default:
					$admin_notice = __( 'Unknown update status.', $this->text_domain ); 
					break;
			}

			printf( '<div class="updated notice is-dismissible"><p>%s</p></div>', apply_filters( 'wcsl_manual_check_message_result_' . $this->slug, $admin_notice, $check_result ) ); 
		}

	} // output_manual_update_check_result() 


	/**
	 * This is for internal purposes to ensure that during development the HTTP requests go through 
	 * due to security features in the WordPress HTTP API. 
	 * 
	 * Source for this solution: Plugin Update Checker Library 3387.1 by Janis Elsts 
	 *
	 * @since 1.0.0 
	 * @access public 
	 * @param bool $allow
	 * @param string $host
	 * @return bool
	 */
	private function fix_update_host( $allow, $host ){ 

		if ( strtolower( $host) === strtolower( $this->license_server_url ) ){ 
			return true; 
		}
		return $allow; 

	} //fix_update_host() 


	/**
	 * Class logger so that we can keep our debug and logging information cleaner 
	 *
	 * @since 1.0.0 
	 * @access public 
	 * @param mixed - the data to go to the error log 
	 */
	private function log( $data ){ 

		if ( is_array( $data ) || is_object( $data ) ) { 
			error_log( __CLASS__ . ' : ' . print_r( $data, true ) ); 
		} else { 
			error_log( __CLASS__ . ' : ' . $data );
		}

	} // log() 

	
	/**
	 * Add the admin menu to the dashboard 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function add_license_menu(){ 

		$page = add_options_page( 
			sprintf( __( '%s License', $this->text_domain ), $this->plugin_nice_name ),
			sprintf( __( '%s License', $this->text_domain ), $this->plugin_nice_name ),
			'manage_options', 
			$this->slug . '_license_manager', 
			array( $this, 'load_license_page' )
		); 

	} // add_license_menu()

	/**
	 * Load settings for the admin screens so users can input their license key 
	 *
	 * Utilizes the WordPress Settings API to implment this
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function add_license_settings(){ 

		register_setting( $this->option_name, $this->option_name, array( $this, 'validate_license') ); 

		// License key section 
		add_settings_section( 
			$this->slug . '_license_activation', 
			__( 'License Activation', $this->text_domain ), 
			array( $this, 'license_activation_section_callback' ), 
			$this->option_name
		 ); 

		// License key 
		add_settings_field(
			'license_key',  
			__( 'License key', $this->text_domain ), 
			array( $this, 'license_key_field' ), 
			$this->option_name, 
			$this->slug . '_license_activation'
		); 

		// License status 
		add_settings_field(
			'license_status',  
			__( 'License Status', $this->text_domain ), 
			array( $this, 'license_status_field' ), 
			$this->option_name, 
			$this->slug . '_license_activation'
		); 

		// License expires 
		add_settings_field(
			'license_expires',  
			__( 'License Expires', $this->text_domain ), 
			array( $this, 'license_expires_field' ), 
			$this->option_name, 
			$this->slug . '_license_activation'
		); 

		// Deactivate license checkbox 
		add_settings_field(
			'deactivate_license',  
			__( 'Deactivate license', $this->text_domain ), 
			array( $this, 'license_deactivate_field' ), 
			$this->option_name, 
			$this->slug . '_license_activation'
		); 

		// This is a staging server checkbox 
		// The plugin can be activated on the main domain and a staging server, 
		// Requires: MUST be a subdomain of your live site 


	} // add_license_page() 

	/**
	 * License page output call back function 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function load_license_page(){ 
	?>
	<div class='wrap'>
		<?php screen_icon(); ?>
		<h2><?php printf( __( '%s License Manager', $this->text_domain ), $this->plugin_nice_name ) ?></h2>
		<form action='options.php' method='post'>
			<div class="main">
				<div class="notice update">
				<?php printf( __( 'Please Note: If your license is active on another website you will need to deactivate this in your wcvendors.com my downloads page before being able to activate it on this site.  IMPORTANT:  If this is a development or a staging site dont activate your license.  Your license should ONLY be activated on the LIVE WEBSITE you use Pro on.', $this->text_domain ), $this->plugin_nice_name ); ?>
				</div>

				<?php //settings_errors(); ?>

				<?php 
					settings_fields( $this->option_name ); 
					do_settings_sections( $this->option_name );
					submit_button( __( 'Save Changes', $this->text_domain ) );
				?>
			</div>
		</form>
	</div>

	<?php
	} // license_page() 

	/**
	 * License activation settings section callback 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function license_activation_section_callback(){ 

		echo '<p>'.__( 'Please enter your license key to activate automatic updates and verify your support.', $this->text_domain ) .'</p>'; 

	} // license_activation_section_callback () 

	/**
	 * License key field callback
	 *
	 * @since 1.0.0
	 * @access public 
	 */
	public function license_key_field( ){ 
		$value 			= ( isset( $this->license_details[ 'license_key' ] ) ) ? $this->license_details[ 'license_key' ] : ''; 
		echo '<input type="text" id="license_key" name="' . $this->option_name . '[license_key]" value="' . $value . '" />'; 

	} // license_key_field() 

	/**
	 * License acivated field 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function license_status_field(){ 

		// $activated 		= $this->license_details[ 'license_status' ]; 

		$license_labels = $this->license_status_types();

		_e( $license_labels[ $this->license_details[ 'license_status' ] ] ); 

	} // license_status_field() 

	/**
	 * License acivated field 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function license_expires_field(){ 
		echo $this->license_details[ 'license_expires' ]; 
	}	

	/**
	 * License deactivate checkbox 
	 *
	 * @since 1.0.0 
	 * @access public 
	 */
	public function license_deactivate_field(){ 

		echo '<input type="checkbox" id="deactivate_license" name="' . $this->option_name . '[deactivate_license]" />';

	} // license_deactivate_field() 


	/**
	 * Validate the license key information sent from the form. 
	 *
	 * @since 1.0.0 
	 * @access public 
	 * @param array $input the input passed from the request 
	 */
	public function validate_license( $input ){ 

		$options 	= $this->license_details; 
		$type 		= null; 
		$message 	= null; 
		$expires 	= ''; 

		foreach ( $options as $key => $value ) {
				
    		if ( 'license_key' === $key ){ 

    			if ( 'active' === $this->get_license_status() ) continue; 

    			if ( ! array_key_exists( 'deactivate_license', $input ) || 'deactivated' !== $this->get_license_status() ) {

    				$this->license_details[ 'license_key' ] = $input[ $key ]; 
					$response 								= $this->server_request( 'activate' ); 

					if ( $response !== null ){ 

						if ( $this->check_response_status( $response ) ){ 
							
							$options[ $key ] 				= $input[ $key ]; 
							$options[ 'license_status' ] 	= $response->status; 
							$options[ 'license_expires' ] 	= $response->expires; 
						
							if ( $response->status === 'valid' ||  $response->status === 'active' ){ 
								$type 							= 'updated'; 
								$message 						= __( 'License activated.',  $this->text_domain ); 
							} else{ 
								$type 							= 'error'; 
								$message 						= $response->message; 
							}

						} else { 

							$type 		= 'error'; 
							$message 	=  __( 'Invalid License', $this->text_domain ); 
						}

						add_settings_error( 
							 $this->option_name, 
							 esc_attr( 'settings_updated' ),
							 $message, 
							 $type
						); 

						$options[ $key ] = $input[ $key ];
					} 
    			} 

    			$options[ $key ] = $input[ $key ];

    		} elseif ( array_key_exists( $key, $input ) && 'deactivate_license' === $key ) { 

    			$response = $this->server_request( 'deactivate' );  

    			if ( $response !== null ){ 

    				if ( $this->check_response_status( $response ) ){ 
	    				$options[ $key ] 				= $input[ $key ]; 
	    				$options[ 'license_status' ] 	= $response->status; 
	    				$options[ 'license_expires' ] 	= $response->expires; 
	    				$type 							= 'updated'; 
						$message 						= __( 'License Deactivated', $this->text_domain ); 

	    			} else { 

	    				$type 		= 'updated'; 
						$message 	= __( 'Unable to deactivate license. Please deactivate on the store.', $this->text_domain ); 
	
	    			}

	    			add_settings_error( 
							 $this->option_name, 
							 esc_attr( 'settings_updated' ),
							 $message, 
							 $type
						); 
    			}

    		} elseif( 'license_status' === $key ){ 

    			if ( empty( $options[ 'license_status' ] ) ) { 
					$options[ 'license_status' ] = 'inactive'; 
    			} else { 
    				$options[ 'license_status' ] = $options[ 'license_status' ]; 
    			}
    			
    		} elseif( 'license_expires' === $key ){ 

    			if ( empty( $options[ 'license_expires' ] ) ) { 
					$options[ 'license_expires' ] =  '';
    			} else { 
    				$options[ 'license_expires' ] = date( 'Y-m-d', strtotime( $options[ 'license_expires' ] ) ); 
    			}
    			
    		}

		}

		return $options; 

	} // validate_license() 	

	/**
	 * The available license status types
	 * 
	 * @since 1.0.0 
	 * @access public 
	 */
	public function license_status_types(){ 

		return apply_filters( 'wcsl_license_status_types',  array( 
				'valid'				=> __( 'Valid', $this->text_domain ), 
				'deactivated'		=> __( 'Deactivated', $this->text_domain ), 
				'max_activations'	=> __( 'Max Activations reached', $this->text_domain ), 
				'invalid'			=> __( 'Invalid', $this->text_domain ), 
				'inactive'			=> __( 'Inactive', $this->text_domain ), 
				'active'			=> __( 'Active', $this->text_domain ), 
				'expiring'			=> __( 'Expiring', $this->text_domain ), 
				'expired'			=> __( 'Expired', $this->text_domain )
			)
		); 

	} // software_types() 


	/**
	 *--------------------------------------------------------------------------
	 * Getters
	 *--------------------------------------------------------------------------
	 * 
	 * Methods for getting object properties 
	 * 
	 */

	/**
	 * Get the license setatus
	 * @since 1.0.0 
	 * @access public   
	 */
	public function get_license_status() { 
		
		return $this->license_details[ 'license_status' ];  

	} // get_license_status()

	/**
	 * Get the license key
	 * @since 1.0.0 
	 * @access public   
	 */
	public function get_license_key() { 
		
		return $this->license_details[ 'license_key(' ];  

	} // get_license_key() 


	/**
	 * Get the license expiry
	 * @since 1.0.0 
	 * @access public   
	 */
	public function get_license_expires() { 

		return $this->license_details[ 'license_expires' ];  

	} // get_license_expires()


	/**
	 *--------------------------------------------------------------------------
	 * Setters 
	 *--------------------------------------------------------------------------
	 * 
	 * Methods to set the object properties for this instance. This does not 
	 * interact with the database. 
	 * 
	 */

	/**
	 * Set the license status 
	 * @since 1.0.0 
	 * @access public   
	 * @param string $license_status 
	 */
	public function set_license_status( $license_status ) {  

		$this->license_details[ 'license_status' ] = $license_status ;  

	} // set_license_status() 

	/**
	 * Set the license key 
	 * @since 1.0.0 
	 * @access public   
	 * @param string $license_key 
	 */
	public function set_license_key( $license_key ) { 
		
		$this->license_details[ 'license_key' ]  = $license_key; 

	} // set_license_key() 

	/**
	 * Set the license expires 
	 * @since 1.0.0 
	 * @access public   
	 * @param string $license_expires
	 */
	public function set_license_expires( $license_expires ) { 
	 	
	 	$this->license_details[ 'license_expires' ] = $license_expires;   

	 } // set_license_expires() 

	 public function save(){ 
	 	
	 	update_option( $this->option_name, $this->license_details ); 

	 } // save() 


} // WC_Software_License_Client 

endif; 
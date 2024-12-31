<?php

class Shorthand_Admin {
	const NONCE = 'shorthand-options';

	private static $initiated = FALSE;
	private static $notices = [];
	private static $allowed
		= [
			'a'      => [
				'href'  => TRUE,
				'title' => TRUE,
			],
			'b'      => [],
			'code'   => [],
			'del'    => [
				'datetime' => TRUE,
			],
			'em'     => [],
			'i'      => [],
			'q'      => [
				'cite' => TRUE,
			],
			'strike' => [],
			'strong' => [],
		];

	/**
	 * Return an array of HTML elements that are allowed in a notice.
	 *
	 * @return array
	 */
	public static function get_notice_kses_allowed_elements() {
		return self::$allowed;
	}

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}

		if ( isset( $_POST['action'] ) && $_POST['action'] == 'enter-key' ) {
			self::enter_api_key();
		}
	}

	public static function init_hooks() {
		self::$initiated = TRUE;

		add_action( 'admin_init', array( 'Shorthand_Admin', 'admin_init' ) );
		add_action( 'admin_notices', array( 'Shorthand_Admin', 'display_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'shorthand-connect.php' ), array('Shorthand_Admin', 'admin_plugin_settings_link') );
	}

	public static function admin_init() {
		// Redirect to API key page.
		if (isset($_GET['page']) && ( 'shorthand-options' !== $_GET['page'] ) && ! Shorthand::get_api_key() ) {
			$admin_url = self::get_page_url( 'init' );
			//wp_redirect( $admin_url );
		}

		/*if ( get_option( 'Activated_Shorthand' ) ) {
			delete_option( 'Activated_Shorthand' );
			if ( ! headers_sent() ) {
				$admin_url = self::get_page_url( 'init' );
				wp_redirect( $admin_url );
			}
		}*/

		load_plugin_textdomain( 'shorthand-connect' );

		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Shorthand', 'shorthand-connect' ),
				esc_html( sprintf( __( 'For more information see %s', 'shorthand-connect' ), '<a href="https://shorthand.com/legal/privacy-policy/" _target="_blank">Shorthand Privacy Policy</a>' ) )
			);
		}
	}

	public static function get_page_url( $page = 'config', $navigation = 'token' ) {

		$args = array(
			'page' => 'shorthand-options',
			'navigation' => $navigation,
		);

		if ( $page === 'init' ) {
			$args['view'] = 'start';
		}

//		return add_query_arg( $args, menu_page_url( 'shorthand-options', false ) );
		return add_query_arg( $args, admin_url('options-general.php') );
	}

	public static function display_notice() {
		global $hook_suffix;

		if ( in_array( $hook_suffix, [ 'jetpack_page_shorthand-options', 'settings_page_shorthand-options' ] ) ) {
			// This page manages the notices and puts them inline where they make sense.
			return;
		}

		if ( ( 'plugins.php' === $hook_suffix || ( ( 'edit.php' === $hook_suffix ) && isset($_GET['post_type']) && 'shorthand_story' === $_GET['post_type'] ) ) && ! Shorthand::get_api_key() ) {
			// Show the "Set Up Shorthand" banner on the comments and plugin pages
			// if no API key has been set.
			self::display_api_key_warning();
		}
	}

	public static function display_api_key_warning() {
		Shorthand::view( 'notice', array( 'type' => 'plugin' ) );
	}

	public static function admin_plugin_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( self::get_page_url() ) . '">' . __( 'Settings', 'shorthand-connect' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Validates a JSON string.
	 *
	 * This function attempts to decode a JSON string. If the JSON is invalid,
	 * it returns `false`. If the JSON is valid, it returns the original JSON string.
	 *
	 * @param string $json_string The JSON string to validate.
	 *
	 * @return string|false The original JSON string if valid, or `false` if invalid.
	 */
	public static function validate_json( $json_string ) {
		// Try to decode the JSON data. If it fails, the JSON is invalid.
		$json_data = json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// The JSON is invalid.
			return false;
		}

		// Return the original JSON string if it's valid.
		return $json_string;
	}
}

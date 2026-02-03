<?php

class Shorthand {
	const API_HOST                          = 'rest.akismet.com';
	const API_PORT                          = 80;
	const MAX_DELAY_BEFORE_MODERATION_EMAIL = 86400; // One day in seconds

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
		self::$initiated = true;
	}

	public static function get_api_key() {
		return apply_filters( 'shorthand_get_api_key', defined( 'SHORTHAND_API_KEY' ) ? constant( 'SHORTHAND_API_KEY' ) : get_option( 'sh_v2_token' ) );
	}

	public static function view( $name, array $args = array() ) {
		$args = apply_filters( 'shorthand_view_arguments', $args, $name );

		foreach ( $args as $key => $val ) {
			$$key = $val;
		}

		load_plugin_textdomain( 'shorthand-connect' );

		$file = SHORTHAND__PLUGIN_DIR . 'views/' . $name . '.php';

		include_once $file;
	}

	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 *
	 * @static
	 */
	public static function plugin_activation() {
		if ( version_compare( $GLOBALS['wp_version'], SHORTHAND__MINIMUM_WP_VERSION, '<' ) ) {
			load_plugin_textdomain( 'shorthand-connect' );

			$message = '<strong>' . sprintf( esc_html__( 'Shorthand Connect %1$s requires WordPress %2$s or higher.', 'shorthand-connect' ), SHORTHAND_VERSION, SHORTHAND__MINIMUM_WP_VERSION ) . '</strong> ' . sprintf( __( 'Please <a href="%1$s">upgrade WordPress</a> to a current version, or <a href="%2$s">downgrade to version 1.3.31 of the Shorthand Connect plugin</a>.', 'shorthand-connect' ), 'https://codex.wordpress.org/Upgrading_WordPress', 'https://wordpress.org/plugins/shorthand-connect/download' );

		} elseif ( ! empty( sanitize_url( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) && false !== strpos( sanitize_url( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '/wp-admin/plugins.php' ) ) {
			add_option( 'Activated_Shorthand', true );
		}
	}
}

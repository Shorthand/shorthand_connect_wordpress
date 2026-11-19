<?php
/**
 * WordPress REST endpoints used by the Shorthand admin picker.
 *
 * @package Shorthand_Connect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides authenticated Story and Project data to the WordPress admin UI.
 */
class Shorthand_Connect_WP_API {
	/**
	 * Registers the REST initialisation hook.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers admin picker routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'shorthand_connect/v1',
			'/stories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_stories' ),
				'permission_callback' => array( __CLASS__, 'can_read_stories' ),
			)
		);
		register_rest_route(
			'shorthand_connect/v1',
			'/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_projects' ),
				'permission_callback' => array( __CLASS__, 'can_manage_projects' ),
			)
		);
		register_rest_route(
			'shorthand_connect/v1',
			'/projects/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import_project' ),
				'permission_callback' => array( __CLASS__, 'can_manage_projects' ),
				'args'                => array(
					'project_id'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_status'          => array(
						'default'           => 'draft',
						'type'              => 'string',
						'enum'              => array( 'draft', 'publish' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'placeholder_post_id' => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			'shorthand_connect/v1',
			'/projects/import/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_project_import_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage_projects' ),
			)
		);
	}

	/**
	 * Checks access to standalone Story data.
	 *
	 * @return bool
	 */
	public static function can_read_stories() {
		return current_user_can( 'read' );
	}

	/**
	 * Checks access to Project data and imports.
	 *
	 * @return bool
	 */
	public static function can_manage_projects() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns standalone Stories for the card picker.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_stories( WP_REST_Request $request ) {
		$limit_param  = $request->get_param( 'limit' );
		$cursor_param = $request->get_param( 'cursor' );
		$keyword      = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
		$limit        = $limit_param ? min( 100, max( 1, absint( $limit_param ) ) ) : 50;
		$cursor       = $cursor_param ? base64_encode( wp_json_encode( array( 'updatedAt' => sanitize_text_field( (string) $cursor_param ) ) ) ) : '';
		$stories      = shorthand_api_get_stories( $keyword, $cursor, (string) $limit );
		return rest_ensure_response( $stories );
	}

	/**
	 * Returns available Projects for the card picker.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_projects() {
		$projects = shorthand_api_get_projects();
		return is_wp_error( $projects ) ? $projects : rest_ensure_response( $projects );
	}

	/**
	 * Starts a bounded background Project import.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import_project( WP_REST_Request $request ) {
		$project_id          = sanitize_text_field( (string) $request->get_param( 'project_id' ) );
		$post_status         = sanitize_key( (string) $request->get_param( 'post_status' ) );
		$placeholder_post_id = absint( $request->get_param( 'placeholder_post_id' ) );
		$result              = shorthand_begin_project_import( $project_id, $post_status );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if (
			$placeholder_post_id
			&& 'shorthand_story' === get_post_type( $placeholder_post_id )
			&& 'auto-draft' === get_post_status( $placeholder_post_id )
			&& current_user_can( 'delete_post', $placeholder_post_id )
		) {
			wp_delete_post( $placeholder_post_id, true );
		}
		return rest_ensure_response(
			array(
				'project_id' => $project_id,
				'started'    => true,
			)
		);
	}

	/**
	 * Returns progress for Project imports visible to the current administrator.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_project_import_status() {
		return rest_ensure_response( shorthand_get_project_import_statuses() );
	}
}

Shorthand_Connect_WP_API::init();

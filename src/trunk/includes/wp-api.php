<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shorthand_Connect_WP_API {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('shorthand_connect/v1', 'stories', [
            'methods'  => 'GET',
            'args' => array(),
            'callback' => [__CLASS__, 'handle_request'],
            'permission_callback' => function () {
                return current_user_can('read') && isset($_SERVER['HTTP_X_WP_NONCE']) && wp_verify_nonce(sanitize_text_field( wp_unslash($_SERVER['HTTP_X_WP_NONCE'])), 'wp_rest');
            }
        ]);
    }

    public static function handle_request() {
        //Nonce handled prior to here
        $limit = isset($_GET['limit']) ? sanitize_text_field($_GET['limit']) : 50; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $cursor = isset($_GET['cursor']) ? base64_encode(wp_json_encode(["updatedAt" => sanitize_text_field($_GET['cursor'])])):''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']):''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $stories = shorthand_api_get_stories($keyword, $cursor, $limit);
        return new WP_REST_Response($stories, 200);
    }
}

Shorthand_Connect_WP_API::init();
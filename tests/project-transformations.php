<?php
/**
 * Executable checks for portable Project JSON transformations.
 *
 * Run with: php tests/project-transformations.php
 *
 * @package Shorthand_Connect
 */

// phpcs:disable

class WP_Error {
	private $code;

	public function __construct( $code ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

class Shorthand_Test_Filesystem {
	public function get_contents( $path ) {
		return file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function put_contents( $path, $contents ) {
		return false !== file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}

function __( $message ) {
	return $message;
}

function add_action() {}
function add_filter() {}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function init_wp_filesystem() {}
function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}
function esc_url_raw( $value ) {
	return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
}
function wp_json_encode( $value ) {
	return json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
}

if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}

require dirname( __DIR__ ) . '/src/includes/projects.php';

/**
 * Fails the harness when a condition is false.
 *
 * @param bool   $condition Condition.
 * @param string $message Failure message.
 */
function shorthand_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$head = '<script>window.$shproject.index;</script><script>window.$shproject = {"unchanged":{"brace":"}"},"baseUrl":"old","publishUrl":"old","pageUri":"old"};</script>';
$updated_head = shorthand_update_project_story_context( $head, 'https://wordpress.test/uploads/story/', 'https://wordpress.test/?p=101' );
shorthand_test_assert( ! is_wp_error( $updated_head ), 'valid inline context should transform' );
$parsed_context = shorthand_parse_project_json_assignment( $updated_head, 'window.$shproject', false );
shorthand_test_assert( 'https://wordpress.test/uploads/story/' === $parsed_context['value']['baseUrl'], 'baseUrl should use the local bundle' );
shorthand_test_assert( 'https://wordpress.test/?p=101' === $parsed_context['value']['pageUri'], 'pageUri should use the stable post-ID URL' );
shorthand_test_assert( '}' === $parsed_context['value']['unchanged']['brace'], 'unrelated context should be preserved' );

$test_dir = sys_get_temp_dir() . '/shorthand-project-test-' . bin2hex( random_bytes( 6 ) );
mkdir( $test_dir );
$project_file = $test_dir . '/project.js';
$index = array(
	'pages' => array(
		'story_abc' => array( 'uri' => 'old-a', 'title' => '</script>&"' ),
		'story_def' => array( 'uri' => 'old-b', 'title' => 'B' ),
	),
);
file_put_contents( $project_file, 'window.$shproject.index = ' . json_encode( $index ) . ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$wp_filesystem = new Shorthand_Test_Filesystem();
$urls = array(
	'story_abc' => 'https://wordpress.test/?p=101',
	'story_def' => 'https://wordpress.test/?p=102',
);
$updated_head = shorthand_post_process_project_files( $test_dir, $head, 'https://wordpress.test/uploads/story/', $urls, 'story_abc' );
shorthand_test_assert( ! is_wp_error( $updated_head ), 'valid project.js should transform' );
$rewritten_index = shorthand_parse_project_json_assignment( file_get_contents( $project_file ), 'window.$shproject.index', true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
shorthand_test_assert( $urls['story_def'] === $rewritten_index['value']['pages']['story_def']['uri'], 'index URLs should be rewritten by Story ID' );
shorthand_test_assert( false === strpos( file_get_contents( $project_file ), '</script>' ), 'JSON characters should be safely encoded' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

$unexpected_urls = $urls;
$unexpected_urls['story_extra'] = 'https://wordpress.test/?p=103';
$result = shorthand_post_process_project_files( $test_dir, $head, 'https://wordpress.test/uploads/story/', $unexpected_urls, 'story_abc' );
shorthand_test_assert( is_wp_error( $result ), 'an unexpected Story set should fail closed' );

$result = shorthand_update_project_story_context( '<script>window.$shproject = {bad};</script>', 'https://wordpress.test/uploads/story/', $urls['story_abc'] );
shorthand_test_assert( is_wp_error( $result ), 'malformed inline JSON should fail closed' );

unlink( $project_file );
rmdir( $test_dir );
echo "Project transformation checks passed.\n";

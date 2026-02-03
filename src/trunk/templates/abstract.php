<?php
/**
 * Shorthand abstract template.
 *
 * @package Shorthand Connect
 */

/**
 * Generates a shorthand abstract template for a post.
 *
 * Creates a basic abstract template containing the provided abstract HTML,
a link to the post, and hidden data.
 *
 * @param int    $post_id The ID of the post.
 * @param string $abstract_html The HTML content for the abstract.
 * @param string $data Additional data to be included in a hidden div.
 * @return string The generated abstract HTML.
 */
function shorthand_abstract_template( $post_id, $abstract_html, $data ) {
	$title    = __( 'View the story' );
	$abstract = array(
		'<p>' . $abstract_html . '</p>',
		'<a href="' . get_permalink( $post_id ) . '">' . $title . '</a>',
		'<div style="display:none;">' . $data . '</div>',
	);
	return implode( $abstract );
}

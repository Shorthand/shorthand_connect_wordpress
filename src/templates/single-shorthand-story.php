<?php
/**
 * Single shorthand story template.
 *
 * @package Shorthand Connect
 */

get_header();

// Check to see if there is a password set against the post.
if ( post_password_required( $post->ID ) ) {
	get_shorthand_password_form();
} else {
	while ( have_posts() ) :
		the_post();
		$meta = get_post_meta( $post->ID );
		?>
		<?php echo get_shorthandinfo( $meta, 'story_body' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 
	<div id="extraHTML">
		<?php echo get_shorthandinfo( $meta, 'extra_html' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<style type="text/css">
		<?php echo get_shorthandoption( 'sh_css' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</style>
		<?php
	endwhile;
}
get_footer();

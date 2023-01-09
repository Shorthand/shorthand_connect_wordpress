<?php get_header();

	// Check to see if there is a password set against the post
	if ( post_password_required( $post->ID ) ) {

		 echo get_the_password_form();

	} else {

		while ( have_posts() ) : the_post();
			$meta = get_post_meta($post->ID);
		?>

		<?php echo trim($meta['story_body'][0]); ?>

		<div id="extraHTML">
			<?php echo trim($meta['extra_html'][0]); ?>
		</div>

		<style type="text/css">
			<?php echo get_option('sh_css'); ?>
		</style>

		<?php
		endwhile;

	}

get_footer();

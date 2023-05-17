<?php get_header();
	// Check to see if there is a password set against the post
	if (post_password_required($post->ID)) {
		 echo wp_kses_post(get_the_password_form());
	} else {
		while ( have_posts() ) : the_post();
			$meta = get_post_meta($post->ID);


		// Get the content, but don't output it yet
		$story_body = trim($meta['story_body'][0]);

		//split the Javascript and HTML
		list($html_part, $js_part) = explode('<script>', $story_body);

		// Output and sanitize the HTML part
		echo wp_kses_post($html_part);

		// Output the JavaScript part
		if (!empty($js_part)) {
			echo '<script>' . wp_check_invalid_utf8($js_part) . '</script>';
		}
		?>

		<div id="extraHTML">
			<?php echo wp_kses_post(trim($meta['extra_html'][0])); ?>
		</div>
		<style type="text/css">
			<?php
				echo wp_strip_all_tags(get_option('sh_css')); ?>
		</style>
		<?php
		endwhile;
	}
get_footer();

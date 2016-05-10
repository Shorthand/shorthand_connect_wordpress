<?php get_header(); ?>

		<?php
		while ( have_posts() ) : the_post();
			$meta = get_post_meta($post->ID);
		?>

			<?php echo trim($meta['story_head'][0]); ?>
			<?php echo trim($meta['story_body'][0]); ?>

			<div id="extraHTML">
				<?php echo trim($meta['extra_html'][0]); ?>
			</div>
			
			<style>
				.site {
					margin: 0;
					max-width: none;
				}
				.site-content {
					padding: 0;
				}
				.site-inner {
					max-width: none;
				}
				.site-header {
					max-width: none;
					z-index: 100;
				}
				.site:before {
					width: 0;
				}
			</style>
		<?php
		endwhile;
		?>

<?php get_footer(); ?>

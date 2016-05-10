<?php get_header(); ?>

		<?php
		// Start the loop.
		while ( have_posts() ) : the_post();

			$meta = get_post_meta($post->ID);
			
			$assets_path = get_site_url().substr($meta['story_path'][0], strpos($meta['story_path'][0], '/wp-content/uploads'));
			$body = file_get_contents($meta['story_path'][0].'/component_article.html');
			$head = file_get_contents($meta['story_path'][0].'/component_head.html');

			$body = str_replace('./static/', $assets_path.'/static/', $body);
			$head = str_replace('./static/', $assets_path.'/static/', $head);
			$body = str_replace('./media/', $assets_path.'/media/', $body);
			$head = str_replace('./media/', $assets_path.'/media/', $head);

			echo $head;

			echo $body;

?>
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

			// End of the loop.
		endwhile;
		?>

<?php get_footer(); ?>

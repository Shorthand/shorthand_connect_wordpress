<?php get_header(); ?>

		<?php
		// Start the loop.
		while ( have_posts() ) : the_post();

			$meta = get_post_meta($post->ID);
			
			$upload_path = '/wordpress'.substr($meta['story_path'][0], strpos($meta['story_path'][0], '/wp-content/uploads'));
			$body = file_get_contents($meta['story_path'][0].'/component_article.html');
			$head = file_get_contents($meta['story_path'][0].'/component_head.html');

			$body = str_replace('./static/', $upload_path.'/static/', $body);
			$head = str_replace('./static/', $upload_path.'/static/', $head);
			$body = str_replace('./media/', $upload_path.'/media/', $body);
			$head = str_replace('./media/', $upload_path.'/media/', $head);

			echo $head;

			echo $body;

?>
			<div id="extraHTML">
				<?php echo trim($meta['extra_html'][0]); ?>
			</div>
			<style>
				.site {
					margin: 0;
				}
				.site-content {
					padding: 0;
				}
				.site-inner {
					max-width: none;
				}
			</style>
<?php

			// End of the loop.
		endwhile;
		?>

<?php get_footer(); ?>

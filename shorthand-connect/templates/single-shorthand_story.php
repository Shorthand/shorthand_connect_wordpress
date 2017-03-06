<?php get_header(); ?>

	<?php
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
	?>

<?php get_footer(); ?>

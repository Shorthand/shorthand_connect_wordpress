<?php
/**
 * Template File for Admin Notices
 *
 * This file handles the display of various admin notices in the WordPress
 * admin area.
 *
 * @package Shorthand Connect
 */

// There are "undefined" variables here because they're defined in the code
// that includes this file as a template.
$kses_allow_link   = array(
	'a' => array(
		'href'   => true,
		'target' => true,
	),
);
$kses_allow_strong = array( 'strong' => true );

if ( ! isset( $type ) ) {
	$type = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

$uri = Shorthand_Admin::get_page_url( 'init' )

/*
 * Some notices (plugin, spam-check, spam-check-cron-disabled, alert and usage-limit) are also shown elsewhere in wp-admin, so have different classes applied so that they match the standard WordPress notice format.
 */
?>
<?php if ( 'plugin' === $type ) : ?>

<div class="notice notice-error is-dismissible">
	<p><?php esc_html_e( 'Almost done. Setup API token in', 'shorthand-connect' ); ?> <a alt="(<?php esc_html_e( 'opens Shorthand Connect plugin settings', 'shorthand-connect' ); ?>)" href="<?php echo esc_url( $uri ); ?>"><?php esc_html_e( 'Shorthand settings', 'shorthand-connect' ); ?></a></p>
</div>

<?php elseif ( 'spam-check' === $type ) : ?>
	<?php // This notice is only displayed on edit-comments.php. ?>
	<div class="notice notice-warning">
		<p><strong><?php esc_html_e( 'Akismet has detected a problem.', 'akismet' ); ?></strong></p>
		<p><?php esc_html_e( 'Some comments have not yet been checked for spam by Akismet. They have been temporarily held for moderation and will automatically be rechecked later.', 'akismet' ); ?></p>
		<?php if ( ! empty( $link_text ) ) : ?>
			<p><?php echo wp_kses( $link_text, $kses_allow_link ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

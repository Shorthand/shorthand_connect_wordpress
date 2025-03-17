<?php
/**
 * Admin settings.
 *
 * @package Shorthand Connect
 */

add_action( 'init', 'shorthand_redirect_admin_config_init' );
/* Only redirect for initial setup. */
function shorthand_redirect_admin_config_init(): void {
	if ( isset( $_POST['sh_submit_hidden'] ) &&
		'Y' === $_POST['sh_submit_hidden'] &&
		check_admin_referer( 'sh-update-configuration' ) ) {
		// If there's a token set, use it, if not set it to an empty string.
		$sh_v2_token = isset( $_POST['sh_v2_token'] ) ? sanitize_text_field( $_POST['sh_v2_token'] ) : '';
		update_option( 'sh_v2_token', $sh_v2_token );

		$profile = shorthand_api_get_profile();
		if ( ! $profile && isset( $_GET['page'] ) && ( 'shorthand-options' === $_GET['page'] ) && isset( $_GET['navigation'] ) && ( 'token' !== $_GET['navigation'] ) ) {
			wp_redirect( Shorthand_Admin::get_page_url( 'init' ) );
			exit();
		}
	}
}

/* Options init */
function shorthand_shorthand_options() {
	// Menu links.
	$menu_links = array(
		'token'        => 'API Key',
		'permalink'    => 'Permalinks',
		'css'          => 'Custom CSS',
		'processing'   => 'Post-processing',
		'experimental' => 'Experimental Features',
	);

	// If current = all, then display all.
	$profile        = shorthand_api_get_profile();
	$menu_link_keys = array_keys( $menu_links );
	$current        = isset( $_GET['navigation'] ) ? sanitize_text_field( $_GET['navigation'] ) : $menu_link_keys[0];
	$current_index  = array_search( $current, $menu_link_keys );
	$messages       = array();
	if ( ! $profile ) {
		$messages['notice-error'] = SHORTHAND_CONFIG_STEP1_ERROR;
	}

	foreach ( $menu_link_keys as $key => $value ) {
		if ( $current === $menu_link_keys[ $key ] && ( $key < 4 ) ) {
			$next = Shorthand_Admin::get_page_url( 'init', $menu_link_keys[ $key + 1 ] );
			break;
		}
	}
	if ( $current === $menu_link_keys[4] ) {
		$next = esc_url( Shorthand_Admin::get_page_url() );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( __( 'You do not have sufficient permissions to access this page.' ) ) );
	}

	if ( isset( $_POST['sh_submit_hidden'] ) &&
		'Y' === $_POST['sh_submit_hidden'] &&
		check_admin_referer( 'sh-update-configuration' ) ) {
		// If there's a token set, use it, if not set it to an empty string.
		$sh_v2_token = isset( $_POST['sh_v2_token'] ) ? sanitize_text_field( $_POST['sh_v2_token'] ) : '';
		update_option( 'sh_v2_token', $sh_v2_token );
		shorthand_rewrite_flush();

		// Reload profile after saving it.
		$profile = shorthand_api_get_profile();
		if ( $profile ) {
			$messages['updated'] = SHORTHAND_CONFIG_STEP1_SUCCESS;
			unset( $messages['notice-error'] );
		}
	}

	$v2_token = esc_html( get_option( 'sh_v2_token' ) );

	if ( isset( $_POST['sh_submit_hidden_two'] ) &&
		( 'Y' === $_POST['sh_submit_hidden_two'] ) &&
		check_admin_referer( 'sh-update-configuration' )
	) {
		// Check if there's custom CSS, if there is, use wp_kses_post()
		// to sanitize otherwise set an empty string.
		$sh_css = isset( $_POST['sh_css'] ) ? wp_kses_post( $_POST['sh_css'] ) : '';
		update_option( 'sh_css', $sh_css );
		$messages['updated'] = SHORTHAND_CONFIG_STEP3_SUCCESS;
	}

	if ( isset( $_POST['sh_submit_hidden_three'] ) &&
		( 'Y' === $_POST['sh_submit_hidden_three'] ) &&
		check_admin_referer( 'sh-update-configuration' )
	) {
		// Check if there's custom permalink, if there is, use sanitize_text_field()
		// to sanitize potential HTML and then set an empty string.
		$sh_permalink = isset( $_POST['sh_permalink'] ) ? sanitize_text_field( $_POST['sh_permalink'] ) : '';
		update_option( 'sh_permalink', $sh_permalink );
		$messages['updated'] = SHORTHAND_CONFIG_STEP2_SUCCESS;
		shorthand_rewrite_flush();
	}

	$permalink_structure = esc_html( get_option( 'sh_permalink' ) );
	if ( '' === $permalink_structure ) {
		update_option( 'sh_permalink', 'shorthand_story' );
		$permalink_structure = esc_html( get_option( 'sh_permalink' ) );
	}
	$sh_css = get_option( 'sh_css' );

	if ( isset( $_POST['sh_submit_hidden_four'] ) &&
		( 'Y' === $_POST['sh_submit_hidden_four'] ) &&
		check_admin_referer( 'sh-update-configuration' )
	) {
		$next                = esc_url( Shorthand_Admin::get_page_url() );
		// sh_regex_list may contain <tags> for lookup and processing on import and so may need to include <script> etc; however it is only ever displayed within a text-area value and manually processed.
		$sh_regex_list       = isset( $_POST['sh_regex_list'] ) ?  wp_unslash( $_POST['sh_regex_list'] ) : '';  //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$messages['updated'] = SHORTHAND_CONFIG_STEP4_SUCCESS;
		if ( empty( $sh_regex_list ) ) {
			// Update the option with an empty value if the input is empty
			update_option( 'sh_regex_list', '' );
		} else {
			// Validate if it's a valid JSON without sanitizing
			$sh_regex_list = Shorthand_Admin::validate_json( $sh_regex_list );

			if ( false !== $sh_regex_list ) {
				// Since $sh_regex_list stored as base64, no need to sanitize the JSON,
				// as base64_encode will handle that.
				update_option( 'sh_regex_list', base64_encode( $sh_regex_list ) );
				$messages['updated'] = SHORTHAND_CONFIG_STEP4_SUCCESS;
			} else {
				// Handle invalid JSON error here.
			}
		}
	}

	$sh_regex_list = base64_decode( get_option( 'sh_regex_list' ) );

	// Experimental Settings
	if ( isset( $_POST['sh_submit_hidden_experimental'] ) &&
		'Y' === $_POST['sh_submit_hidden_experimental'] &&
		check_admin_referer( 'sh-update-configuration' ) ) {
		$next                  = esc_url( Shorthand_Admin::get_page_url() );
		$sh_media_cron_offload = isset( $_POST['sh_media_cron_offload'] ) ? filter_var( $_POST['sh_media_cron_offload'], FILTER_VALIDATE_BOOLEAN ) : false;
		$sh_disable_acf        = isset( $_POST['sh_disable_acf'] ) ? filter_var( $_POST['sh_disable_acf'], FILTER_VALIDATE_BOOLEAN ) : false;
		update_option( 'sh_media_cron_offload', $sh_media_cron_offload );
		update_option( 'sh_disable_acf', $sh_disable_acf );
		$messages['updated'] = SHORTHAND_CONFIG_STEP5_SUCCESS;
	}
	$sh_media_cron_offload = filter_var( get_option( 'sh_media_cron_offload' ), FILTER_VALIDATE_BOOLEAN );
	$sh_disable_acf        = filter_var( get_option( 'sh_disable_acf' ), FILTER_VALIDATE_BOOLEAN );

	?>
	<div class="wrap shorthand-options-wrapper py-1">
		<div class="shorthand-nav py-1">
				<?php
				foreach ( $menu_link_keys as $index => $key ) {
					$green_class   = ( $current_index <= $index ) ? ' green' : '';
					$current_class = ( $current_index == $index ) ? ' current' : '';
					$done_class    = ( $current_index > $index ) ? ' done' : '';
					?>
					<?php $badge_text = $current_index > $index ? '&#10003;' : ( $index + 1 ); ?>
			<span class="badge<?php echo esc_attr( $green_class . $current_class . $done_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
			<span class="text<?php echo esc_attr( $green_class . $current_class . $done_class ); ?>"><?php echo esc_html( $menu_links[ $key ] ); ?></span>
					<?php if ( $index < count( $menu_link_keys ) - 1 ) : ?>
			<span class="next px-1 <?php echo esc_attr( $done_class ); ?>">&gt;</span>
			<?php endif; ?>
				<?php } ?>
		</div>

		<div class="shorthand-form-wrapper">
			<form name="form_settings" method="post" action="<?php echo esc_url( $next ); ?>">
			<?php if ( ! empty( $messages ) ) : ?>
				<?php foreach ( $messages as $class => $message ) : ?>
			<div class="notice <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $message ); ?></div>
		<?php endforeach; ?>
			<?php endif; ?>

		<?php if ( in_array( $current, array( $menu_link_keys[0], 'all' ) ) ) : ?>
		<div class="py-1">
			<h2>Shorthand API Key</h2>
			<?php wp_nonce_field( 'sh-update-configuration' ); ?>
			<input type="hidden" name="sh_submit_hidden" value="Y"/>
			<p><label for="sh_v2_token"><?php esc_html_e( 'Your API key provided by Shorthand', 'shorthand-connect' ); ?></label></p>
			<input type="text" id="sh_v2_token" name="sh_v2_token" value="<?php echo esc_attr( $v2_token ); ?>">
		</div>
		<?php endif; ?>

		<?php if ( in_array( $current, array( $menu_link_keys[1], 'all' ) ) ) : ?>
		<div class="py-1">
			<h2>Permalink Structure</h2>
			<?php wp_nonce_field( 'sh-update-configuration' ); ?>
			<input type="hidden" name="sh_submit_hidden_three" value="Y"/>
			<p>
			<p><label for="sh_permalink"><?php esc_html_e( 'Set the permalink structure of Shorthand story URLs', 'shorthand-connect' ); ?></label></p>
			<input type="text" name="sh_permalink" value="<?php echo esc_attr( $permalink_structure ); ?>" size="20">
			<p><?php echo esc_url( get_site_url() ); ?>/<strong><?php echo esc_html( $permalink_structure ); ?></strong>/{STORY_NAME}</p>
		</div>
		<?php endif; ?>

		<?php if ( in_array( $current, array( $menu_link_keys[2], 'all' ) ) ) : ?>
		<div class="py-1">
			<h2>Custom CSS</h2>
			<p>Use theme wide CSS to customise Shorthand Story pages to better suit your theme</p>
			<?php wp_nonce_field( 'sh-update-configuration' ); ?>
			<input type="hidden" name="sh_submit_hidden_two" value="Y"/>
			<textarea rows="10" cols="80" name="sh_css"><?php echo esc_textarea( $sh_css ); ?></textarea>
		</div>
		<?php endif; ?>

		<?php if ( in_array( $current, array( $menu_link_keys[3], 'all' ) ) ) : ?>
		<div class="py-1">
			<h2>Post-processing</h2>
			<p>Create a JSON object of regex queries and replacements.</p>
			<p><em>This Example removes title tags from within the head tag by replacing it with nothing.</em></p>
			<pre><code style="width: 100%;">
{
	"head": [
	{
	&quot;query&quot;: &quot;/&lt;title&gt;(.*?)&lt;\\/title&gt;/&quot;,
	&quot;replace&quot;: &quot;&quot;
	}
	],
	"body": []
}
		</code></pre>
			<?php wp_nonce_field( 'sh-update-configuration' ); ?>
			<input type="hidden" name="sh_submit_hidden_four" value="Y"/>
			<textarea rows="10" cols="80" id="sh_regex_list"
				name="sh_regex_list"><?php echo esc_textarea( $sh_regex_list ); ?></textarea>
			<script>
			let textarea = document.querySelector("textarea#sh_regex_list");

			textarea.addEventListener("keyup", function (event) {
			try {
				JSON.parse(textarea.value);
				textarea.setCustomValidity("");

			} catch (err) {
				if (textarea.value != "") {
				console.log("Invalid JSON");
				textarea.setCustomValidity("Invalid JSON in the Post-processing field");
				} else {
				textarea.setCustomValidity("");
				}
			}

			});
			</script>
		</div>
		<?php endif; ?>

		<?php if ( in_array( $current, array( $menu_link_keys[4], 'all' ) ) ) : ?>
		<div class="py-1">
			<h2>Experimental Features <span class="badge badge-blue">Advanced</span></h2>
			<p>Early access features that are still subject to change.</p>
			<?php wp_nonce_field( 'sh-update-configuration' ); ?>
			<input type="hidden" name="sh_submit_hidden_experimental" value="Y"/>

			<div class="checkbox-container">
			<input type="checkbox" id="sh_media_cron_offload" name="sh_media_cron_offload" <?php echo esc_attr( $sh_media_cron_offload ? 'checked' : '' ); ?> />
			<div class="bordered">
				<label for="sh_media_cron_offload"><strong>Import media assets via cron</strong>
				<p>Assets will be fetched after story save to prevent potential execution timeouts. Media won't be immediately available on save but progress will be updated based on the `media_status` field.</p>
				<p>It is advised that Shorthand Story Posts are saved as a draft first to trigger the cron job prior to public publishing.</p>
				</label>
			</div>
			</div>

			<br/>
			<div class="checkbox-container">
			<input type="checkbox" id="sh_disable_acf" name="sh_disable_acf"
				value="true" <?php echo esc_attr( $sh_disable_acf ? 'checked' : '' ); ?> />
			<div class="bordered"><label for="sh_disable_acf">
				<strong>Disable Advanced Custom Fields</strong>
				<p>Used to prevent any potential issues that could cause the Shorthand Custom Fields to become hidden by Advanced Custom Fields.</p>
			</label></div>
			</div>
			</br>
		</div>
		<?php endif; ?>

				<div class="py-1">
					<hr />
				<?php if ( 'token' !== $current ) : ?>
					<p class="submit">
						<input type="submit" name="Submit" class="button button-primary" value="<?php esc_attr_e( 'Save and continue' ); ?>"/>
						<?php if ( ! in_array( $current, array( $menu_link_keys[0], $menu_link_keys[1] ) ) ) : ?>
						<a href="<?php echo esc_url( $next ); ?>" class="button button-skip button-secondary">Skip</a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="submit">
						<input type="submit" name="Submit" class="button button-primary" value="<?php esc_attr_e( 'Add API key' ); ?>"/>
					</p>
				<p>
					<?php esc_html_e( 'Do not have API key?', 'shorthand-connect' ); ?> <a target="_blank" alt="(<?php esc_html_e( 'opens Shorthand Connect plugin settings', 'shorthand-connect' ); ?>)" href="https://support.shorthand.com/en/articles/62-programmatic-publishing-with-the-shorthand-api"><?php esc_html_e( 'Get one here', 'shorthand-connect' ); ?></a>
				</p>
				<?php endif; ?>
				<div style='clear:both'></div>
				</div>
			</form>

		</div>
	</div>
	</div>

	<?php
}

function registerStyles() {
// Adding styles.
	$css_path = '../css/options.css';
	wp_register_style( 'options_style', plugin_dir_url( __FILE__ ) . $css_path, array(), '1.3', 'all' );
	wp_enqueue_style( 'options_style' );

	$css_path = '../css/options-start.css';
	wp_register_style( 'options_style_start', plugin_dir_url( __FILE__ ) . $css_path, array(), '1.3', 'all' );
	wp_enqueue_style( 'options_style_start' );
}

add_action( 'init', 'registerStyles' );



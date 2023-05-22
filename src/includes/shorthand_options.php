<?php
/* Options */
function shand_shorthand_menu() {
	add_options_page( 'Shorthand Options', 'Shorthand', 'manage_options', 'shorthand-options', 'shand_shorthand_options' );
}
$default_sh_site_css = '
/* START CSS FOR DEFAULT WP THEMES */
.site {
	margin: 0;
	max-width: none;
}
.site-content {
	padding: 0 !important;
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
/* END CSS FOR DEFAULT WP THEMES */
';

// JSON Checker
function validate_json($json_string) {
    // Try to decode the JSON data. If it fails, the JSON is invalid.
    $json_data = json_decode($json_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // The JSON is invalid.
        return false;
    }

    // If the data is valid JSON, re-encode it to ensure it's correctly formatted.
    return json_encode($json_data);
}

function shand_shorthand_options()
{
	global $default_sh_site_css;
	global $serverURL;

	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( esc_html(__( 'You do not have sufficient permissions to access this page.' )) );
	}
	if( isset($_POST['sh_submit_hidden']) && $_POST['sh_submit_hidden'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		//If there's a token set, use it, if not set it to an empty string
		$sh_v2_token = isset($_POST['sh_v2_token']) ? sanitize_text_field($_POST['sh_v2_token']) : '';
		update_option('sh_v2_token', $sh_v2_token);
	}
	
	$v2_token = esc_html(get_option('sh_v2_token'));
	
	if( isset($_POST['sh_submit_hidden_two']) && $_POST['sh_submit_hidden_two'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		//Check if there's custom CSS, if there is, use wp_kses_post() to sanitize otherwise set an empty string
		$sh_css = isset($_POST['sh_css']) ? wp_kses_post($_POST['sh_css']) : '';
		update_option('sh_css', $sh_css);
	}


	// Rather than running a rewrite flush everytime a post is submitted, run it on plugin activate/deactivate
	function shand_rewrite_flush() {
		shand_create_post_type();
		flush_rewrite_rules();
	}
	register_activation_hook( __FILE__, 'shand_rewrite_flush' );
	register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

	if( isset($_POST['sh_submit_hidden_three']) && $_POST['sh_submit_hidden_three'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		//Check if there's custom permalink, if there is, use sanitize_text_field() to sanitize potential HTML and then set an empty string
		$sh_permalink = isset($_POST['sh_permalink']) ? sanitize_text_field($_POST['sh_permalink']) : '';
		update_option('sh_permalink', $sh_permalink);
		shand_rewrite_flush();
	}
	$permalink_structure = esc_html(get_option('sh_permalink'));

	if ($permalink_structure == '') {
		update_option('sh_permalink', 'shorthand_story');
		$permalink_structure = esc_html(get_option('sh_permalink'));
	}
	$sh_css = get_option('sh_css');
	$no_css = false;
	if ($sh_css == '') {
		$no_css = true;
		if (isset($default_site_css)){
			update_option('sh_css', $default_site_css);
		}
		$sh_css = $default_sh_site_css;
	}



	if (isset($_POST['sh_submit_hidden_four']) && $_POST['sh_submit_hidden_four'] == 'Y' && check_admin_referer('sh-update-configuration')) {
		$sh_regex_list = isset($_POST['sh_regex_list']) ? validate_json(wp_unslash($_POST['sh_regex_list'])) : '';
	
		if ($sh_regex_list !== false) {
			update_option('sh_regex_list', base64_encode($sh_regex_list));
		} else {
			// Handle invalid JSON error here.
		}
	}

	$sh_regex_list = base64_decode(get_option('sh_regex_list'));

	// Experimental Settings
	if (isset($_POST['sh_submit_hidden_experimental']) && $_POST['sh_submit_hidden_experimental'] == 'Y' && check_admin_referer('sh-update-configuration')) {
		$sh_media_cron_offload = isset($_POST['sh_media_cron_offload']) ? filter_var($_POST['sh_media_cron_offload'], FILTER_VALIDATE_BOOLEAN) : false;
		$sh_disable_acf = isset($_POST['sh_disable_acf']) ? filter_var($_POST['sh_disable_acf'], FILTER_VALIDATE_BOOLEAN) : false;
		update_option('sh_media_cron_offload', $sh_media_cron_offload);
		update_option('sh_disable_acf', $sh_disable_acf);
	}
  $sh_media_cron_offload = filter_var(get_option('sh_media_cron_offload'), FILTER_VALIDATE_BOOLEAN);
  $sh_disable_acf = filter_var(get_option('sh_disable_acf'), FILTER_VALIDATE_BOOLEAN);
  
	$profile = sh_get_profile();

	ob_start();
	wp_nonce_field( 'sh-update-configuration' );
	$n_once  = ob_get_clean();

?>	
<div class="container">
	<div class="py-1">
	<h1>Shorthand API Configuration</h1>
	<h2>Shorthand Connect Status</h2>
	<form name="form1" method="post">
		<?php echo $n_once ?>
		<input type="hidden" name="sh_submit_hidden" value="Y" />
		<table class="form-table"><tbody>
		<tr class="v2row">
			<th scope="row"><label for="sh_v2_token"><?php esc_html_e("Shorthand Team Token", 'sh-v2-token' ); ?></label></th>
			<td><input type="text" id="sh_v2_token" name="sh_v2_token" value="<?php echo esc_attr($v2_token); ?>" size="28"></td>
		</tr>
		</tbody></table>
		<?php if ($profile) { ?>
		<p class="status">Successfully connected</p>
		<p><strong>Username</strong>: <?php echo esc_html($profile->username); ?></p>
	<?php } else { ?>
		<p class="status warn">Not Connected</p>
	<?php } ?>
	<div style='clear:both'></div>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</p>
	</form>
	</div>


	<div class="py-1">
	<h2>Shorthand Permalink Structure</h2>
		<p>Use this to set the permalink structure of Shorthand story URLs</p>
		<form name="form2" method="post">
			<?php echo $n_once ?>
			<input type="hidden" name="sh_submit_hidden_three" value="Y" />
			<p>
				<?php _e("Permalink structure:", 'sh-permalink-value' ); ?><br /> <?php echo get_site_url(); ?>/<input type="text" name="sh_permalink" value="<?php echo esc_attr($permalink_structure); ?>" size="20">/{STORY_NAME}
			</p>
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
			</p>
		</form>
	</div>

	<div class="py-1">
	<h2>Shorthand Story Page CSS (theme wide CSS)</h2>
		<p>Use this CSS to customise Shorthand Story pages to better suit your theme</p>
		<?php if ($no_css) { ?>
			<p class="status warn">No custom CSS found, using default theme CSS</p>
		<?php }?>
		<form name="form2" method="post">
			<?php echo $n_once ?>
			<input type="hidden" name="sh_submit_hidden_two" value="Y" />
			<textarea rows="10" cols="80" name="sh_css"><?php echo esc_textarea($sh_css); ?></textarea>
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
			</p>
		</form>
		</div>

	<div class="py-1">	
	<h2>Post-processing</h2>
		<p>Use this to create a JSON object of regex queries and replacements.</p>
		<p><em>This Example removes title tags from within the head tag by replacing it with nothing.</em></p>
		<pre><code>
		{
			"head":
			[
			{
				&quot;query&quot;:&quot;/&lt;title.(.*?)&lt;\/title&gt;/&quot;,
				&quot;replace&quot;:&quot;&quot;
			}
			],
			"body":[]
		}
		</code></pre>
		<form name="form2" method="post" onsubmit="padJson()">
			<?php echo $n_once ?>
			<input type="hidden" name="sh_submit_hidden_four" value="Y" />
			<textarea rows="10" cols="80" id="sh_regex_list" name="sh_regex_list"><?php echo stripslashes($sh_regex_list); ?></textarea>
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
			</p>
		</form>
		<script>
			let textarea = document.querySelector("textarea#sh_regex_list");
  
			function padJson() {
				console.log('updated JSON');
				textarea.value = textarea.value.replace(/\\/g, '\\\\');
			}
			
			
			textarea.addEventListener("keyup", function(event) {
				try{
					JSON.parse(textarea.value);
					textarea.setCustomValidity("");
					
				}catch(err){
					if(textarea.value != ""){
						console.log("Invalid JSON");
						textarea.setCustomValidity("Invalid JSON in the Post-processing field");
					}else{
						textarea.setCustomValidity("");
					}
				}
				
			});
		</script>
	</div>
	
	<div class="py-1">
		<h2>Experimental Features</h2>
		<p>Early access features that are still subject to change.</p>
		<form name="form_experimental" method="post">
		<?php echo $n_once ?>
		<input type="hidden" name="sh_submit_hidden_experimental" value="Y" />
		<input type="checkbox" id="sh_media_cron_offload" name="sh_media_cron_offload" value="true" <?php echo esc_attr($sh_media_cron_offload ? 'checked' : '') ?> />
		<label for="sh_media_cron_offload">Import media assets via cron</label>
		<p>Assets will be fetched after story save to prevent potential execution timeouts. Media won't be immediately available on save but progress will be updated based on the `media_status` field.</p>
		<p>It is advised that Shorthand Story Posts are saved as a draft first to trigger the cron job prior to public publishing.</p>
		<br/>
		<input type="checkbox" id="sh_disable_acf" name="sh_disable_acf" value="true" <?php echo esc_attr($sh_disable_acf ? 'checked' : '') ?> />
		<label for="sh_disable_acf">Disable Advanced Custom Fields</label>
		<p>Used to prevent any potential issues that could cause the Shorthand Custom Fields to become hidden by Advanced Custom Fields.</p>
		</br>
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</p>
		</form>
		</div>
	</div>
		</div>
<style>
		.py-1 {
			padding: 1em;
		}
		.bg-white {
			background: white;
		}
		.container {
			max-width: 980px;
		}
		img.grav {
			float: left;
			width:80px;
			margin-right:10px;
		}
		p.status {
			background:#dfd;
			color:green;
			font-weight:bold;
			width:350px;
			clear:left;
			padding:5px;
		}
		p.status.warn {
			background:#ffd;
			color:orange;
		}
		.row-hidden {
			display:none;
		}
		#wpfooter {
			position: unset;
		}
		code {
			font-family: monospace;
			display: inherit;
		}
	</style>


<?php
}

add_action( 'admin_menu', 'shand_shorthand_menu' );

?>
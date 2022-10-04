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

function shand_shorthand_options() {

	global $default_sh_site_css;
	global $serverURL;
	global $serverv2URL;
	global $allowversionswitch;
	global $showServerURL;

	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( esc_html(__( 'You do not have sufficient permissions to access this page.' )) );
	}
	if( isset($_POST['sh_submit_hidden']) && $_POST['sh_submit_hidden'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		update_option('sh_v2_token', sanitize_text_field($_POST['sh_v2_token']));
	}

	$v2_token = esc_html(get_option('sh_v2_token'));
	

	if( isset($_POST['sh_submit_hidden_two']) && $_POST['sh_submit_hidden_two'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		update_option('sh_css', wp_kses_post($_POST['sh_css']));
	}
	if( isset($_POST['sh_submit_hidden_three']) && $_POST['sh_submit_hidden_three'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		update_option('sh_permalink', sanitize_text_field($_POST['sh_permalink']));
		shand_create_post_type();
		flush_rewrite_rules();
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
		if(isset($default_site_css)){
			update_option('sh_css', $default_site_css);
		}
		$sh_css = $default_sh_site_css;
	}

	if(isset($_POST['sh_submit_hidden_four']) && $_POST['sh_submit_hidden_four'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		update_option('sh_regex_list', base64_encode(wp_unslash($_POST['sh_regex_list'])));
	}

	$sh_regex_list = base64_decode(get_option('sh_regex_list'));

	//Experimental Settings
	if( isset($_POST['sh_submit_hidden_experimental']) && $_POST['sh_submit_hidden_experimental'] == 'Y' && check_admin_referer( 'sh-update-configuration' ) ) {
		update_option('sh_media_split', $_POST['sh_media_split']);
	}
	$sh_media_split = filter_var(get_option('sh_media_split'), FILTER_VALIDATE_BOOLEAN);


	$profile = sh_get_profile();
	$n_once = wp_nonce_field( 'sh-update-configuration' );

?>
	<h3>Shorthand API Configuration</h3>
	<form name="form1" method="post">
		<?php echo $n_once ?>
		<input type="hidden" name="sh_submit_hidden" value="Y" />
		<table class="form-table"><tbody>
		<tr class="v2row">
			<th scope="row"><label for="sh_v2_token"><?php _e("Shorthand Team Token", 'sh-v2-token' ); ?></label></th>
			<td><input type="text" id="sh_v2_token" name="sh_v2_token" value="<?php echo esc_attr($v2_token); ?>" size="28"></td>
		</tr>
		</tbody></table>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</p>
		<hr />
	</form>
	<h3>Shorthand Connect Status</h3>
	<?php if ($profile) { ?>
		<p class="status">Successfully connected</p>
		<p><strong>Username</strong>: <?php echo $profile->username; ?></p>
	<?php } else { ?>
		<p class="status warn">Not Connected</p>
	<?php } ?>
	<div style='clear:both'></div>
	<h3>Shorthand Permalink Structure</h3>
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



	<h3>Shorthand Story Page CSS (theme wide CSS)</h3>
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

	<h3>Post-processing</h3>
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

	<style>
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

		code {
  font-family: monospace;
  display: inherit;
}
	</style>

<h3>Experimental Features</h3>
		<p>Early access features that are still subject to change.</p>
		
		<form name="form_experimental" method="post">
			<?php echo $n_once ?>
			<input type="hidden" name="sh_submit_hidden_experimental" value="Y" />
			<input type="checkbox" id="sh_media_split" name="sh_media_split" value="true" <?php echo esc_attr($sh_media_split ? 'checked' : '') ?> />
			<label for="sh_media_split">Import story assets into media library</label>
			<p>Assets will be fetched individually and imported into the WP media library. Used to reduce the overall zip fetch time and size but will result in a longer fetch time for full story update.</p>
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
			</p>
		</form>
<?php
}

add_action( 'admin_menu', 'shand_shorthand_menu' );

?>

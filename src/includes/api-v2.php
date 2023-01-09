<?php

/* API FUNCTIONS LIST */
/*
	1. Get Author profile
	2. Get Stories
	3. Get Story Files Localized path
	4. Get Shorthand Story url
	5. Copy Story Zip
	6. Extract Zip File
*/

// VERSION 2 API
function sh_v2_api_get($url, $options) {
	global $serverv2URL;
	$token = get_option('sh_v2_token');
	if (!$token) {
		return false;
	}

	$url = $serverv2URL.$url;
	$request_options = array_merge(
		array(
			'headers' => array(
				'Authorization' => 'Token '.$token,
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2'
			),
			'http_api_args' => $options
		),
		$options
	);
	if(function_exists("vip_safe_wp_remote_get") && !isset($options['timeout'])){
		return vip_safe_wp_remote_get($url,false,1,3,10, $request_options);
	}else{
		return wp_remote_get($url, $request_options);
	}
	
}

function sh_v2_api_get_json($url, $options) {
	$response = sh_v2_api_get($url, $options);
	$body = wp_remote_retrieve_body($response);
	return json_decode($body);
}

/*
/* Name: Get Author profile */
/* Desc: Return a username and gravatar based on 
*/
function sh_get_profile() {
	$tokeninfo = array();

	$data = sh_v2_api_get_json('/v2/token-info', array());
	if ($data && isset($data->organisation_id)) {
		$tokeninfo['username'] = $data->name . ' ('.$data->token_type.' Token)';
		$tokeninfo['gravatar'] = $data->logo;
		$tokeninfo = (object)$tokeninfo;
	}

	return $tokeninfo;
}


/*
/* Name: Get Stories */
/* Desc: Return an array of objects (Stories) - image, id, metadata, description, title and story_version
*/
function sh_get_stories() {
	$stories = null;

	$data = sh_v2_api_get_json('/v2/stories', array('timeout' => '240'));
	if($data) {
		$stories = array();
		//Something went wrong
		if (isset($data->status) && $data->status) {
			return null;
		}
		foreach($data as $storydata) {
			$story = array(
				'image' => $storydata->signedCover,
				'id' => $storydata->id,
				'metadata' => (object)array(
					'description' => $storydata->description
				),
				'title' => $storydata->title,
				'story_version' => ''.$storydata->version
			);
			$stories[] = (object)$story;
		}
	}
	
	return $stories;
}

/*
/* Name: Get Story Files Localized path */
/* Desc: Checks if there's files in the destination path
*/
function sh_get_story_path($post_id, $story_id) {
	
	init_WP_Filesystem();
	$destination = wp_upload_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;
	
	if(!file_exists($destination_path)) {
		$destination_path = null;
	}
	
	$destination_path = apply_filters('sh_get_story_path', $destination_path, $destination);
	
	return $destination_path;
}

/*
/* Name: Get Shorthand Story url */
/* Desc: Checks if there's files in the destination path
*/
function sh_get_story_url($post_id, $story_id) {

	init_WP_Filesystem();
	
	$destination = wp_upload_dir();
	$destination_url = $destination['url'].'/shorthand/'.$post_id.'/'.$story_id;
	
	$destination_url = apply_filters('sh_get_story_url', $destination_url);
	
	return $destination_url;
}


/*
/* Name: Copy Story Zip */
/* Desc: This function redownloads the story extract but does not update meta fields.
/* Note: You will need to run shand_update_story($post_id, $story_id) as well in most cases
*/
function sh_copy_story($post_id, $story_id, $without_assets=false, $assets_only=false) {

	wp_raise_memory_limit('admin');
	init_WP_Filesystem();

	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id;
	$story = array();

	//Attempt to connect to the server
	$zip_file = wp_tempnam('sh_zip',$tmpdir);
	$response = sh_v2_api_get('/v2/stories/'.$story_id.($without_assets?'?without_assets=true':'').($assets_only?'?assets_only=true':''), array(
		'timeout' => '600',
		'stream' => true,
		'filename' => $zip_file
	));
	if (is_wp_error($response)) {
		$story['error'] = array(
			'pretty' => ' Request to Shorthand failed',
			'error' => $response->get_error_message($response)
		);
	} else if (!$response || $response['response']['code'] != 200) {
		$story['error'] = array(
			'pretty' => 'Request to Shorthand failed; check the token is configured correctly',
			'response' => $response
		);
	} else {
		$story = extractStoryContent($zip_file, $destination_path, $story_id);
	}
	
	do_action('sh_copy_story', $post_id, $story_id, $story);
	unlink($zip_file);

	return $story;
}


function init_WP_Filesystem(){
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}
}


/*
/* Name: Extract Zip File */
/* Desc: This function unzips a file downloaded via another function sh_copy_story()
*/
function extractStoryContent($zip_file, $destination_path,$story_id){
	//WP VIP HOSTING COMPATIBILITY
	$zip = new ZipArchive;
	if ( $zip->open($zip_file)) {
		wp_mkdir_p($destination_path.'/'.$story_id);
		$zip->extractTo($destination_path.'/'.$story_id);
		$zip->close();
		if (is_wp_error($zip)) {
			$story['error'] = array(
				'pretty' => 'Could not copy story into Wordpress',
				'error' => $zip->get_error_message(),
				'unzip error'=> $zip_file
			);
			if(function_exists("wp_filesize")){
				$story['error']['downloaded zip size'] = wp_filesize($zip_file);
			}
		} else {
			$story['path'] = $destination_path.'/'.$story_id;
		}
	} else {
		$story['error'] = array(
			'pretty' => 'Could not unzip file'
		);
	}

	return $story;
}

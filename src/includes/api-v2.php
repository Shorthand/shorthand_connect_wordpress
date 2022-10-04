<?php

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

function sh_get_story_url($post_id, $story_id) {

	init_WP_Filesystem();
	
	$destination = wp_upload_dir();
	$destination_url = $destination['url'].'/shorthand/'.$post_id.'/'.$story_id;
	
	$destination_url = apply_filters('sh_get_story_url', $destination_url);
	
	return $destination_url;
}

function sh_copy_story($post_id, $story_id) {
	$sh_media_split = filter_var(get_option('sh_media_split'), FILTER_VALIDATE_BOOLEAN);
	wp_raise_memory_limit('admin');
	init_WP_Filesystem();

	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id;

	$story = array();

	//Attempt to connect to the server
	$zip_file = wp_tempnam('sh_zip',$tmpdir);
	$response = sh_v2_api_get('/v2/stories/'.$story_id.($sh_media_split?'?without_assets=true':''), array(
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
		if($sh_media_split){
			$story['asset_dictionary'] = sh_fetchIndividualMedia($post_id, $story_id,$destination_path);
		}
	}
	
	do_action('sh_copy_story', $post_id, $story_id, $story);
	
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

function sh_fetchIndividualMedia($post_id, $story_id, $destination_path){
	add_filter( 'upload_mimes', 'sh_add_video_mimes' );
	$media_list = json_decode(wp_remote_retrieve_body(sh_v2_api_get('/v2/stories/'.$story_id.'/public-assets', array(
		'timeout' => '600'))));

	$media_dictionary = array();
	sh_cleaupExistingAssets($post_id);
	foreach ($media_list as $media) {
		array_push($media_dictionary, array("Original"=>$media->fullFileName, "Uploaded"=>sh_downloadPublicMedia($media,$post_id)));
		foreach ($media->breakpoints as $breakpoint){
			array_push($media_dictionary, array("Original"=>$breakpoint->fullFileName, "Uploaded"=>sh_downloadPublicMedia($breakpoint,$post_id)));
		}
	} 

	return $media_dictionary;
}

function sh_cleaupExistingAssets($post_id){
	$media_list = get_children($post_id);
	foreach ($media_list as $media) {
		wp_delete_attachment($media->ID, true);
	} 
}

function sh_downloadPublicMedia($media, $post_id){

	$media_tmp = download_url($media->path);

    if(is_wp_error( $media_tmp )){
        return "FAILED";
    }else {
        $media_size = filesize($media_tmp);
		$media_name = preg_replace('/\'\.(?=[^.]*\.)\'/', '-', $media->fullFileName);

        $file = array(
           'name' => $media_name,
           'type' => $media->mime,
           'tmp_name' => $media_tmp,
           'error' => 0,
           'size' => $media_size
        );

        $new_media = media_handle_sideload( $file, $post_id, null);
		$new_url = wp_get_attachment_url($new_media);
        return preg_replace('/^https?:/','',$new_url);
    }

}

function sh_add_video_mimes( $allowed_mimes ) {
    $allowed_mimes['mp4'] = 'video/mp4';
    return $allowed_mimes;
}

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
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
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}
	$destination = wp_upload_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;
	if(!file_exists($destination_path)) {
		$destination_path = null;
	}
	return $destination_path;
}

function sh_get_story_url($post_id, $story_id) {
	WP_Filesystem();
	global $wp_filesystem;

	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}
	$destination = wp_upload_dir();
	$destination_url = $destination['url'].'/shorthand/'.$post_id.'/'.$story_id;
	return $destination_url;
}

function sh_copy_story($post_id, $story_id) {

	wp_raise_memory_limit('admin');
	WP_Filesystem();

	global $wp_filesystem;

	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}

	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id;

	$story = array();

	//Attempt to connect to the server
	$zipfile = wp_tempnam('sh_zip',$tmpdir);
	$unzipdir = wp_tempnam('sh_unzip',$tmpdir).'_dir';
	$response = sh_v2_api_get('/v2/stories/'.$story_id, array(
		'timeout' => '600',
		'stream' => true,
		'filename' => $zipfile
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
		if(function_exists("vip_safe_wp_remote_get")){
			//WP VIP HOSTING
			$zip = new ZipArchive;
			if ( $zip->open($zipfile) == true ) {
				wp_mkdir_p($destination_path.'/'.$story_id);
				$zip->extractTo($destination_path.'/'.$story_id);
				$zip->close();
				if (is_wp_error($zip)) {
					$story['error'] = array(
						'pretty' => 'Could not copy story into Wordpress',
						'error' => $err->get_error_message(),
						'unzip error'=> $unzipfile
					);
					if(function_exists("wp_filesize")){
						$story['error']['downloaded zip size'] = wp_filesize($zipfile);
					}
				} else {
					$story['path'] = $destination_path.'/'.$story_id;
				}
			} else {
				$story['error'] = array(
					'pretty' => 'Could not unzip file'
				);
			}
		}else{
			//Standard WP
			$unzipfile = unzip_file( $zipfile, $unzipdir);
			if ( $unzipfile == 1 ) {
				wp_mkdir_p($destination_path.'/'.$story_id);
				$err = copy_dir($unzipdir, $destination_path.'/'.$story_id);
				if (is_wp_error($err)) {
					$story['error'] = array(
						'pretty' => 'Could not copy story into Wordpress',
						'error' => $err->get_error_message(),
						'unzip error'=> $unzipfile
					);
					if(function_exists("wp_filesize")){
						$story['error']['downloaded zip size'] = wp_filesize($zipfile);
					}
				} else {
					$story['path'] = $destination_path.'/'.$story_id;
				}
			} else {
				$story['error'] = array(
					'pretty' => 'Could not unzip file'
				);
			}
		}
	}
	return $story;
}
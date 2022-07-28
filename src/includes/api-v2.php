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
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			)
		),
		$options
	);

	return wp_remote_get($url, $request_options);
}

function sh_v2_api_get_json($url, $options) {
	$response = sh_v2_api_get($url, $options);
	$body = wp_remote_retrieve_body($response);
	return json_decode($body);
}

function sh_get_profile() {
	$tokeninfo = array();

	$data = sh_v2_api_get_json('/v2/token-info', array());
	if ($data && isset($data->name)) {
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
		if ($data->status) {
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
	$destination = wp_upload_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;
	if(!file_exists($destination_path)) {
		$destination_path = null;
	}
	return $destination_path;
}

function sh_get_story_url($post_id, $story_id) {
	WP_Filesystem();
	$destination = wp_upload_dir();
	$destination_url = $destination['url'].'/shorthand/'.$post_id.'/'.$story_id;
	return $destination_url;
}

function sh_copy_story($post_id, $story_id) {

	// Set the maximum memory limit for the entire operation (this is already called later by unzip_file, but lets do it earlier)
	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	WP_Filesystem();

	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id;

	$story = array();

	//Attempt to connect to the server
	$zipfile = wp_tempnam($tmpdir, 'sh_zip');
	$unzipdir = wp_tempnam($tmpdir, 'sh_unzip').'_dir';
	$response = sh_v2_api_get('/v2/stories/'.$story_id, array(
		'timeout' => '600',
		'stream' => true,
		'filename' => $zipfile
	));
	if($response['response']['code'] == 200) {
		$unzipfile = unzip_file( $zipfile, $unzipdir);
			if ( $unzipfile == 1 ) {
				wp_mkdir_p($destination_path.'/'.$story_id);
				$err = copy_dir($unzipdir, $destination_path.'/'.$story_id);
				if (is_wp_error($err)) {
					$story['error'] = array(
						'pretty' => 'Could not copy story into Wordpress',
						'error' => $err->get_error_message()
					);
				} else {
					$story['path'] = $destination_path.'/'.$story_id;
				}
			} else {
				$story['error'] = array(
					'pretty' => 'Could not unzip file'
				);
			}
	} else {
		$story['error'] = array(
			'pretty' => 'Could not get zip file',
			'response' => $response
		);
	}

	return $story;
}

<?php

// VERSION 2 API

function sh_get_profile() {

	global $serverv2URL;
	$token = get_option('sh_v2_token');

	$tokeninfo = array();

	if($token) {
		$url = $serverv2URL.'/v2/token-info';
		$response = wp_remote_get( $url, array(
			headers => array(
				'Authorization' => 'Token '.$token,
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			)
		) );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body);
		if(isset($data) && isset($data->name)) {
			$tokeninfo['username'] = $data->name . ' ('.$data->token_type.' Token)';
			$tokeninfo['gravatar'] = $data->logo;
			$tokeninfo = (object)$tokeninfo;
		}
	}

	return $tokeninfo;
}

function sh_get_stories() {
	global $serverv2URL;
	$token = get_option('sh_v2_token');

	$valid_token = true;

	$stories = null;

	//Attempt to connect to the server
	if($token) {
		$url = $serverv2URL.'/v2/stories';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Token '.$token,
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			),
			'timeout' => '240'
		) );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body);
		if(isset($data)) {
			$stories = array();
			$valid_token = true;
			//Something went wrong
			if ($data->status) {
				return null;
			}
			foreach($data as $storydata) {
				$story = array(
					'image' => $storydata->cover,
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

	global $serverv2URL;
	$token = get_option('sh_v2_token');

	$valid_token = false;

	$story = array();

	//Attempt to connect to the server
	if($token) {
		$url = $serverv2URL.'/v2/stories/'.$story_id;
		$zipfile = tempnam($tmpdir, 'sh_zip');
		$unzipdir = tempnam($tmpdir, 'sh_unzip').'_dir';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Token '.$token,
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			),
			'timeout' => '600',
			'stream' => true,
			'filename' => $zipfile
		) );
		if($response['response']['code'] == 200) {
			$unzipfile = unzip_file( $zipfile, $unzipdir);
				if ( $unzipfile == 1 ) {
					wp_mkdir_p($destination_path.'/'.$story_id);
					$err = copy_dir($unzipdir, $destination_path.'/'.$story_id);
					$story['path'] = $destination_path.'/'.$story_id;
				} else {
					$story['error'] = array(
					'pretty' => 'Could not unzip file'
				);
				}
		} else {
			$story['error'] = array(
				'pretty' => 'Could not get zip file',
				'error' => curl_error($ch),
				'response' => $response
			);
		}
	}
	return $story;
}

?>

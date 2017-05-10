<?php

// VERSION 2 API

function sh_get_profile() {

	global $serverv2URL;
	$token = get_option('sh_v2_token');

	$tokeninfo = array();

	if($token) {
		$url = $serverv2URL.'/v2/token-info';
		$ch = curl_init( $url );
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Token: '.$token));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
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
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Token: '.$token));
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
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
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;

	global $serverv2URL;
	$token = get_option('sh_v2_token');

	$valid_token = false;

	$story = array();

	//Attempt to connect to the server
	if($token) {
		$url = $serverv2URL.'/v2/stories/'.$story_id;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Token: '.$token));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
		if($data && $data->url) {
			$ch = curl_init($data->url);
			$zipfile = tempnam($tmpdir, 'sh_zip');
			$ziphandle = fopen($zipfile, "w");
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_FILE, $ziphandle);
			$response = curl_exec( $ch );
			if($response == 1) {
				$unzipfile = unzip_file( $zipfile, $destination_path);
					if ( $unzipfile ) {
						$story['path'] = $destination_path;
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
		} else {
			$story['error'] = array(
				'pretty' => 'Could not get story link',
				'error' => curl_error($ch),
				'response' => $response
			);
		}
	}
	return $story;
}

?>

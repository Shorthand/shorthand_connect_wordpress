<?php

function sh_get_profile() {

	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$data = array();

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/profile/';
		$vars = 'user='.$user_id.'&token='.$token;
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
	}
	return $data;
}

function sh_get_stories() {
	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$stories = false;

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/index/';
		$vars = 'user='.$user_id.'&token='.$token;
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
		if(isset($data->stories)) {
			$valid_token = true;
			$stories = $data->stories;
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

function sh_copy_story($post_id, $story_id) {

	// Set the maximum memory limit for the entire operation (this is already called later by unzip_file, but lets do it earlier)
	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	WP_Filesystem();
	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;

	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$story = array();

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/story/'.$story_id.'/';
		$vars = 'user='.$user_id.'&token='.$token;
		$ch = curl_init($url);
		$zipfile = tempnam($tmpdir, 'sh_zip');
		$ziphandle = fopen($zipfile, "w");
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
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
				'pretty' => 'Could not upload file',
				'error' => curl_error($ch),
				'response' => $response
			);
		}
	}
	return $story;
}

?>

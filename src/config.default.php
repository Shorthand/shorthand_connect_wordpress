<?php

global $server_url;
global $show_archived_stories;
global $noabstract;

$server_url           = 'https://test-pla-1508-wpvip-api.test.shorthand.com';
$show_archived_stories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ) {
	$noabstract = SHORTHAND_NOABSTRACT;
} else {
	$noabstract = false;
}

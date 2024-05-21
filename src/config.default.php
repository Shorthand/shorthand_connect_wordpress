<?php

global $server_url;
global $show_archived_stories;
global $noabstract;

$server_url           = 'https://api.shorthand.com';
$show_archived_stories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ) {
	$noabstract = SHORTHAND_NOABSTRACT;
} else {
	$noabstract = false;
}

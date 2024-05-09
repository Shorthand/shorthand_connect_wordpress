<?php

global $serverURL;
global $showArchivedStories;
global $noabstract;

$serverURL           = 'https://test-pla-1508-wpvip-api.test.shorthand.com';
$showArchivedStories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ) {
	$noabstract = SHORTHAND_NOABSTRACT;
} else {
	$noabstract = false;
}

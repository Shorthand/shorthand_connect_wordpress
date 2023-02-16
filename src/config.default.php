<?php

global $serverURL;
global $showServerURL;
global $showArchivedStories;
global $noabstract;

$serverURL = 'https://test-pla-676-publishingplugins-update-plugins-api.test.shorthand.com';
$showServerURL = false;
$showArchivedStories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ){
    $noabstract = SHORTHAND_NOABSTRACT;
} else {
    $noabstract = false;
}

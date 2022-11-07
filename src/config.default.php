<?php

global $serverURL;
global $serverv2URL;
global $allowversionswitch;
global $showServerURL;
global $showArchivedStories;
global $noabstract;

$serverURL = 'https://api.shorthand.com';
$serverv2URL = 'https://api.shorthand.com';
$allowversionswitch = true;
$showServerURL = false;
$showArchivedStories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ){
    $noabstract = SHORTHAND_NOABSTRACT;
} else {
    $noabstract = false;
}

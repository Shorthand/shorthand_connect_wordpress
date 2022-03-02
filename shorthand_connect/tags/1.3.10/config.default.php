<?php

$serverURL = 'https://test-dep-updates-app.test.shorthand.com';
$serverv2URL = 'https://test-dep-updates-api.test.shorthand.com';
$allowversionswitch = true;
$showServerURL = false;
$showArchivedStories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ){
    $noabstract = SHORTHAND_NOABSTRACT;
} else {
    $noabstract = false;
}

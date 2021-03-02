<?php

$serverURL = 'https://app.shorthand.com';
$serverv2URL = 'https://api.shorthand.com';
$allowversionswitch = true;
$showServerURL = false;
$showArchivedStories = false;

if ( defined( 'SHORTHAND_NOABSTRACT' ) ){
    $noabstract = SHORTHAND_NOABSTRACT;
} else {
    $noabstract = false;
}

?>

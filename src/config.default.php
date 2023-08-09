<?php

global $serverURL;
global $showArchivedStories;
global $noabstract;

$serverURL = 'https://api.shorthand.com';
$showArchivedStories = false;

if (defined('SHORTHAND_NOABSTRACT')) {
    $noabstract = SHORTHAND_NOABSTRACT;
} else {
    $noabstract = false;
}

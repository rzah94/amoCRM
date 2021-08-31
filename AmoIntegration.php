<?php

header('Content-type: text/html; charset=utf-8');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'app/Amo.php';
require_once 'app/Request.php';

$subdomain = 'rozasletatru';
$redirect_uri =  'https://rzah-project.ru/newAmoIntegration/AmoIntegration.php';
$client_id = '4a7b14d4-ceea-4c8f-8ad1-eccd55f8bad6';
$client_secret = '5N0GXM0zmlhVRL7zQGAT6jAfyCaFqf6TlxgsvUBnTMNwuvGkfL5vdgXKpGnqBxEO';




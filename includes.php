<?php

session_start();
error_reporting(0);

require_once 'php/config.php';

date_default_timezone_set(TIME_ZONE);

require_once "php/ezSQL/shared/ez_sql_core.php";
if(USE_PDO){
    require_once "php/ezSQL/ez_sql_pdo.php";
}
require_once "php/ezSQL/ez_sql_mysql.php";
require_once 'php/db.php';
require_once 'php/string.php';
require_once 'php/layout.php';
require_once 'php/class.phpmailer.php';
require_once 'php/functions.php';
require_once __DIR__ . '/src/Facebook/autoload.php';

$db = new Db;
$tmpl_strings = new StringResource('str/');

$fb = new Facebook\Facebook([
  'app_id' => '1022219621154669',
  'app_secret' => 'e85f617b2a3411e9ad32506d787a4320',
  'default_graph_version' => 'v2.4',
]);
$helper = $fb->getCanvasHelper();

try {
	if (isset($_SESSION['facebook_access_token'])) {
	$accessToken = $_SESSION['facebook_access_token'];
	} else {
  		$accessToken = $helper->getAccessToken();
	}
} catch(Facebook\Exceptions\FacebookResponseException $e) {
 	// When Graph returns an error
 	echo 'Graph returned an error: ' . $e->getMessage();
  	exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
 	// When validation fails or other local issues
	echo 'Facebook SDK returned an error: ' . $e->getMessage();
  	exit;
 }
if (isset($accessToken)) {
	if (isset($_SESSION['facebook_access_token'])) {
		$fb->setDefaultAccessToken($_SESSION['facebook_access_token']);
	} else {
		$_SESSION['facebook_access_token'] = (string) $accessToken;
	  	// OAuth 2.0 client handler
		$oAuth2Client = $fb->getOAuth2Client();
		// Exchanges a short-lived access token for a long-lived one
		$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($_SESSION['facebook_access_token']);
		$_SESSION['facebook_access_token'] = (string) $longLivedAccessToken;
		$fb->setDefaultAccessToken($_SESSION['facebook_access_token']);
	}
	// validating the access token
	try {
		$request = $fb->get('/me');
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		// When Graph returns an error
		if ($e->getCode() == 190) {
			unset($_SESSION['facebook_access_token']);
			$helper = $fb->getRedirectLoginHelper();
			$loginUrl = $helper->getLoginUrl('https://apps.facebook.com/anketirai/', $permissions);
			echo "<script>window.top.location.href='".$loginUrl."'</script>";
			exit;
		}
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		// When validation fails or other local issues
		echo 'Facebook SDK returned an error: ' . $e->getMessage();
		exit;
	}
	// getting basic info about user
	try {
		$profile_request = $fb->get('/me?fields=name,first_name,last_name,email');
		$profile = $profile_request->getGraphNode()->asArray();
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		// When Graph returns an error
		echo 'Graph returned an error: ' . $e->getMessage();
		unset($_SESSION['facebook_access_token']);
		echo "<script>window.top.location.href='https://apps.facebook.com/anketirai/'</script>";
		exit;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		// When validation fails or other local issues
		echo 'Facebook SDK returned an error: ' . $e->getMessage();
		exit;
	}
	// priting basic info about user on the screen
	//echo 'Hello '.($profile[name]).' !';
  	// Now you can redirect to another page and use the access token from $_SESSION['facebook_access_token']
} else {
	$helper = $fb->getRedirectLoginHelper();
        $permissions = ['email'];
	$loginUrl = $helper->getLoginUrl('https://apps.facebook.com/anketirai/', $permissions);
	echo "<script>window.top.location.href='".$loginUrl."'</script>";
}

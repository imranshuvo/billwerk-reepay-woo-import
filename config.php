<?php 

$key = wk_bwi_get_api_key(); //Should be getting from the db
$api_url = 'https://api.reepay.com';


$client = new \GuzzleHttp\Client([
	'base_uri' => $api_url
]);

$headers = array(
	'Authorization' => "Basic ".base64_encode($key.":"),
	'Content-Type' => 'application/json',
	'Accept' => 'application/json'
);
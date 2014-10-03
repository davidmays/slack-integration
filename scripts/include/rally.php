<?php
//rally commands
$RALLY_HOST_URL = 'https://rally1.rallydev.com/';
$RALLY_API_URL = $RALLY_HOST_URL . 'slm/webservice/v2.0/';
$RALLY_TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s.u\Z';

function CallAPI($url)
{
	global $RALLY_USERNAME, $RALLY_PASSWORD;

	$json = get_url_contents_with_basicauth($url, $RALLY_USERNAME, $RALLY_PASSWORD);
	$object = json_decode($json);

	return $object;
}

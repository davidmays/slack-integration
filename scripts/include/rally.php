<?
//rally commands
$RALLY_BASE_URL = 'https://rally1.rallydev.com/';
$RALLY_API_URL = $RALLY_BASE_URL . 'slm/webservice/v2.0/';

function CallAPI($uri)
{
	global $config;

	$json = get_url_contents_with_basicauth($uri, $config['rally']['username'], $config['rally']['password']);
	$object = json_decode($json);

	return $object;
}

?>

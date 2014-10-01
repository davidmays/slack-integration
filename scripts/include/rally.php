<?
//rally commands
$RALLY_BASE_URL = 'https://rally1.rallydev.com/';
$RALLY_API_URL = $RALLY_BASE_URL . 'slm/webservice/v2.0/';


function MakeField($title, $value, $short=false)
{
	$attachmentfield = array(
		"title" => $title,
		"value" => $value,
		"short" => $short);

	return $attachmentfield;
}

function CallAPI($uri)
{
	global $config;

	$json = get_url_contents_with_basicauth($uri, $config['rally']['username'], $config['rally']['password']);
	$object = json_decode($json);

	return $object;
}

function TruncateText($text, $len)
{
	if(strlen($text) <= $len)
		return $text;

	return substr($text,0,$len)."...[MORE]";
}

?>

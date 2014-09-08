<?
//rally commands


function getProjectPayload($projectRefUri)
{
	$project = CallAPI($projectRefUri);
}

function CallAPI($uri)
{
	global $config;

	$json = get_url_contents_with_basicauth($uri, $config['rally']['username'], $config['rally']['password']);
	$object = json_decode($json);

	return $object;
}


function GetProjectID($projectref)
{
	$ProjectFull = CallAPI($projectref);
	$projectid = $ProjectFull->Project->ObjectID;
	return $projectid;
}

?>

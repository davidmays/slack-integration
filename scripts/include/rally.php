<?
//rally commands
$RALLY_BASE_URL = 'https://rally1.rallydev.com/';
$RALLY_API_URL = $RALLY_BASE_URL . 'slm/webservice/v2.0/';


function HandleStory($id, $channel_name)
{
	$ref = FindRequirement($id);

	$payload = GetRequirementPayload($ref);

	$result = postit($channel_name, $payload->text, $payload->attachments);

	if($result=='Invalid channel specified'){
	    die("Sorry, the rallyme command can't post messages to your private chat.\n");
	}

	if($result!="ok"){
		print_r($result."\n");
		print_r(json_encode($payload));
		print_r("\n");
		die("Apparently the Rallyme script is having a problem. Ask <https://cim.slack.com/team/tdm|@tdm> about it. :frowning:");
	}
	return $result;
}

function postit($channel_name, $payload, $attachments){
	global $config, $slackCommand;

	return slack_incoming_hook_post_with_attachments(
		$config['slack']['hook'],
		$config['rally']['botname'],
		$slackCommand->ChannelName,
		$config['rally']['boticon'],
		$payload,
		$attachments);
}



function GetRallyAttachmentLink($attachmentRef)
{
	$attachments = CallAPI($attachmentRef);
	$firstattachment = $attachments->QueryResult->Results[0];

	$attachmentname = $firstattachment->_refObjectName;
	$encodedattachmentname = urlencode($attachmentname);
	$id = $firstattachment->ObjectID;

	$uri = "https://rally1.rallydev.com/slm/attachment/{$id}/{$encodedattachmentname}";
	$linktxt = "<{$uri}|{$attachmentname}>";
	return $linktxt;
}

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

function FindRequirement($id)
{
	$query = GetArtifactQueryUri($id);

	$searchresult = CallAPI($query);
//	print_r($searchresult);die;

	$count = GetCount($searchresult);
	if($count == 0)
		NotFound($id);

	return GetFirstObjectFromSearchResult("HierarchicalRequirement", $searchresult);
}

function GetArtifactQueryUri($id)
{
	global $config;
	return str_replace("[[ID]]", $id, $config['rally']['artifactquery']);
}

function TruncateText($text, $len)
{
	if(strlen($text) <= $len)
		return $text;

	return substr($text,0,$len)."...[MORE]";
}

?>

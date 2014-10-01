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

function GetRequirementPayload($ref)
{
	$object = CallAPI($ref);

	$requirement = null;

	if($object->HierarchicalRequirement)
	{
		$requirement = $object->HierarchicalRequirement;
	}
	elseif($object->Task)
	{
		$requirement = $object->Task;
	}
	else
	{
		$class = get_class($object);
		global $slackCommand;
		$userlink = BuildUserLink($slackCommand->UserName);
		print_r("Sorry {$userlink}, I can't handle a {$class} yet. I'll let @tdm know about it.");
		die;
	}

	$projecturi = $requirement->Project->_ref;

	$title = $requirement->_refObjectName;


	$ProjectFull = CallAPI($projecturi);
	$projectid = $ProjectFull->Project->ObjectID;
	$storyid = $requirement->ObjectID;
	$description = $requirement->Description;
	$owner = $requirement->Owner->_refObjectName;
	$projectName = $requirement->Project->_refObjectName;
	$itemid = $requirement->FormattedID;
	$created = $requirement->_CreatedAt;
	$estimate = $requirement->PlanEstimate;
	$hasparent = $requirement->HasParent;
	$childcount = $requirement->DirectChildrenCount;
	$state = $requirement->ScheduleState;
	$blocked = $requirement->Blocked;
	$blockedreason = $requirement->BlockedReason;
	$ready = $requirement->Ready;

	$attachmentcount = $requirement->Attachments->Count;

	$firstattachment = null;
	if($attachmentcount>0)
	{
		$linktxt = GetRallyAttachmentLink($requirement->Attachments->_ref);
		$firstattachment = MakeField("attachment",$linktxt,false);
	}

	$parent = null;
	if($hasparent)
		$parent = $requirement->Parent->_refObjectName;

	$clean_description = html_entity_decode(strip_tags($description), ENT_HTML401|ENT_COMPAT, 'UTF-8');
	$short_description = TruncateText($clean_description, 300);

	$storyuri = "https://rally1.rallydev.com/#/{$projectid}d/detail/userstory/{$storyid}";
	$enctitle = urlencode($title);
	$linktext = "<{$storyuri}|{$enctitle}>";

	$dovegray = "#CEC7B8";



	$fields = array(
		MakeField("link",$linktext,false),
		MakeField("parent",$parent,false),

		MakeField("id",$itemid,true),
		MakeField("owner",$owner,true),

		MakeField("project",$projectName,true),
		MakeField("created",$created,true),

		MakeField("estimate",$estimate,true),
		MakeField("state",$state,true));

		if($childcount>0)
			array_push($fields,MakeField("children",$childcount,true));

		if($blocked)
			array_push($fields, MakeField("blocked",$blockedreason,true));

		array_push($fields, MakeField("description",$short_description,false));

		if($firstattachment!=null)
			array_push($fields,$firstattachment);


	global $slackCommand;
	$userlink = BuildUserLink($slackCommand->UserName);
	$user_message = "Ok {$userlink}, here's the story you requested.";

	$obj = new stdClass;
	$obj->text = "";
	$obj->attachments = MakeAttachment($user_message, "", $dovegray, $fields, $storyuri);
//	print_r(json_encode($obj));die;

	return $obj;
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

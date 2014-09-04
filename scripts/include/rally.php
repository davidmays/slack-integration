<?php
//rally commands


function HandleItem($slackCommand, $rallyFormattedId)
{
	$rallyItemType = substr($rallyFormattedId,0,2);

	switch($rallyItemType){

	case "DE":
		return HandleDefect($rallyFormattedId, $slackCommand->ChannelName);
		die;
		break;
	case "US":
	case "TA":
		return HandleStory($rallyFormattedId, $slackCommand->ChannelName);
		die;
		break;
	default:
		print_r("Sorry, I don't know what kind of rally object {$rallyFormattedId} is. If you need rallyme to work with these, buy <https://cim.slack.com/team/tdm|@tdm> a :beer:. I hear he likes IPAs.");
		die;
		break;
	}
}

function HandleDefect($id, $channel_name)
{
	$defectref = FindDefect($id);

	$payload = GetDefectPayload($defectref);

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

function GetDefectPayload($ref)
{
	global $show,$requesting_user_name;

	$object = CallAPI($ref);

	$defect = $object->Defect;

	$projecturi = $defect->Project->_ref;

	$title = $defect->_refObjectName;
	$description = $defect->Description;
	$owner = $defect->Owner->_refObjectName;
	$submitter = $defect->SubmittedBy->_refObjectName;
	$project = $object->Project->_refObjectName;
	$created = $defect->_CreatedAt;
	$state = $defect->State;
	$priority = $defect->Priority;
	$severity = $defect->Severity;
	$frequency = $defect->c_Frequency;
	$foundinbuild = $defect->FoundInBuild;

	$short_description = TruncateText(strip_tags($description), 200);

	$ProjectFull = CallAPI($projecturi);
	$projectid = $ProjectFull->Project->ObjectID;
	$defectid = $defect->ObjectID;
	$projectName = $defect->Project->_refObjectName;
	$itemid = $defect->FormattedID;

	$attachmentcount = $defect->Attachments->Count;

	$firstattachment = null;
	if($attachmentcount>0)
	{
		$linktxt = GetRallyAttachmentLink($defect->Attachments->_ref);
		$firstattachment = MakeField("attachment",$linktxt,false);
	}

	$defecturi = "https://rally1.rallydev.com/#/{$projectid}d/detail/defect/{$defectid}";

	$enctitle = urlencode($title);
	$linktext = "<{$defecturi}|{$enctitle}>";

	$color = "bad";

	$clean_description = html_entity_decode(strip_tags($description), ENT_HTML401|ENT_COMPAT, 'UTF-8');
	$short_description = TruncateText($clean_description, 300);

	$fields = array(
		MakeField("link",$linktext,false),

		MakeField("id",$itemid,true),
		MakeField("owner",$owner,true),

		MakeField("project",$projectName,true),
		MakeField("created",$created,true),

		MakeField("submitter",$submitter,true),
		MakeField("state",$state,true),

		MakeField("priority",$priority,true),
		MakeField("severity",$severity,true),

    	MakeField("frequency",$frequency,true),
    	MakeField("found in",$foundinbuild,true),

		MakeField("description",$short_description,false)
	);

	if($firstattachment!=null)
		array_push($fields,$firstattachment);

	global $slackCommand;

	$userlink = BuildUserLink($slackCommand->UserName);
	$user_message = "Ok, {$userlink}, here's the defect you requested.";

	$obj = new stdClass;
	$obj->text = "";
	$obj->attachments = MakeAttachment($user_message, "", $color, $fields, $storyuri);
	return $obj;
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

function BuildUserLink($username)
{
    $userlink = "<https://cim.slack.com/team/{$username}|@{$username}>";
    return $userlink;
}

function GetArtifactQueryUri($id)
{
	global $config;
	return str_replace("[[ID]]", $id, $config['rally']['artifactquery']);
}

function GetDefectQueryUri($id)
{
	global $config;
	return str_replace("[[ID]]", $id, $config['rally']['defectquery']);
}

function FindDefect($id)
{
	$query = GetDefectQueryUri($id);
	$searchresult = CallAPI($query);

	$count = GetCount($searchresult);
	if($count == 0)
		NotFound($id);

	return GetFirstObjectFromSearchResult("Defect", $searchresult);
}

function GetCount($searchresult)
{
	return $searchresult->QueryResult->TotalResultCount;
}

function NotFound($id)
{
	global $slackCommand;
	$userlink = BuildUserLink($slackCommand->UserName);
	print_r("Sorry {$userlink}, I couldn't find {$id}");die;
}


function GetFirstObjectFromSearchResult($objectName, $result)
{
	foreach ($result->QueryResult->Results as $result)
	{
		if($result->_type == $objectName)
			return $result->_ref;
	}
	global $slackCommand;
	$userlink = BuildUserLink($slackCommand->UserName);
	print_r("Sorry @{$userlink}, your search for '{$slackCommand->Text}' was ambiguous.:\n");
	print_r("Here's what Rally told me:\n");
	print_r($result);
	die;
}

function TruncateText($text, $len)
{
	if(strlen($text) <= $len)
		return $text;

	return substr($text,0,$len)."...[MORE]";
}

<?
/**
 * Responds to queries for information about defects, tasks, and user stories.
 */
require_once('curl.php');
require_once('slack.php');
require_once('rally.php');

set_error_handler('_HandleRallyMeErrors', E_USER_ERROR);

/**
 * Parses artifact ID from request, queries Rally, and selects handler to gather
 * field values.
 *
 * @param string $command_text
 *
 * @return string[]
 */
function FetchArtifactPayload($command_text)
{
	global $RALLY_API_URL;

	list($formatted_id) = explode(' ', trim($command_text));
	$formatted_id = strtoupper($formatted_id);

	//handle item
	$query_url = $RALLY_API_URL;
	switch (substr($formatted_id, 0, 2)) {

		case 'DE': //find defect
			$query_url .= 'defect';
			$artifact_type = 'Defect';
			$func = 'ParseDefectPayload';
			break;

		case 'TA':
			$query_url .= 'artifact';
			$artifact_type = 'Task';
			$func = 'ParseTaskPayload';
			break;

		case 'US':
			$query_url .= 'artifact';
			$artifact_type = 'HierarchicalRequirement';
			$func = 'ParseStoryPayload';
			break;

		default:
			trigger_error('Sorry, @user, I don\'t know how to handle "' . $command_text . '". You can look up user stories, defects, and tasks by ID, like "DE1234".', E_USER_ERROR);
	}
	$query_url .= '?query=(FormattedID+%3D+' . $formatted_id . ')&fetch=true';

	$Results = CallAPI($query_url);
	if ($Results->QueryResult->TotalResultCount == 0) { //get count
		trigger_error('Sorry, @user, I couldn\'t find ' . $formatted_id, E_USER_ERROR); //not found
	}

	//get first object from search result
	foreach ($Results->QueryResult->Results as $Result) {
		if ($Result->_type == $artifact_type) {
			return call_user_func($func, $Result);
		}
	}
	trigger_error('Sorry, @user, your search for "' . $formatted_id . '" was ambiguous.', E_USER_ERROR);
}

/**
 * Notifies Slack users of errors either via an incoming webhook or in the body
 * of the HTTP response.
 *
 * @param  int $errno
 * @param  string $errstr
 *
 * @return void
 */
function _HandleRallyMeErrors($errno, $errstr)
{
	global $config;

	//assume at-mentions are linkified over either transmission channel
	$user = '@' . $_REQUEST['user_name'];
	$errstr = strtr($errstr, array('@user' => $user));

	if (isSlashCommand()) {
		//use an incoming webhook to report error
		slack_incoming_hook_post(
			$config['slack']['hook'],
			$config['rally']['botname'],
			$_REQUEST['channel_name'],
			$config['rally']['boticon'],
			NULL,
			$errstr
		);
	} else {
		//otherwise return Slack-formatted JSON in the response body
		PrintJsonResponse($errstr);
	}

	exit();
}

/**
 * Prepares a table of fields attached to a Rally defect for display.
 *
 * @param object $Defect
 *
 * @return string[]
 */
function ParseDefectPayload($Defect)
{
	global $RALLYME_DISPLAY_VERSION, $RALLY_BASE_URL;

	$title = $Defect->_refObjectName;
	$header = array('title' => $title, 'type' => 'defect');
	$item_url = $RALLY_BASE_URL . '#/' . basename($Defect->Project->_ref) . '/detail/defect/' . $Defect->ObjectID;

	switch ($RALLYME_DISPLAY_VERSION) {

		case 2:
			$header['item_id'] = $Defect->FormattedID;
			$header['item_url'] = $item_url;

			$state = $Defect->State;
			if ($state == 'Closed') {
				$Date = new DateTime($Defect->ClosedDate);
				$state .= ' ' . $Date->format('M j');
			}

			$fields = array(
				'Creator' => $Defect->SubmittedBy->_refObjectName,
				'Created' => $Defect->_CreatedAt,
				'Owner' => $Defect->Owner->_refObjectName,
				'State' => $state,
				'Priority' => $Defect->Priority,
				'Severity' => $Defect->Severity,
				'Description' => $Defect->Description,
			);
			if ($Defect->Attachments->Count > 0) {
				$fields['Attachment'] = GetAttachmentLinks($Defect->Attachments->_ref);
			}
			break;

		default:
			$fields = array(
				'link' => array($title => $item_url),
				'id' => $Defect->FormattedID,
				'owner' => $Defect->Owner->_refObjectName,
				'project' => $Defect->Project->_refObjectName,
				'created' => $Defect->_CreatedAt,
				'submitter' => $Defect->SubmittedBy->_refObjectName,
				'state' => $Defect->State,
				'priority' => $Defect->Priority,
				'severity' => $Defect->Severity,
				'frequency' => $Defect->c_Frequency,
				'found in' => $Defect->FoundInBuild,
				'description' => $Defect->Description,
			);
			if ($Defect->Attachments->Count > 0) {
				$fields['attachment'] = GetAttachmentLinks($Defect->Attachments->_ref);
			}
			break;
	}

	return array('header' => $header, 'fields' => $fields);
}

/**
 * Prepares a table of fields attached to a Rally task for display.
 *
 * @param object $Artifact
 *
 * @return string[]
 */
function ParseTaskPayload($Artifact)
{

}

/**
 * Prepares a table of fields attached to a Rally user story for display.
 *
 * @param object $Story
 *
 * @return string[]
 */
function ParseStoryPayload($Story)
{
	global $RALLYME_DISPLAY_VERSION, $RALLY_BASE_URL;

	$title = $Story->_refObjectName;
	$header = array('title' => $title, 'type' => 'story');
	$item_url = $RALLY_BASE_URL . '#/' . basename($Story->Project->_ref) . '/detail/userstory/' . $Story->ObjectID;

	switch ($RALLYME_DISPLAY_VERSION) {

		case 2:
			// break;

		default:
			$parent = NULL;
			if ($Story->HasParent) {
				/**
				 * @todo perform lookup of parent's project ID to make this into
				 *       a link; we can't assume it's in the same project
				 */
				$parent = $Story->Parent->_refObjectName;
			}

			$fields = array(
				'link' => array($title => $item_url),
				'parent' => $parent,
				'id' => $Story->FormattedID,
				'owner' => $Story->Owner->_refObjectName,
				'project' => $Story->Project->_refObjectName,
				'created' => $Story->_CreatedAt,
				'estimate' => $Story->PlanEstimate,
				'state' => $Story->ScheduleState,
			);
			if ($Story->DirectChildrenCount > 0) {
				$fields['children'] = $Story->DirectChildrenCount;
			}
			if ($Story->Blocked) {
				$fields['blocked'] = $Story->BlockedReason;
			}
			$fields['description'] = $Story->Description;
			if ($Story->Attachments->Count > 0) {
				$fields['attachment'] = GetAttachmentLinks($Story->Attachments->_ref);
			}
			break;
	}

	return array('header' => $header, 'fields' => $fields);
}

function GetRequirementPayload($ref)
{
	$object = CallAPI($ref);

	$requirement = null;

	if ($object->HierarchicalRequirement) {
		$requirement = $object->HierarchicalRequirement;
	} elseif ($object->Task) {
		$requirement = $object->Task;
	} else {
		$class = get_class($object);
		global $slackCommand;
		$userlink = BuildUserLink($slackCommand->UserName);
		print_r("Sorry {$userlink}, I can't handle a {$class} yet. I'll let @tdm know about it.");
		die;
	}

	$projecturi = $requirement->Project->_ref;
	$ProjectFull = CallAPI($projecturi);
	$projectid = $ProjectFull->Project->ObjectID;
	$storyid = $requirement->ObjectID;
	$storyuri = "https://rally1.rallydev.com/#/{$projectid}d/detail/userstory/{$storyid}";
	$title = $requirement->_refObjectName;
	$enctitle = urlencode($title);
	$linktext = "<{$storyuri}|{$enctitle}>";

	$parent = null;
	if ($requirement->HasParent) {
		$parent = $requirement->Parent->_refObjectName;
	}

	$fields = array(
		MakeField("link", $linktext, false),
		MakeField("parent", $parent, false),

		MakeField("id", $requirement->FormattedID, true),
		MakeField("owner", $requirement->Owner->_refObjectName, true),

		MakeField("project", $requirement->Project->_refObjectName, true),
		MakeField("created", $requirement->_CreatedAt, true),

		MakeField("estimate", $requirement->PlanEstimate, true),
		MakeField("state", $requirement->ScheduleState, true)
	);

	if ($requirement->DirectChildrenCount > 0) {
		array_push($fields, MakeField("children", $requirement->DirectChildrenCount, true));
	}

	if ($requirement->Blocked) {
		array_push($fields, MakeField("blocked", $requirement->BlockedReason, true));
	}

	$description = $requirement->Description;
	$clean_description = html_entity_decode(strip_tags($description), ENT_HTML401 | ENT_COMPAT, 'UTF-8');
	$short_description = TruncateText($clean_description, 300);
	array_push($fields, MakeField("description", $short_description, false));

	$firstattachment = null;
	if ($requirement->Attachments->Count > 0) {
		$linktxt = GetRallyAttachmentLink($requirement->Attachments->_ref);
		$firstattachment = MakeField("attachment", $linktxt, false);
	}
	if ($firstattachment != null) {
		array_push($fields, $firstattachment);
	}

	global $slackCommand;
	$userlink = BuildUserLink($slackCommand->UserName);
	$user_message = "Ok {$userlink}, here's the story you requested.";

	$dovegray = "#CEC7B8";

	$obj = new stdClass;
	$obj->text = "";
	$obj->attachments = MakeAttachment($user_message, "", $dovegray, $fields, $storyuri);
	//	print_r(json_encode($obj));die;

	return $obj;
}

/**
 * Returns an array of file links listed in a Rally attachment object.
 *
 * @param string $attachment_ref
 *
 * @return string[]
 */
function GetAttachmentLinks($attachment_ref)
{
	global $RALLY_BASE_URL;
	$url = $RALLY_BASE_URL . 'slm/attachment/';
	$links = array();

	$Attachments = CallAPI($attachment_ref);

	foreach ($Attachments->QueryResult->Results as $Attachment) {
		$filename = $Attachment->_refObjectName;
		$link_url = $url . $Attachment->ObjectID . '/' . urlencode($filename);
		$links[$filename] = $link_url;
	}

	return $links;
}

/**
 * Posts artifact details to a Slack channel via an incoming webhook.
 *
 * @param string[] $payload
 *
 * @return mixed
 */
function SendArtifactPayload($payload)
{
	global $config;

	$prextext = ArtifactPretext($payload['header']);
	$color = '#CEC7B8'; //dove gray
	if ($payload['header']['type'] == 'defect') {
		$color = 'bad'; //purple
	}

	$fields = array();
	foreach ($payload['fields'] as $label => $value) {
		$short = TRUE;
		switch ($label) {

			case 'Parent':
			case 'parent':
				if (is_string($value)) {
					$short = FALSE;
					break;
				}
			case 'Attachment':
			case 'link':
			case 'attachment':
				$link_url = reset($value);
				$value = l(urlencode(key($value)), $link_url);
				$short = FALSE;
				break;

			case 'Description':
			case 'description':
				$value = TruncateText(SanitizeText($value), 300, $payload['header']['item_url']);
				$short = FALSE;
				break;
		}
		$fields[] = MakeField($label, $value, $short);
	}

	$attachment = MakeAttachment($prextext, '', $color, $fields, $payload['header']['item_url']);

	return slack_incoming_hook_post_with_attachments(
		$config['slack']['hook'],
		$config['rally']['botname'],
		$_REQUEST['channel_name'],
		$config['rally']['boticon'],
		'',
		$attachment
	);
}

/**
 * Returns artifact details as Slack-formatted JSON in the body of the response.
 *
 * @param string[] $payload
 *
 * @return mixed
 */
function ReturnArtifactPayload($payload)
{
	$text = ArtifactPretext($payload['header']);

	foreach ($payload['fields'] as $label => $value) {
		switch ($label) {

			case 'Attachment':
			case 'Parent':
				$link_url = reset($value);
				$value = l(key($value), $link_url);
				break;

			case 'Block Reason':
				$label = '';
				$value = SanitizeText($value);
				break;

			case 'Description':
				$value = TruncateText(SanitizeText($value), 300, $payload['header']['item_url']);
				$value = '\n> ' . strtr($value, array('\n' => '\n> '));
				break;
		}

		if ($label) {
			$label .= ':';
			$text .= '\n`' . str_pad($label, 15) . '`\t' . $value;
		} else {
			$text .= '\n>' . $value;
		}
	}

	return PrintJsonResponse($text);
}

function ArtifactPretext($info)
{
	global $RALLYME_DISPLAY_VERSION;

	switch ($RALLYME_DISPLAY_VERSION) {

		case 2:
			return em('Details for ' . $info['item_id'] . ' ' . l($info['title'], $info['item_url']));

		default:
			return 'Ok, @' . $_REQUEST['user_name'] . ', here\'s the ' . $info['type'] . ' you requested.';
	}
}

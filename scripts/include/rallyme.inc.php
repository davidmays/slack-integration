<?
/**
 * Responds to queries for information about defects, tasks, and user stories.
 */
require_once('curl.php');
require_once('slack.php');
require_once('rally.php');

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

	$artifact_query = $RALLY_API_URL;
	switch (substr($formatted_id, 0, 2)) {

		case 'DE':
			$artifact_query .= 'defect';
			$artifact_type = 'Defect';
			$func = 'ParseDefectPayload';
			break;

		case 'TA':
			$artifact_query .= 'artifact';
			$artifact_type = 'Task';
			$func = 'ParseTaskPayload';
			break;

		case 'US':
			$artifact_query .= 'artifact';
			$artifact_type = 'HierarchicalRequirement';
			$func = 'ParseStoryPayload';
			break;

		default:
			die('Sorry, I don\'t know how to handle "' . $command_text . '". You can look up user stories, defects, and tasks by ID, like "DE1234".');
	}
	$artifact_query .= '?query=(FormattedID+%3D+' . $formatted_id . ')&fetch=true';

	$Results = CallAPI($artifact_query);
	if ($Results->QueryResult->TotalResultCount == 0) {
		die('Sorry, I couldn\'t find ' . $formatted_id);
	}

	foreach ($Results->QueryResult->Results as $Result) {
		if ($Result->_type == $artifact_type) {
			return call_user_func($func, $Result);
		}
	}
	die('Sorry, your search for "' . $formatted_id . '" was ambiguous.');
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
	global $RALLY_BASE_URL;

	$state = $Defect->State;
	if ($state == 'Closed') {
		$Date = new DateTime($Defect->ClosedDate);
		$state .= ' ' . $Date->format('M j');
	}

	$ret = array(
		'item_id' => $Defect->FormattedID,
		'item_url' => $RALLY_BASE_URL . '#/' . basename($Defect->Project->_ref) . '/detail/defect/' . $Defect->ObjectID,
		'title' => $Defect->_refObjectName,

		'Creator' => $Defect->SubmittedBy->_refObjectName,
		'Created' => $Defect->_CreatedAt,
		'Owner' => $Defect->Owner->_refObjectName,
		'State' => $state,
		'Priority' => $Defect->Priority,
		'Severity' => $Defect->Severity,
		'Description' => $Defect->Description,
	);

	if ($Defect->Attachments->Count > 0) {
		$ret['Attachment'] = GetAttachmentLinks($Defect->Attachments->_ref);
	}

	return $ret;
}

function GetDefectPayload($defect)
{
	$submitter = $defect->SubmittedBy->_refObjectName;
	$created = $defect->_CreatedAt;
	$state = $defect->State;
	$priority = $defect->Priority;
	$severity = $defect->Severity;
	$frequency = $defect->c_Frequency;
	$foundinbuild = $defect->FoundInBuild;

	$firstattachment = null;
	if ($defect->Attachments->Count > 0) {
		$linktxt = GetRallyAttachmentLink($defect->Attachments->_ref);
		$firstattachment = MakeField("attachment", $linktxt, false);
	}

	global $RALLY_API_URL;

	$enctitle = urlencode($defect->_refObjectName);
	$projectid = basename($defect->Project->_ref);
	$defectid = $defect->ObjectID;
	$defecturl = $RALLY_BASE_URL . '#/' . $projectid . '/detail/defect/' . $defectid;
	$linktext = l($enctitle, $defecturl);

	$itemid = $defect->FormattedID;
	$owner = $defect->Owner->_refObjectName;
	$projectName = $defect->Project->_refObjectName;

	$description = $defect->Description;
	$clean_description = html_entity_decode(strip_tags($description), ENT_HTML401 | ENT_COMPAT, 'UTF-8');
	$short_description = TruncateText($clean_description, 300);

	$fields = array(
		MakeField("link", $linktext, false),

		MakeField("id", $itemid, true),
		MakeField("owner", $owner, true),

		MakeField("project", $projectName, true),
		MakeField("created", $created, true),

		MakeField("submitter", $submitter, true),
		MakeField("state", $state, true),

		MakeField("priority", $priority, true),
		MakeField("severity", $severity, true),

		MakeField("frequency", $frequency, true),
		MakeField("found in", $foundinbuild, true),

		MakeField("description", $short_description, false)
	);

	if ($firstattachment != null) {
		array_push($fields, $firstattachment);
	}

	global $slackCommand;

	$userlink = BuildUserLink($slackCommand->UserName);
	$user_message = "Ok, {$userlink}, here's the defect you requested.";

	$color = "bad";

	$obj = new stdClass;
	$obj->text = "";
	$obj->attachments = MakeAttachment($user_message, "", $color, $fields, $storyuri);
	return $obj;
}

/**
 * Prepares a table of fields attached to a Rally artifact for display.
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
 * @param object $Artifact
 *
 * @return string[]
 */
function ParseStoryPayload($Artifact)
{

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
	$ret = array();

	$Attachments = CallAPI($attachment_ref);
	foreach ($Attachments->QueryResult->Results as $Attachment) {
		$filename = $Attachment->_refObjectName;
		$link_url = $url . $Attachment->ObjectID . '/' . urlencode($filename);
		$ret[$filename] = $link_url;
	}

	return $ret;
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
	$text = em('Details for ' . $payload['item_id'] . ' ' . l($payload['title'], $payload['item_url']));

	foreach (array_slice($payload, 3) as $title => $value) {
		switch ($title) {

			case 'Attachment':
			case 'Parent':
				$link_url = reset($value);
				$value = l(key($value), $link_url);
				break;

			case 'Block Reason':
				$title = '';
				$value = SanitizeText($value);
				break;

			case 'Description':
				$value = TruncateText(SanitizeText($value), 300, $payload['item_url']);
				$value = '\n> ' . strtr($value, ['\n' => '\n> ']);
		}

		if ($title) {
			$title .= ':';
			$text .= '\n`' . str_pad($title, 15) . '`\t' . $value;
		} else {
			$text .= '\n>' . $value;
		}
	}

	$data = ['text' => $text];
	$text = json_encode($data, JSON_HEX_AMP | JSON_HEX_APOS | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	$text = strtr($text, ['\n' => 'n', '\t' => 't']); //fix double-escaped codes

	return print_r($text);
}

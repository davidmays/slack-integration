<?
/**
 * Responds to queries for information about defects, tasks, and user stories.
 */
require('curl.php');
require('slack.php');
require('rally.php');

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
	//assume any words following the formatted id are the name of fields we want to see
	$field_filter = explode(' ', trim($command_text));
	$formatted_id = strtoupper(array_shift($field_filter));

	//determine requested artifact type
	switch (substr($formatted_id, 0, 2)) {

		case 'DE':
			$artifact_type = 'Defect';
			$func = 'ParseDefectPayload';
			break;

		case 'TA':
			$artifact_type = 'Task';
			$func = 'ParseTaskPayload';
			break;

		case 'US':
			$artifact_type = 'HierarchicalRequirement';
			$func = 'ParseStoryPayload';
			break;

		default:
			trigger_error('Sorry, @user, I don\'t know how to handle "' . $command_text . '". You can look up user stories, defects, and tasks by ID, like "DE1234".', E_USER_ERROR);
	}

	//compile query string
	global $config;
	$query_url = $config['rally']['apiurl'] . $artifact_type . '?query=(FormattedID+%3D+' . $formatted_id . ')&fetch=true';

	//query Rally
	$Results = CallAPI($query_url);
	if ($Results->QueryResult->TotalResultCount == 0) { //get count
		trigger_error('Sorry, @user, I couldn\'t find ' . $formatted_id, E_USER_ERROR); //not found
	}

	//generate payload from first query result
	foreach ($Results->QueryResult->Results as $Result) {
		if ($Result->_type == $artifact_type) {
			$payload = call_user_func($func, $Result);

			//filter display of artifact fields
			if (!empty($field_filter)) {
				if (ctype_upper(key($payload['fields'])[0])) { //match the case of payload field labels
					$field_filter = array_map('ucfirst', $field_filter);
				}
				$payload['fields'] = array_intersect_key($payload['fields'], array_flip($field_filter));
			}

			return $payload;
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
	$header = CompileArtifactHeader($Defect, 'defect');

	global $config;
	switch ($config['rallyme']['version']) {

		case 2:
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
				'link' => array($header['title'] => $header['url']),
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
 * @param object $Task
 *
 * @return string[]
 */
function ParseTaskPayload($Task)
{
	$header = CompileArtifactHeader($Task, 'task');

	global $config;
	switch ($config['rallyme']['version']) {

		case 2:
			$fields = array(
				'Parent' => $Task->WorkProduct->_refObjectName,
				'Owner' => $Task->Owner->_refObjectName,
				'Created' => $Task->_CreatedAt,
				'To Do' => $Task->ToDo,
				'Actual' => $Task->Actuals,
				'State' => $Task->State,
				'Status' => ''
			);
			if ($Task->Blocked) {
				$fields['Status'] = 'Blocked';
				$fields['Block Description'] = $Task->BlockedReason;
			} elseif ($Task->Ready) {
				$fields['Status'] = 'Ready';
			}
			$fields['Description'] = $Task->Description;
			if ($Task->Attachments->Count > 0) {
				$fields['Attachment'] = GetAttachmentLinks($Task->Attachments->_ref);
			}
			break;

		default:
			$fields = CompileRequirementFields($Task, $header);
			break;
	}

	return array('header' => $header, 'fields' => $fields);
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
	$header = CompileArtifactHeader($Story, 'story');

	global $config;
	switch ($config['rallyme']['version']) {

		case 2:
			$fields = array(
				'Project' => $Story->Project->_refObjectName,
				'Created' => $Story->_CreatedAt,
				'Owner' => $Story->Owner->_refObjectName,
				'Points' => $Story->PlanEstimate,
				'State' => $Story->ScheduleState,
				'Status' => ''
			);
			if ($Story->Blocked) {
				$fields['Status'] = 'Blocked';
				$fields['Block Description'] = $Story->BlockedReason;
			} elseif ($Story->Ready) {
				$fields['Status'] = 'Ready';
			}
			$fields['Description'] = $Story->Description;
			if ($Story->Attachments->Count > 0) {
				$fields['Attachment'] = GetAttachmentLinks($Story->Attachments->_ref);
			}
			break;

		default:
			$fields = CompileRequirementFields($Story, $header);
			break;
	}

	return array('header' => $header, 'fields' => $fields);
}

/**
 * Prepares an array of fields of meta-information common to all artifacts.
 *
 * @param object $Artifact
 * @param string $type
 *
 * @return string[]
 */
function CompileArtifactHeader($Artifact, $type)
{
	global $config;
	$path_map = array('defect' => 'defect', 'task' => 'task', 'story' => 'userstory'); //associate human-readable names with Rally URL paths

	$item_url = $config['rally']['hosturl'] . '#/' . basename($Artifact->Project->_ref) . '/detail/' . $path_map[$type] . '/' . $Artifact->ObjectID;

	return array(
		'type' => $type,
		'id' => $Artifact->FormattedID,
		'title' => $Artifact->_refObjectName,
		'url' => $item_url
	);
}

/**
 * Prepare a table of field values for stories and tasks.
 *
 * Rally lumps stories and tasks together as types of "Hierarchical Requirements"
 * and so the original version of this script rendered the same fields for both.
 *
 * @param object $Requirement
 * @param string[] $header
 *
 * @return string[]
 */
function CompileRequirementFields($Requirement, $header)
{
	$parent = NULL;
	if ($Requirement->HasParent) {
		/**
		 * @todo perform lookup of parent's project ID to make this into
		 *       a link; we can't assume it's in the same project
		 */
		$parent = $Requirement->Parent->_refObjectName;
	}

	$fields = array(
		'link' => array($header['title'] => $header['url']),
		'parent' => $parent,
		'id' => $Requirement->FormattedID,
		'owner' => $Requirement->Owner->_refObjectName,
		'project' => $Requirement->Project->_refObjectName,
		'created' => $Requirement->_CreatedAt,
		'estimate' => $Requirement->PlanEstimate,
		'state' => $Requirement->ScheduleState,
	);
	if ($Requirement->DirectChildrenCount > 0) {
		$fields['children'] = $Requirement->DirectChildrenCount;
	}
	if ($Requirement->Blocked) {
		$fields['blocked'] = $Requirement->BlockedReason;
	}
	$fields['description'] = $Requirement->Description;
	if ($Requirement->Attachments->Count > 0) {
		$fields['attachment'] = GetAttachmentLinks($Requirement->Attachments->_ref);
	}

	return $fields;
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
	global $config;
	$url = $config['rally']['hosturl'] . 'slm/attachment/';
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
				$value = TruncateText(SanitizeText($value), 300, $payload['header']['url']);
				$short = FALSE;
				break;
		}
		$fields[] = MakeField($label, $value, $short);
	}

	$attachment = MakeAttachment($prextext, '', $color, $fields, $payload['header']['url']);

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
				$value = TruncateText(SanitizeText($value), 300, $payload['header']['url']);
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

/**
 * Compiles a short message that Rallybot uses to announce query results.
 *
 * @param string[] $header
 *
 * @return string
 */
function ArtifactPretext($header)
{
	global $config;
	switch ($config['rallyme']['version']) {

		case 2:
			return em('Details for ' . $header['id'] . ' ' . l($header['title'], $header['url']));

		default:
			return 'Ok, @' . $_REQUEST['user_name'] . ', here\'s the ' . $header['type'] . ' you requested.';
	}
}

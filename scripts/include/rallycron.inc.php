<?php
require('curl.php');
require('slack.php');
require('rally.php');

function FetchLatestRallyComments($since)
{
	global $RALLY_URL, $RALLY_PROJECT_ID;

	$api_url = $RALLY_URL . 'slm/webservice/v2.0/';
	$query_url = $api_url . 'conversationpost?query=((Artifact.Project.ObjectID+%3D+' . $RALLY_PROJECT_ID . ')AND(CreationDate+>+' . $since . '))&fetch=Artifact,Text,User&order=CreationDate+asc';

	$results = CallAPI($query_url);
	$results = $results->QueryResult->Results;

	$project_url = $RALLY_URL . '#/' . $RALLY_PROJECT_ID;

	$items = array();
	foreach ($results as $Result) {
		switch ($type = $Result->Artifact->_type) {
			case 'Defect':
				$path = '/detail/defect/';
				break;
			case 'HierarchicalRequirement':
				$type = 'User Story';
				$path = '/detail/userstory/';
				break;
			default:
				continue 2; //don't display comments attached to other artifact types
		}

		$items[] = array(
			'type' => $type,
			'title' => $Result->Artifact->_refObjectName,
			'url' => $project_url . $path . basename($Result->Artifact->_ref) . '/discussion',
			'user' => $Result->User->_refObjectName,
			'text' => $Result->Text
		);
	}

	return $items;
}

function SendRallyCommentNotifications($items)
{
	global $SLACK_CHANNEL_FOR_RALLY_PROJECT;
	$success = TRUE;

	foreach ($items as $item) {
		$item['title'] = SanitizeText($item['title']);
		$item['title'] = TruncateText($item['title'], 300);
		$slug = $item['type'] . ' ' . l($item['title'], $item['url']);

		$item['text'] = SanitizeText($item['text']);
		$item['text'] = TruncateText($item['text'], 300);

		//display a preview of the comment as a message attachment
		$pretext = em('New comment added to ' . $slug);
		$text = '';
		$color = '#CEC7B8'; //dove gray
		$fields = array(MakeField($item['user'], $item['text']));
		$fallback = $item['user'] . ' commented on ' . $slug;

		$message = MakeAttachment($pretext, $text, $color, $fields, $fallback);
		$success = SendIncomingWebHookMessage($SLACK_CHANNEL_FOR_RALLY_PROJECT, '', $message) && $success;
	}

	return $success;
}

function FetchUpdatedRallyArtifacts($since)
{
	global $RALLY_URL, $RALLY_PROJECT_ID, $RALLY_TIMESTAMP_FORMAT;

	$api_url = $RALLY_URL . 'slm/webservice/v2.0/';
	$query_url = $api_url . 'artifact?query=((Project.ObjectID+%3D+' . $RALLY_PROJECT_ID . ')AND(LastUpdateDate+>+' . $since . '))&fetch=CreationDate,FormattedID,LastUpdateDate,Owner,Ready,RevisionHistory,ScheduleState,SubmittedBy&order=LastUpdateDate+asc&pagesize=200';

	$results = CallAPI($query_url);
	$results = $results->QueryResult->Results;

	$project_url = $RALLY_URL . '#/' . $RALLY_PROJECT_ID;

	$items = array();
	foreach ($results as $Artifact) {

		$user = '';
		switch ($type = $Artifact->_type) {
			case 'Defect':
				$path = '/detail/defect/';
				$user = $Artifact->SubmittedBy->_refObjectName;
				break;
			case 'HierarchicalRequirement':
				$type = 'User Story';
				$path = '/detail/userstory/';
				break;
			case 'TestCase':
				$type = 'Test Case';
				$path = '/detail/testcase/';
				break;
			default:
				continue 2; //don't display other artifact types
		}
		if (empty($user) && isset($Artifact->Owner)) {
			$user = $Artifact->Owner->_refObjectName;
		}

		//was the artifact just created?
		$lastUpDate = date_create_from_format($RALLY_TIMESTAMP_FORMAT, $Artifact->LastUpdateDate)->getTimestamp();
		$creationDate = date_create_from_format($RALLY_TIMESTAMP_FORMAT, $Artifact->CreationDate)->getTimestamp();

		if (($lastUpDate - $creationDate) < 2) { //assume items updated within 1 sec haven't changed state
			$items[] = array( //report newly-created artifacts
				'type' => $type,
				'title' => $Artifact->_refObjectName,
				'url' => $project_url . $path . basename($Artifact->_ref),
				'user' => $user,
				'id' => $Artifact->FormattedID
			);

		} elseif ($type == 'User Story') { //track progress of user stories
			switch ($Artifact->ScheduleState) {
				case 'Completed':
					$fact_table = array(1 => 'SCHEDULE STATE changed');
					$state = 'acceptance-ready';
					break;
				case 'In-Progress':
					if ($Artifact->Ready) {
						$fact_table = array(1 => 'READY changed from [false] to [true]');
						$state = 'verification-ready';
					} else {
						$fact_table = array(
							0 => 'SCHEDULE STATE changed',
							1 => 'READY changed from [true] to [false]'
						);
						$state = 'needs-work';
					}
					break;
				default:
					continue 2; //don't parse other state changes
			}

			//parse latest revision messages to verify state change
			$query2_url = $Artifact->RevisionHistory->_ref . '/Revisions?query=(CreationDate+>+' . $since . ')&fetch=CreationDate,Description,User';

			$results2 = CallAPI($query2_url);
			$results2 = $results2->QueryResult->Results;

			$is_verified = FALSE;
			foreach ($results2 as $Revision) {
				if (isset($fact_table[0]) && (strpos($Revision->Description, $fact_table[0]) !== FALSE)){
					continue 2; //stop parsing if the negative fact has appeared
				}
				if (strpos($Revision->Description, $fact_table[1]) !== FALSE) {
					$is_verified = TRUE;
					$user = $Revision->User->_refObjectName;
				}
			}
			if (!$is_verified) {
				continue; //skip artifacts with unconfirmed state change
			}

			$items[] = array( //report stories that have changed state
				'type' => $type,
				'title' => $Artifact->_refObjectName,
				'url' => $project_url . $path . basename($Artifact->_ref),
				'user' => $user,
				'id' => $Artifact->FormattedID,
				'state' => $state //presence of this key indicates state-change notification
			);
		}
	}
	return $items;
}

function SendRallyUpdateNotifications($items)
{
	global $SLACK_CHANNEL_FOR_RALLY_PROJECT;
	$success = TRUE;

	foreach ($items as $item) {
		$item['title'] = SanitizeText($item['title']);
		$item['title'] = TruncateText($item['title'], 300);
		$slug = l($item['title'], $item['url']);

		//display a state-change notification as a message attachment
		if (isset($item['state'])) {
			switch ($item['state']) {
				case 'verification-ready':
					$item['state'] = ' is ready for QA';
					$color = '#F29513'; //github orange
					break;
				case 'needs-work':
					$item['state'] = ' needs additional work';
					$color = '#D84A63'; //paletton-suggested red
					break;
				case 'acceptance-ready':
					$item['state'] = ' is ready for acceptance';
					$color = '#6CC644'; //github green
			}
			$item['state'] = $item['id'] . $item['state'];

			$pretext = em($item['type'] . ' updated by ' . $item['user']);
			$text = '';
			$fields = array(MakeField($item['state'], $slug));
			$fallback = $item['type'] . ' ' . $item['state'];

		//display a link to the new artifact as a message attachment
		} else {
			$pretext = em('New ' . $item['type'] . ' added by ' . $item['user']);
			$text = '';
			$color = '#6CC644'; //github green
			$fields = array(MakeField($item['id'], $slug));
			$fallback = $item['type'] . ' ' . $item['id'] . ' added by ' . $item['user'];
		}

		$message = MakeAttachment($pretext, $text, $color, $fields, $fallback);
		$success = sendIncomingWebHookMessage($SLACK_CHANNEL_FOR_RALLY_PROJECT, '', $message) && $success;
	}

	return $success;
}

function SendIncomingWebHookMessage($channel, $payload, $attachments)
{
	global $SLACK_INCOMING_HOOK_URL, $RALLYBOT_NAME, $RALLYBOT_ICON;

	//allow bot to display formatted attachment text
	$attachments->mrkdwn_in = ['pretext', 'text', 'title', 'fields'];

	$reply = slack_incoming_hook_post_with_attachments($SLACK_INCOMING_HOOK_URL, $RALLYBOT_NAME, $channel, $RALLYBOT_ICON, $payload, $attachments);

	$success = ($reply == 'ok');
	if (!$success) {
		trigger_error('Unable to send Incoming WebHook message: ' . $reply);
	}
	return $success;
}

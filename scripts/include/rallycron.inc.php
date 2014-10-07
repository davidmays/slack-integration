<?php
require('curl.php');
require('slack.config.php');
require('slack.php');
require('rallyme.config.php');
require('rally.php');

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

			$state_info = FetchStoryStateChangeInfo($Artifact, $since); //see rally library file
			if (is_null($state_info)) {
				continue; //skip stories that haven't changed state
			}

			$items[] = array(
				'type' => $type,
				'title' => $Artifact->_refObjectName,
				'url' => $project_url . $path . basename($Artifact->_ref),
				'user' => $state_info[1],
				'id' => $Artifact->FormattedID,
				'state' => $state_info[0] //presence of this key indicates state-change notification
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
	global $config;

	//allow bot to display formatted attachment text
	$attachments->mrkdwn_in = ['pretext', 'text', 'title', 'fields'];

	$reply = slack_incoming_hook_post_with_attachments(
		$config['slack']['hook'],
		$config['rally']['botname'],
		$channel,
		$config['rally']['boticon'],
		$payload,
		$attachments
	);

	$success = ($reply == 'ok');
	if (!$success) {
		trigger_error('Unable to send Incoming WebHook message: ' . $reply);
	}
	return $success;
}


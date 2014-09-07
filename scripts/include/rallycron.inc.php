<?php
require('curl.php');
require('slack.config.php');
require('slack.php');
require('rallyme.config.php');
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

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

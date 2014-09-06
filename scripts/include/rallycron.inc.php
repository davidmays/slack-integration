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
var_dump($query_url);
	$results = CallAPI($query_url);
	$results = $results->QueryResult->Results;

	$project_url = $RALLY_URL . '#/' . $RALLY_PROJECT_ID;

	$items = array();
	foreach ($results as $Result) {
var_dump('processing ' . $Result->FormattedID . ' ' . $Result->_refObjectName);
		$user = '';
		switch ($type = $Result->_type) {
			case 'Defect':
				$path = '/detail/defect/';
				$user = $Result->SubmittedBy->_refObjectName;
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
var_dump('-> skipping unimportant new artifact');
				continue 2; //don't display other artifact types
		}
		if (empty($user) && isset($Result->Owner)) {
			$user = $Result->Owner->_refObjectName;
		}

		//was the artifact just created?
		$lastUpDate = date_create_from_format($RALLY_TIMESTAMP_FORMAT, $Result->LastUpdateDate)->getTimestamp();
		$creationDate = date_create_from_format($RALLY_TIMESTAMP_FORMAT, $Result->CreationDate)->getTimestamp();

		if (($lastUpDate - $creationDate) < 2) { //assume items updated within 1 sec haven't changed state
var_dump('-> reporting newly-created artifact');
			$items[] = array( //report newly-created artifacts
				'type' => $type,
				'title' => $Result->_refObjectName,
				'url' => $project_url . $path . basename($Result->_ref),
				'user' => $user,
				'id' => $Result->FormattedID
			);

		} elseif ($type == 'User Story') { //track progress of user stories
			switch ($Result->ScheduleState) {
				case 'Completed':
					$fact_table = array(1 => 'SCHEDULE STATE changed');
					$state = 'acceptance-ready';
					break;
				case 'In-Progress':
					if ($Result->Ready) {
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
var_dump('-> skipping unimportant status');
					continue 2; //don't parse other state changes
			}

			//parse latest revision messages to verify state change
			$query2_url = $Result->RevisionHistory->_ref . '/Revisions?query=(CreationDate+>+' . $since . ')&fetch=CreationDate,Description,User';
			$statusResults = CallAPI($query2_url);

			$is_verified = FALSE;
			foreach ($statusResults->QueryResult->Results as $Revision) {
				if (isset($fact_table[0]) && (strpos($Revision->Description, $fact_table[0]) !== FALSE)){
var_dump('-> poisoning the well');
					continue 2; //stop parsing if the negative fact has appeared
				}
				if (strpos($Revision->Description, $fact_table[1]) !== FALSE) {
					$is_verified = TRUE;
					$user = $Revision->User->_refObjectName;
				}
			}
			if (!$is_verified) {
var_dump('-> skipping unconfirmed state change');
				continue; //skip artifacts with unconfirmed state change
			}
var_dump('-> reporting artifact state change');
			$items[] = array( //report stories that have changed state
				'type' => $type,
				'title' => $Result->_refObjectName,
				'url' => $project_url . $path . basename($Result->_ref),
				'user' => $user,
				'id' => $Result->FormattedID,
				'state' => $state //presence of this key indicates state-change notification
			);
		}
	}
	return $items;
}

function SendRallyUpdateNotifications($items)
{

}

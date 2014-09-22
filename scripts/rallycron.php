<?php
require('config/config.php');
require('include/rallycron.inc.php');

date_default_timezone_set('UTC');
$since = date($RALLY_TIMESTAMP_FORMAT, time() - $CRON_INTERVAL);

$result = NULL;

if ($items = FetchLatestRallyComments($since)) {
	$result = SendRallyCommentNotifications($items);
}

if ($items = FetchUpdatedRallyArtifacts($since)) {
	$result = SendRallyUpdateNotifications($items) && $result;
}

<?php
require('config/rallycron.conf.php');
require('include/rallycron.inc.php');

date_default_timezone_set('UTC');
$since = date($RALLY_TIMESTAMP_FORMAT, time() - $CRON_INTERVAL);

if ($items = FetchUpdatedRallyArtifacts($since)) {
	$result = SendRallyUpdateNotifications($items);
}

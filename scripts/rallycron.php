<?php
require('include/rallycron.inc.php');

date_default_timezone_set('UTC');

$CRON_INTERVAL = 61; //seconds between cron runs; pad for script run time and latency

$since = date($RALLY_TIMESTAMP_FORMAT, time() - $CRON_INTERVAL);

$items = FetchLatestRallyItems($since);
$result = SendRallyNotifications($items);

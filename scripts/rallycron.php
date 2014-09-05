<?php
require('include/curl.php');
require('include/slack.php');
require('include/rally.php');
require('include/rallyme.config.php');
require('include/slack.config.php');

date_default_timezone_set('UTC');

$CRON_INTERVAL = 61; //seconds between cron runs; pad for script run time and latency

$since = date($RALLY_TIMESTAMP_FORMAT, time() - $CRON_INTERVAL);

$items = FetchLatestRallyItems($since);
$result = SendRallyNotifications($items);

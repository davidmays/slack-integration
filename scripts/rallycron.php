<?php
require('include/curl.php');
require('include/slack.php');
require('include/rally.php');
require('config/config.php');

date_default_timezone_set('UTC');

$config['cron_interval'] = 61; //seconds between cron runs; pad for script run time and latency
$since = date($config['rally']['date_format'], time() - $config['cron_interval']);

$items = FetchLatestRallyItems($since);
$result = SendRallyNotifications($items);

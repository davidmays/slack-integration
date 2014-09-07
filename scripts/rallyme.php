<?
require('include/slack.php');
require('include/curl.php');
require('include/rally.php');
require('config/config.php');

$slackCommand = BuildSlashCommand($_REQUEST);

$rallyFormattedId = strtoupper($slackCommand->Text);

$result = HandleItem($slackCommand, $rallyFormattedId);
?>

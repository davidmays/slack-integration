<?
require('include/rallyme.config.php');
require('include/slack.config.php');
require('include/rallyme.inc.php');

$slackCommand = BuildSlashCommand($_REQUEST);

$rallyFormattedId = strtoupper($slackCommand->Text);

$result = HandleItem($slackCommand, $rallyFormattedId);
?>

<?php
require('include/slack.php');
require('include/curl.php');
require('include/rally.php');
require('include/rallyme.config.php');
require('include/slack.config.php');

$slackCommand = BuildSlashCommand($_REQUEST);

$rallyFormattedId = strtoupper($slackCommand->Text);

$result = HandleItem($slackCommand, $rallyFormattedId);

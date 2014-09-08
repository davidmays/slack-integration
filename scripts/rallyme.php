<?php
require('config/config.php');
require('include/rallyme.inc.php');

$slackCommand = BuildSlashCommand($_REQUEST);

$rallyFormattedId = strtoupper($slackCommand->Text);

$result = HandleItem($slackCommand, $rallyFormattedId);

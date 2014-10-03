<?
require('include/slack.config.php');
require('include/rallyme.config.php');
require('include/rallyme.inc.php');

$result = NULL;

if (isValidOutgoingHookRequest() && isset($_REQUEST['text'])) {
	$payload = FetchArtifactPayload($_REQUEST['text']);
	$result = isSlashCommand() ? SendArtifactPayload($payload) : ReturnArtifactPayload($payload);
}

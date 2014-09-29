<?
require('include/slack.php');
require('include/curl.php');
require('include/rally.php');
require('include/rallyme.config.php');
require('include/slack.config.php');
require('config/config.php');
require('include/rallyme.inc.php');

$result = NULL;

if (isset($_REQUEST['token']) && $_REQUEST['token'] == $SLACK_OUTGOING_HOOK_TOKEN && isset($_REQUEST['text'])) {
	$payload = FetchArtifactPayload($_REQUEST['text']);
	$result = isset($_REQUEST['command']) ? SendArtifactPayload($payload) : ReturnArtifactPayload($payload);
}

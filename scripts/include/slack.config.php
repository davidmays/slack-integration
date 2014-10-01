<?
require('slack.secrets.php');

$config['slack']['incominghook'] = 'https://' . $SLACK_SUBDOMAIN . '.slack.com/services/hooks/incoming-webhook?token=';

$config['slack']['hook'] = $config['slack']['incominghook'].$config['slack']['incominghooktoken'];
?>

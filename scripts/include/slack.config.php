<?
$config['slack']['incominghook'] = "https://cim.slack.com/services/hooks/incoming-webhook?token=";

$config['slack']['incominghooktoken'] = "REPLACE ME";

$config['slack']['hook'] = $config['slack']['incominghook'].$config['slack']['incominghooktoken'];
?>
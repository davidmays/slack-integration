<?
require('include/curl.php');
require('include/slack.php');
require('include/slack.config.php');

$command = BuildSlashCommand($_REQUEST);

$hook = $config['slack']['hook'];

//use one or the other of $emoji or $iconurl
$emoji = null;
$iconurl = "http://upload.wikimedia.org/wikipedia/en/1/13/Stick_figure.png";

$comicid = $command->Text;

$xkcdapi = "http://xkcd.com/{$comicid}/info.0.json";

$json = get_url_contents($xkcdapi);

$xkcdresponse = json_decode($json);

$alt = $xkcdresponse->alt;
$image = $xkcdresponse->img;

$payload = "{$image}\n<http://xkcd.com/{$comicid}/|{$alt}>\n";

$ret = slack_incoming_hook_post($hook, "xkcdbot", $command->ChannelName, $iconurl, $emoji, $payload);
if($ret!="ok")
	print_r("@tdm, gifbot got this response when it tried to post to the incoming hook.\n{$ret}");
?>
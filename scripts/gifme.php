<?
require('include/curl.php');
require('include/slack.php');
require('include/slack.config.php');

$command = BuildSlashCommand($_REQUEST);

$hook = $config['slack']['hook'];

//use one or the other of $emoji or $iconurl
$emoji = ":camera:";
$iconurl = null;

$enc = urlencode($command->Text);

$imageSearchJson = get_url_contents('http://ajax.googleapis.com/ajax/services/search/images?v=1.0&safe=active&as_filetype=gif&rsz=8&imgsz=medium&q=animated+gif+'.$enc);

$imageresponse = json_decode($imageSearchJson);

$whichImage = rand(0,7);

$returnedimageurl = $imageresponse->responseData->results[$whichImage]->unescapedUrl;

$ret = slack_incoming_hook_post($hook, "gifbot", $command->ChannelName, $iconurl, $emoji, "@" .$command->UserName."\n".$returnedimageurl);
if($ret!="ok")
	print_r("@tdm, gifbot got this response when it tried to post to the incoming hook.\n{$ret}");
?>
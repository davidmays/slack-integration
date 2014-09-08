<?
require('include/curl.php');
require('include/slack.php');
require('config/config.php');

$command = BuildSlashCommand($_REQUEST);

//use one or the other of $emoji or $iconurl
$emoji = ":camera:";
$iconurl = null;

$enc = urlencode($command->Text);

$imageSearchJson = get_url_contents('http://ajax.googleapis.com/ajax/services/search/images?v=1.0&safe=active&as_filetype=gif&rsz=8&imgsz=medium&q=animated+'.$enc);

$imageresponse = json_decode($imageSearchJson);

$userlink = '<https://' . $SLACK_SUBDOMAIN . '.slack.com/team/' . $command->UserName . '|' . $command->UserName . '>';

if($imageresponse->responseData == null){
	//{"responseData": null, "responseDetails": "qps rate exceeded", "responseStatus": 503}
	$details = $imageresponse->responseDetails;
	$status = $imageresponse->responseStatus;

	print_r("Sorry @{$userlink}, no gif for you! [{$details}:{$status}]\n");
	//print_r($imageresponse);
	die;
}

$whichImage = rand(0,7);
$returnedimageurl = $imageresponse->responseData->results[$whichImage]->url;

$payload = "@{$userlink} asked for '{$command->Text}'\n{$returnedimageurl}";

$ret = slack_incoming_hook_post($SLACK_INCOMING_HOOK_URL, "gifbot", $command->ChannelName, $iconurl, $emoji, $payload);
if($ret!="ok")
	print_r("@tdm, gifbot got this response when it tried to post to the incoming hook for /gifme.\n{$ret}");
?>

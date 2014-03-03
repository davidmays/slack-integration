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

$imageSearchJson = get_url_contents('http://ajax.googleapis.com/ajax/services/search/images?v=1.0&safe=active&rsz=8&imgsz=medium&q='.$enc);

$imageresponse = json_decode($imageSearchJson);





$userlink = "<https://cim.slack.com/team/{$command->UserName}|{$command->UserName}>";

if($imageresponse->responseData == null){
	//{"responseData": null, "responseDetails": "qps rate exceeded", "responseStatus": 503}
	$details = $imageresponse->responseDetails;
	$status = $imageresponse->responseStatus;
	
	print_r("Sorry @{$userlink}, no image for you! [{$details}:{$status}]\n");
	//print_r($imageresponse);
	die;
}

$whichImage = rand(0,7);
$returnedimageurl = $imageresponse->responseData->results[$whichImage]->url;

$payload = "@{$userlink} asked for '{$command->Text}'\n{$returnedimageurl}";

$ret = slack_incoming_hook_post($hook, "imagebot", $command->ChannelName, $iconurl, $emoji, $payload);
if($ret!="ok")
	print_r("@tdm, gifbot got this response when it tried to post to the incoming hook for /imageme.\n{$ret}");
?>

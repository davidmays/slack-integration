<?
require('include/curl.php');
require('include/slack.php');
require('config/config.php');

$command = BuildSlashCommand($_REQUEST);

//use one or the other of $emoji or $iconurl
$emoji = ":camera:";
$iconurl = null;
$userlink = '<https://' . $SLACK_SUBDOMAIN . '.slack.com/team/' . $command->UserName . '|' . $command->UserName . '>';
$maxtries = 2;
$tries = 0;


startover:

$imageresponse = RunImageSearch($command->Text);
$tries++;

if($imageresponse->responseData == null){
	//{"responseData": null, "responseDetails": "qps rate exceeded", "responseStatus": 503}
	$details = $imageresponse->responseDetails;
	$status = $imageresponse->responseStatus;

	if($status == 503 && $tries < $maxtries)
	{
	    sleep(1);
	    goto startover; //yeah, it's a goto. deal with it. http://xkcd.com/292/
	}

	print_r("Sorry @{$userlink}, no image for you! [{$details}:{$status}]\n");
	//print_r($imageresponse);
	die;
}

$whichImage = rand(0,7);
$returnedimageurl = $imageresponse->responseData->results[$whichImage]->url;

$payload = "@{$userlink} asked for '{$command->Text}'\n{$returnedimageurl}";

$ret = slack_incoming_hook_post($SLACK_INCOMING_HOOK_URL, "imagebot", $command->ChannelName, $iconurl, $emoji, $payload);
if($ret!="ok")
	print_r("@tdm, gifbot got this response when it tried to post to the incoming hook for /imageme.\n{$ret}");



function RunImageSearch($text)
{
	$enc = urlencode($text);

    $imageSearchJson = get_url_contents('http://ajax.googleapis.com/ajax/services/search/images?v=1.0&safe=active&rsz=8&imgsz=medium&q='.$enc);

    $imageresponse = json_decode($imageSearchJson);

    return $imageresponse;
}
?>

<?
require('include/memegenerator.php');
require('include/curl.php');
require('include/slack.php');
require('config/config.php');

/*
token=K2gDHdWZvZSmwOkW9O6yVbA7
team_id=T0001
channel_id=C2147483705
channel_name=test
timestamp=1355517523.000005
user_id=U2147483697
user_name=Steve
text=googlebot: What is the air-speed velocity of an unladen swallow?
*/

//meme generator API
$cmd = BuildSlashCommand($_REQUEST);


$payload = json_encode($cmd);

mylog('received.txt',$payload);

$cmdText = $cmd->Text;
$memetext = str_replace("memebot ", "", $cmdText);

$parts = explode("/", $memetext);

$gen = $parts[0];
$top = urlencode($parts[1]);
$bottom = urlencode($parts[2]);

$meme = CreateNewMeme($gen, $top, $bottom);
mylog('sent.txt',$meme);

$response = slack_incoming_hook_post($SLACK_INCOMING_HOOK_URL, $cmd->UserName, $cmd->ChannelName, null, ":bow:", $meme);

mylog('sent.txt',$response);

//str_replace ( mixed $search , mixed $replace , mixed $subject [, int &$count ] )

//print_r($cmd->Text);die;



//$out = new stdClass();
//$out->text = $meme;

//$json = json_encode($out);
//mylog('sent.txt',$json);
//print_r($json);
?>

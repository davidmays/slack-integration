<?
require('include/memegenerator.php');
require('include/slack.php');
require('include/curl.php');

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



//$response = slack_incoming_hook_post($config['slack']['hook'], $cmd->UserName, $cmd->ChannelNAme, null, ":bow:", $cmd->Text);

//mylog('sent.txt',$response);

//str_replace ( mixed $search , mixed $replace , mixed $subject [, int &$count ] )

//print_r($cmd->Text);die;

$cmdText = $cmd->Text;
$memetext = str_replace("memebot ", "", $cmdText);

$parts = explode("/", $memetext);

$gen = $parts[0];
$top = $parts[1];
$bottom = $parts[2];

$meme = CreateNewMeme($gen, $top, $bottom);

$out = new stdClass();
$out->Text = $meme;

$json = json_encode($out);
mylog('sent.txt',$json);
print_r($json);
?>

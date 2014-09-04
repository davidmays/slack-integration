<?php
$generators['yodawg'] = "Yo+Dawg+Heard+You";
$generators['allthethings'] = "X+All+The+Y";
$generators['yuno'] = "Y+U+No";
$generators['amitheonlyone'] = "Am+I+The+Only+One+Around+Here";


function CreateNewMeme($gen, $top, $bottom)
{
	global $generators;

	$meme = $generators[$gen];

	$API = "http://apimeme.com/meme?meme={$meme}&top={$top}&bottom={$bottom}";

	return $API;
}

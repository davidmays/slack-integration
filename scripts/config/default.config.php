<?php

  ////////////////////
 // Slack settings //
////////////////////

$config['slack']['outgoinghooktoken'] = "REPLACE ME";
$config['slack']['hook'] = "REPLACE ME"; //URL of an Incoming WebHook including https:// and token value

$config['slack']['subdomain'] = "REPLACE ME"; //subdomain used to identify your team's instance, like "cim"

  ////////////////////
 // Rally settings //
////////////////////

$config['rally']['username'] = "REPLACE ME";
$config['rally']['password'] = "REPLACE ME";

$config['rally']['botname'] = "rallybot";
$config['rally']['boticon'] = "https://yt3.ggpht.com/-vkXOTHhRGck/AAAAAAAAAAI/AAAAAAAAAAA/IBjv0oYIm5Q/s100-c-k-no/photo.jpg";

  ////////////////////////
 // Rallycron settings //
////////////////////////

$CRON_INTERVAL = 61; //seconds between cron runs; pad for script run time and latency

$RALLY_PROJECT_ID = REPLACE_ME; //number that follows '#/' in the URI of the project to track
$SLACK_CHANNEL_FOR_RALLY_PROJECT = 'REPLACE ME'; //do not include hash symbol

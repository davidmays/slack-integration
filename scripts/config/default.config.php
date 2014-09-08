<?php

  ////////////////////
 // Slack settings //
////////////////////

$SLACK_INCOMING_HOOK_URL = 'REPLACE ME'; //URL of an Incoming WebHook including https:// and token value

$SLACK_SUBDOMAIN = 'REPLACE ME'; //subdomain used to identify your team's instance, like 'cim'

  ////////////////////
 // Rally settings //
////////////////////

$RALLY_USERNAME = 'REPLACE ME';
$RALLY_PASSWORD = 'REPLACE ME';

$RALLYBOT_NAME = 'rallybot';
$RALLYBOT_ICON = 'https://yt3.ggpht.com/-vkXOTHhRGck/AAAAAAAAAAI/AAAAAAAAAAA/IBjv0oYIm5Q/s100-c-k-no/photo.jpg';

  ////////////////////////
 // Rallycron settings //
////////////////////////

$CRON_INTERVAL = 61; //seconds between cron runs; pad for script run time and latency

$RALLY_PROJECT_ID = REPLACE_ME; //number after '#/' in the URI of the project to track
$SLACK_CHANNEL_FOR_RALLY_PROJECT = 'REPLACE ME'; //do not include hash symbol

<?php

  ////////////////////
 // Slack settings //
////////////////////

$SLACK_INCOMING_HOOK_URL = 'REPLACE ME'; //unique webhook URL including 'https://' and token value
$SLACK_OUTGOING_HOOK_TOKEN = 'REPLACE ME'; //used to validate requests coming from Slack

$SLACK_SUBDOMAIN = 'REPLACE ME'; //subdomain of slack.com used by your team, e.g.: 'cim'

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

$RALLYCRON_PROJECT_ID = REPLACE_ME; //number after '#/' in URL of the rally project to track
$RALLYCRON_CHANNEL = 'REPLACE ME'; //slack channel to post to; do not include hash symbol

$CRON_INTERVAL = 61; //seconds between cron runs; pad for script run time and latency

  //////////////////////
 // Rallyme settings //
//////////////////////

$RALLYME_DISPLAY_VERSION = 2; //displays different fields for fetched artifacts

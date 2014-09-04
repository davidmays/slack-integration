<?php

  ////////////////////
 // Slack settings //
////////////////////

$config['slack']['incominghooktoken'] = "REPLACE ME";
$config['slack']['outgoinghooktoken'] = "REPLACE ME";

$config['slack']['incominghook'] = "https://cim.slack.com/services/hooks/incoming-webhook?token=";

$config['slack']['hook'] = $config['slack']['incominghook'].$config['slack']['incominghooktoken'];

  ////////////////////
 // Rally settings //
////////////////////

$config['rally']['username'] = "REPLACE ME";
$config['rally']['password'] = "REPLACE ME";

$config['rally']['botname'] = "rallybot";
$config['rally']['boticon'] = "https://yt3.ggpht.com/-vkXOTHhRGck/AAAAAAAAAAI/AAAAAAAAAAA/IBjv0oYIm5Q/s100-c-k-no/photo.jpg";

$config['rally']['artifactquery'] = "https://rally1.rallydev.com/slm/webservice/v2.0/artifact?query=(FormattedID%20=%20[[ID]])";
$config['rally']['defectquery'] = "https://rally1.rallydev.com/slm/webservice/v2.0/defect?query=(FormattedID%20=%20[[ID]])";

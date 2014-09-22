<?php
//HTTP Utility Methods for Google App Engine's PHP Runtime

function get_url_contents($url)
{
    $context = array('http' => array('method' => 'get'));
    $context = stream_context_create($context);

    return file_get_contents($url, FALSE, $context);
}

function get_url_contents_with_basicauth($url, $username, $password)
{
    $auth_header = $username . ':' . $password;
    $auth_header = base64_encode($auth_header);
    $auth_header = 'Authorization: Basic ' . $auth_header . "\r\n";

    $context = array('http' => array('method' => 'get', 'header' => $auth_header));
    $context = stream_context_create($context);

    return file_get_contents($url, FALSE, $context);
}

function curl_post($url, $data_string)
{
    $length_header = strlen($data_string);
    $length_header = 'Content-Length: ' . $length_header . "\r\n";

    $context = array('http' => array('method' => 'post', 'header' => $length_header, 'content' => $data_string));
    $context = stream_context_create($context);

    return file_get_contents($url, FALSE, $context);
}

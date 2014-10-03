<?php
//HTTP Utility Methods

function get_url_contents($url)
{
	$crl = curl_init($url);

	curl_setopt($crl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
	curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);

	$response = curl_exec($crl);
	curl_close($crl);
	return $response;
}

function get_url_contents_with_basicauth($url, $username, $password)
{
	$crl = curl_init();

	curl_setopt($crl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
	curl_setopt($crl, CURLOPT_URL, $url);
	curl_setopt($crl, CURLOPT_USERPWD, "{$username}:{$password}");
	curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);

	$ret = curl_exec($crl);
	curl_close($crl);
	return $ret;
}

function curl_post($uri, $data)
{
	$crl = curl_init($uri);
	curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($crl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($crl, CURLOPT_HTTPHEADER, array(
		'Content-Length: ' . strlen($data)
	));

	$response = curl_exec($crl);
	curl_close($crl);
	return $response;
}

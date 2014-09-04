<?php
/*
built based on the docs here:
https://developers.google.com/image-search/v1/jsondevguide
*/


function BuildSearchCommand($query, $size, $maxresults, $safe, $filetype)
{

    $cmd = new stdClass();
    $cmd->Size = $size;
    $cmd->Query = $query;
    $cmd->Count = $maxresults;
    $cmd->Safe = $safe;
    $cmd->FileType = $filetype;

    return $cmd;
}

function GetImageSearchResponse($cmd)
{

    $googleImageSearch = "http://ajax.googleapis.com/ajax/services/search/images?v=1.0&safe={$cmd->Safe}&as_filetype={$cmd->FileType}&rsz={$cmd->Count}&imgsz={$cmd->Size}&q={$cmd->Query}";

    $result = CallAPI($googleImageSearch);

    return $result;
}

function GetRandomResultFromResponse($n, $result)
{
    $resultArray = $result->responseData->results;
    $count = count($resultArray);
    $item = rand(0,$count-1);
    return $resultArray[$item];
}

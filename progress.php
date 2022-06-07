<?php

include("./vendor/autoload.php");

$resolution = $_GET['resolution'];
$logFile = "./tmp/video/log-$resolution.txt";

if(!is_file($logFile)) die("Not started");

$file = fopen($logFile, "r");
$content = file_get_contents($logFile);

preg_match("/Duration: (.*?),/", $content, $matches);
$duration = $matches[1];


preg_match_all("/time=(.*?) bitrate/", $content, $matches);
$current = $matches[1][count($matches[1])-1];

$progress = round(getTimeStamp($current)/getTimeStamp($duration) * 100);

echo $progress . "%";

function getTimeStamp($ffmpegTime){
    $ar = array_reverse(explode(":", $ffmpegTime));
    $duration = floatval($ar[0]);
    if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
    if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;
    return $duration;
}


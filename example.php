<?php

include('vendor/autoload.php');

$video = \Aznoqmous\PhpHls\HLSParser::open("./video.mp4");

$video
    ->addDefaultResolutions()
    ->setSegmentLength(5)
    ->save("./tmp/video")
;

$duration = $video->getDuration();

$spriteSize = 40;
$fps = floor($spriteSize / $duration);

$video->generateThumbnail("./tmp/video/video-thumbnail.jpg", 2, new \Aznoqmous\PhpHls\Resolution(800,600));
$video->generateThumbnailsSprite("./tmp/video/video-sprite.jpg", $fps);

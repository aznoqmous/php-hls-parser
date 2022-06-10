<?php

include('vendor/autoload.php');

$video = \App\HLSParser::open("./video.mp4");

$video
    ->addDefaultResolutions()
    ->setSegmentLength(5)
    ->save("./tmp/video")
;

$duration = $video->getDuration();

$spriteSize = 40;
$fps = floor($spriteSize / $duration);

$video->generateThumbnail("./tmp/video/video-thumbnail.jpg", 2, new \App\Resolution(800,600));
$video->generateThumbnailsSprite("./tmp/video/video-sprite.jpg", $fps);

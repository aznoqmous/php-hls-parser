<?php

include('vendor/autoload.php');

$start = time();

\App\HLSParser::open("./video.mov")
    ->addDefaultResolutions()
    ->setSegmentLength(5)
    ->save("./tmp/video")
;

echo time() - $start;
<?php

require_once __DIR__ . "/AdaptiveImages/AdaptiveImages.php";

$imageProcessor = new AdaptiveImages\AdaptiveImages(
    array(
        "cachePath"  => "ai-cache",
        "jpgQuality" => 90,
        "sharpen"    => false,
    )
);

$imageProcessor->getImage();

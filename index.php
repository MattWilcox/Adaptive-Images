<?php

require_once __DIR__ . "/AdaptiveImages.php";

$imageProcessor = new alexsomeoddpilot\AdaptiveImages(
    array(
        "cachePath"  => "ai-cache",
        "jpgQuality" => 90,
        "sharpen"    => false,
    )
);

$imageProcessor->getImage();

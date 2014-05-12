<?php
/**
 * Sets up
 */

namespace alexsomeoddpilot;

class AdaptiveImages
{
    private $resolutions;

    private $cachePath;

    private $jpgQuality;

    private $sharpen;

    private $watchCache;

    private $browserCache;

    private $documentRoot;

    private $requestedUri;

    private $requestedFile;

    private $sourceFile;

    private $resolution;

    function __construct(
        $resolutions = array(1382, 992, 768, 480),
        $cachePath = "ai-cache",
        $jpgQuality = 75,
        $sharpen = true,
        $watchCache = true,
        $browserCache = 60*60*24*7
    )
    {
        $this->resolutions = $resolutions;
        $this->cachePath = $cachePath;
        $this->jpegQuality = $jpegQuality;
        $this->sharpen = $sharpen;
        $this->watchCache = $watchCache;
        $this->browserCache = $browserCache;

        // get all of the required data from the HTTP request
        $this->documentRoot  = $_SERVER['DOCUMENT_ROOT'];
        $this->requestedUri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $this->requestedFile = basename($this->requestedUri);
        $this->sourceFile    = $this->documentRoot . $this->requestedUri;
        $this->resolution    = FALSE;
    }
}

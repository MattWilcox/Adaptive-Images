<?php
/**
 * Resizes and serves images
 *
 * Based on Adaptive Images by Matt Wilcox
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

    public function __construct(
        $resolutions = array(1382, 992, 768, 480),
        $cachePath = "ai-cache",
        $jpgQuality = 75,
        $sharpen = true,
        $watchCache = true,
        $browserCache = 60*60*24*7
    ) {
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
        $this->resolution    = false;

        $this->setupCache();
    }

    private function isMobile()
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($userAgent, 'mobile');
    }

    private function setupCache()
    {
        // does the $cache_path directory exist already?
        if (!is_dir($this->$documentRoot . "/" . $cache_path)) {
            // no, so make it
            if (!mkdir($this->$documentRoot . "/" . $cache_path, 0755, true)) {
                // check again to protect against race conditions
                if (!is_dir($this->$documentRoot . "/" . $cache_path)) {
                    // uh-oh, failed to make that directory
                    $this->sendErrorImage("Failed to create cache directory at: $document_root/$cache_path");
                }
            }
        }
    }

    /**
     * helper function: Send headers and returns an image.
     */
    private function sendImage($filename, $browserCache)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, array('png', 'gif', 'jpeg'))) {
            header("Content-Type: image/".$extension);
        } else {
            header("Content-Type: image/jpeg");
        }

        header("Cache-Control: private, max-age=" . $browserCache);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $browserCache) . ' GMT');
        header('Content-Length: ' . filesize($filename));
        readfile($filename);

        exit();
    }

    /**
     * helper function: Create and send an image with an error message.
     */
    private function sendErrorImage($message)
    {
        $imageResource = ImageCreateTrueColor(800, 300);
        $textColor     = ImageColorAllocate($imageResource, 233, 14, 91);
        $messageColor  = ImageColorAllocate($imageResource, 91, 112, 233);

        ImageString($imageResource, 5, 5, 5, "Adaptive Images encountered a problem:", $textColor);
        ImageString($imageResource, 3, 5, 25, $message, $messageColor);

        ImageString($imageResource, 5, 5, 85, "Potentially useful information:", $textColor);
        ImageString($imageResource, 3, 5, 105, "DOCUMENT ROOT IS: " . $this->documentRoot, $textColor);
        ImageString($imageResource, 3, 5, 125, "REQUESTED URI WAS: " . $this->requestedUri, $textColor);
        ImageString($imageResource, 3, 5, 145, "REQUESTED FILE WAS: " . $this->requestedFile, $textColor);
        ImageString($imageResource, 3, 5, 165, "SOURCE FILE IS: " . $this->sourceFile, $textColor);
        ImageString($imageResource, 3, 5, 185, "DEVICE IS MOBILE? " . $this->isMobile(), $textColor);

        header("Cache-Control: no-store");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1000) . ' GMT');
        header('Content-Type: image/jpeg');

        ImageJpeg($imageResource);
        ImageDestroy($imageResource);

        exit();
    }
}

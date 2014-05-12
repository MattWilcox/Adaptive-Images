<?php
// Resizes and serves images
//
// Based on Adaptive Images by Matt Wilcox

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
        $options = array()
    ) {
        $options = array_merge(
            array(
                "resolutions"  => array(1382, 992, 768, 480),
                "cachePath"    => "ai-cache",
                "jpgQuality"   => 75,
                "sharpen"      => true,
                "watchCache"   => true,
                "browserCache" => 604800
            ),
            $options
        );

        $this->resolutions  = $options["resolutions"];
        $this->cachePath    = $options["cachePath"];
        $this->jpgQuality   = $options["jpgQuality"];
        $this->sharpen      = $options["sharpen"];
        $this->watchCache   = $options["watchCache"];
        $this->browserCache = $options["browserCache"];

        // get all of the required data from the HTTP request
        $this->documentRoot  = $_SERVER['DOCUMENT_ROOT'];
        $this->requestedUri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $this->requestedFile = basename($this->requestedUri);
        $this->sourceFile    = $this->documentRoot . $this->requestedUri;
        $this->resolution    = false;

        $this->setupCache();
    }

    public function getImage()
    {
        // check if the file exists at all
        if (!file_exists($this->sourceFile)) {
            header("Status: 404 Not Found");
            exit();
        }

        /* check that PHP has the GD library available to use for image re-sizing */
        if (!extension_loaded('gd')) { // it's not loaded
            if (!function_exists('dl') || !dl('gd.so')) { // and we can't load it either
                // no GD available, so deliver the image straight up
                trigger_error('You must enable the GD extension to make use of Adaptive Images', E_USER_WARNING);
                sendImage($this->sourceFile, $this->browserCache);
            }
        }

        /* Check to see if a valid cookie exists */
        if (isset($_COOKIE['resolution'])) {
            $cookieValue = $_COOKIE['resolution'];

            // does the cookie look valid? [whole number, comma, potential floating number]
            // no it doesn't look valid
            if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", $cookieValue)) {
                setcookie("resolution", $cookieValue, time()-100); // delete the mangled cookie
            } else {
                // the cookie is valid, do stuff with it
                $cookieData   = explode(",", $_COOKIE['resolution']);

                // the base resolution (CSS pixels)
                $clientWidth  = (int) $cookieData[0];
                $totalWidth   = $clientWidth;

                // set a default, used for non-retina style JS snippet
                $pixelDensity = 1;

                if (@$cookieData[1]) {
                    // the device's pixel density factor (physical pixels per CSS pixel)
                    $pixelDensity = $cookieData[1];
                }

                // make sure the supplied break-points are in reverse size order
                rsort($this->resolutions);
                // by default use the largest supported break-point
                $resolution = $this->resolutions[0];

                // if pixel density is not 1,
                // then we need to be smart about adapting
                // and fitting into the defined breakpoints
                if ($pixelDensity != 1) {
                    // required physical pixel width of the image
                    $totalWidth = $clientWidth * $pixelDensity;

                    $resolution = $this->breakpoint($resolution, $totalWidth, $this->resolutions);

                    // the required image width is bigger than any existing value in $this->resolutions
                    if ($totalWidth > $this->resolutions[0]) {
                        // apply the multiplier
                        $resolution = $resolution * $pixelDensity;
                    }
                } else {
                    $resolution = $this->breakpoint($resolution, $totalWidth, $this->resolutions);
                }
            }
        }

        // No resolution was found (no cookie or invalid cookie)
        if (empty($resolution)) {
            // We send the lowest resolution for mobile-first approach, and highest otherwise
            $resolution = ($this->isMobile()) ?
                min($this->resolutions) : max($this->resolutions);
        }

        // if the requested URL starts with a slash, remove the slash
        if (substr($this->requestedUri, 0, 1) == "/") {
            $this->requestedUri = substr($this->requestedUri, 1);
        }

        // where might the cache file be?
        $cacheFile = $this->documentRoot . "/" . $this->cachePath . "/" . $resolution . "/" . $this->requestedUri;

        // Use the resolution value as a path variable and check
        // to see if an image of the same name exists at that path
        if (file_exists($cacheFile)) {
            var_dump($cacheFile);

            // it exists cached at that size
            // if cache watching is enabled, compare cache and source
            // modified dates to ensure the cache isn't stale
            if ($this->watchCache) {
                $cacheFile = $this->refreshCache($this->sourceFile, $cacheFile, $resolution);
            }

            $this->sendImage($cacheFile, $this->browserCache);
        }

        // It exists as a source file, and it doesn't exist cached - lets make one:
        $file = $this->generateImage($this->sourceFile, $cacheFile, $resolution);
        $this->sendImage($file, $this->browserCache);
    }

    private function breakpoint($resolution, $width, $breakpoints = array())
    {
        foreach ($breakpoints as $breakpoint) {
            if ($width <= $breakpoint) {
                $resolution = $breakpoint;
            }
        }

        return $resolution;
    }

    private function isMobile()
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($userAgent, 'mobile');
    }

    private function setupCache()
    {
        // does the $cachePath directory exist already?
        if (!is_dir($this->documentRoot . "/" . $this->cachePath)) {
            // no, so make it
            if (!mkdir($this->documentRoot . "/" . $this->cachePath, 0755, true)) {
                // check again to protect against race conditions
                if (!is_dir($this->documentRoot . "/" . $this->cachePath)) {
                    // uh-oh, failed to make that directory
                    $this->sendErrorImage(
                        "Failed to create cache directory at: " .
                        $this->documentRoot . "/" . $this->cachePath
                    );
                }
            }
        }
    }

    // helper function: Send headers and returns an image.
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

    // helper function: Create and send an image with an error message.
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

    // sharpen images function
    private function findSharp($intOrig, $intFinal)
    {
        $intFinal = $intFinal * (750.0 / $intOrig);
        $intA     = 52;
        $intB     = -0.27810650887573124;
        $intC     = .00047337278106508946;
        $intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
        return max(round($intRes), 0);
    }

    // refreshes the cached image if it's outdated
    private function refreshCache($sourceFile, $cacheFile, $resolution)
    {
        if (file_exists($cacheFile)) {
            // not modified
            if (filemtime($cacheFile) >= filemtime($sourceFile)) {
                return $cacheFile;
            }

            // modified, clear it
            unlink($cacheFile);
        }
        return generateImage($sourceFile, $cacheFile, $resolution);
    }

    // generates the given cache file for the given source file with the given resolution
    private function generateImage($sourceFile, $cacheFile, $resolution)
    {
        $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));

        // Check the image dimensions
        $dimensions   = GetImageSize($sourceFile);
        $width        = $dimensions[0];
        $height       = $dimensions[1];

        // Do we need to downscale the image?
        // no, because the width of the source image is already less than the client width
        if ($width <= $resolution) {
            return $sourceFile;
        }

        // We need to resize the source image to the width of the resolution breakpoint we're working with
        $ratio     = $height/$width;
        $newWidth  = $resolution;
        $newHeight = ceil($newWidth * $ratio);

        // re-sized image
        $dst        = ImageCreateTrueColor($newWidth, $newHeight);

        switch ($extension) {
            case 'png':
                $src = @ImageCreateFromPng($sourceFile);
                break;
            case 'gif':
                $src = @ImageCreateFromGif($sourceFile);
                break;
            default:
                $src = @ImageCreateFromJpeg($sourceFile);
                // Enable interlancing (progressive JPG, smaller size file)
                ImageInterlace($dst, true);
                break;
        }

        if ($extension=='png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // do the resize in memory
        ImageCopyResampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ImageDestroy($src);

        // sharpen the image?
        // NOTE: requires PHP compiled with the bundled version of GD
        // (see http://php.net/manual/en/function.imageconvolution.php)
        if ($this->sharpen && function_exists('imageconvolution')) {
            $intSharpness = $this->findSharp($width, $newWidth);
            $arrMatrix = array(
                array(-1, -2, -1),
                array(-2, $intSharpness + 12, -2),
                array(-1, -2, -1)
            );
            imageconvolution($dst, $arrMatrix, $intSharpness, 0);
        }

        $cacheDir = dirname($cacheFile);

        // does the directory exist already?
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                // check again if it really doesn't exist to protect against race conditions
                if (!is_dir($cacheDir)) {
                    // uh-oh, failed to make that directory
                    ImageDestroy($dst);
                    sendErrorImage("Failed to create cache directory: $cacheDir");
                }
            }
        }

        if (!is_writable($cacheDir)) {
            sendErrorImage("The cache directory is not writable: $cacheDir");
        }

        // save the new file in the appropriate path, and send a version to the browser
        switch ($extension) {
            case 'png':
                $gotSaved = ImagePng($dst, $cacheFile);
                break;
            case 'gif':
                $gotSaved = ImageGif($dst, $cacheFile);
                break;
            default:
                $gotSaved = ImageJpeg($dst, $cacheFile, $this->jpgQuality);
                break;
        }

        ImageDestroy($dst);

        if (!$gotSaved && !file_exists($cacheFile)) {
            sendErrorImage("Failed to create image: $cacheFile");
        }

        return $cacheFile;
    }
}

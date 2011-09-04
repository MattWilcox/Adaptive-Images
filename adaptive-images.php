<?php
/* FILE INFO ----------------------------------------------------------------------------------------------------------
             http://adaptive-images.com | Twitter: @responsiveimg
             
    version 1.3 beta (2011/08/31) ------------------------------------------------------------
      NEW FEATURES
        * Added support for Mobile First philosophy (see CONFIG, $mobile_first)
      
      NOTES
      When $mobile_first is set to TRUE it means the mobile sized version of the requested
      image will be sent in the event that no cookie is set (likely because JavaScript is
      unavailable). If FALSE, the original image is sent.
      
      There is a known problem with Firefox on a first visit to a site where $mobile_first
      is TRUE. It doesn't set the cookie fast enough, so the very first load sends the mobile
      size image. All page loads after are fine. Opera, Safari, and Chrome all work OK.
                 
    version 1.2.2 (2011/08/30) ------------------------------------------------------------
      NEW FEATURES
        * Unsupported no-javascript solution (see instructions.htm)
        
    version 1.2.1 (2011/08/26) ------------------------------------------------------------
      NO NEW FEATURES
      I have re-branded Responsive-Images to "Adaptive-Images", firstly to help distinguish
      this project from the identically named project by Filament Group, and secondly
      because it's a more appropriate name. This system actively adapts existing images as
      well as "responding" to the visitors viewport to serve an appropriately sized version.
      
      NOTES
      The project is now available on GitHub for those who wish to track it there:
      https://github.com/MattWilcox/Adaptive-Images
      
    version 1.2 (2011/08/21) ------------------------------------------------------------
                                           Contributions by Fabian Beiner, with thanks :)
      NEW FEATURES
        * Support for PNG and GIF images
        * Added ability to sharpen re-scaled images (see CONFIG, $sharpen)
      BUG FIXES
        * Better extension detection (.jpeg was failing)
        * First-run Firefox bug fixed. The JS must be in-line, in the <head>!
            DETAILS:
            Firefox (and potentially others, but not observed anywhere else) was requesting
            the first <img> before it loaded the external javascript file, even when in the
            <head>. This caused Firefox to load the full-resolution image the very first 
            time the site was visited. All subsequent page views were fine.
      OTHER IMPROVEMENTS
        * Cleaned up the .htaccess file and included clear comments on use
        * Vastly improved instructions and examples on the downloadable zip
        * Since 1.1 fixed issues with browser cache, default cache time now set to 7 days
        * Refactored PHP code

    version 1.1 (2011/08/16) ------------------------------------------------------------

      NEW FEATURES
        * Re-engineered the size detection methodology.

          Now detects maximum possible screen size of the device instead of the current
          window size. This removes the problem of visitors with small windows caching
          small images to the browser, then upon maximising the browser having too small
          images for the new screen size. It also simplifies the JS back down to its
          original "just dump the size into a cookie" functionality.

          This update removes the following:

          * All JS config options

    version 1.0 (2011/08/09) ------------------------------------------------------------

      NEW FEATURES
        * Headers sent along with the image, for browser side caching (see CONFIG, $browser_cache)
        * JavaScript responds to window re-sizes, requests higher res images if required
      BUG FIXES
        * Fixed the MIME type for JPG's (image/jpeg not image/jpg)

    beta 2 (2011/08/04) -----------------------------------------------------------------

      NEW FEATURES
        * Added the ability to control generated image quality (see CONFIG, $jpg_quality)
        * Added configurable resolution breakpoints (see CONFIG, $resolutions)
        * Optional Cache checking - defaults to on (see CONFIG, $watch_cache)
      BUG FIXES
        * The PHP now checks that the GD extension is loaded before proceeding
        * Clarified comments further

    beta 1 (2011/08/01) -----------------------------------------------------------------

      NEW FEATURES
        * Initial public release
        * Commented the PHP for public consumption
        * Added user-configurable cache directory (see CONFIG, $cache_path)
      BUG FIXES
        * Didn't generate downscaled images due to typo

/* CONFIG ----------------------------------------------------------------------------------------------------------- */

$resolutions   = array(1382, 992, 768, 480); // the resolution break-points to use (screen widths, in pixels)
$cache_path    = "ai-cache"; // where to store the generated re-sized images. This folder must be writable.
$jpg_quality   = 80; // the quality of any generated JPGs on a scale of 0 to 100
$sharpen       = TRUE; // Shrinking images can blur details, perform a sharpen on re-scaled images?
$watch_cache   = TRUE; // check that the responsive image isn't stale (ensures updated source images are re-cached)
$browser_cache = 60*60*24*7; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
$mobile_first  = FALSE; // If there's no cookie deliver the mobile version (if FALSE, delivers original resource)

/* END CONFIG ----------------------------------------------------------------------------------------------------------
------------------------ Don't edit anything after this line unless you know what you're doing -------------------------
--------------------------------------------------------------------------------------------------------------------- */

/* get all of the required data from the HTTP request */
$document_root  = $_SERVER['DOCUMENT_ROOT'];
$requested_uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested_file = basename($requested_uri);
$source_file    = $document_root.$requested_uri;
$resolution     = FALSE;

/* helper function: Send headers and returns an image. */
function sendImage($filename, $browser_cache) {
  $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (in_array($extension, array('png', 'gif', 'jpeg'))) {
    header("Content-Type: image/".$extension);
  } else {
    header("Content-Type: image/jpeg");
  }
  header("Cache-Control: public, max-age=".$browser_cache);
  header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
  header('Content-Length: '.filesize($filename));
  readfile($filename);
  exit();
}

/* helper function: Create and send an image with an error message. */
function sendErrorImage($message) {
  $im         = ImageCreateTrueColor(800, 200);
  $text_color = ImageColorAllocate($im, 233, 14, 91);
  ImageString($im, 1, 5, 5, $message, $text_color);
  header("Cache-Control: no-cache");
  header('Expires: '.gmdate('D, d M Y H:i:s', time()-1000).' GMT');
  header('Content-Type: image/jpeg');
  ImageJpeg($im);
  ImageDestroy($im);
  exit();
}

/* sharpen images function */
function findSharp($intOrig, $intFinal) {
  $intFinal = $intFinal * (750.0 / $intOrig);
  $intA     = 52;
  $intB     = -0.27810650887573124;
  $intC     = .00047337278106508946;
  $intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
  return max(round($intRes), 0);
}

/* refreshes the cached image if it's outdated */
function refreshCache($source_file, $cache_file, $resolution) {
  if (file_exists($cache_file)) {
    // not modified
    if (filemtime($cache_file) >= filemtime($source_file)) {
      return $cache_file;
    }

    // modified, clear it
    unlink($cache_file);
  }
  return generateImage($source_file, $cache_file, $resolution);
}

/* generates the given cache file for the given source file with the given resolution */
function generateImage($source_file, $cache_file, $resolution) {
  global $sharpen, $jpg_quality;

  $extension = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));

  // Check the image dimensions
  $dimensions   = GetImageSize($source_file);
  $width        = $dimensions[0];
  $height       = $dimensions[1];

  // Do we need to downscale the image?
  if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
    return $source_file;
  }

  // We need to resize the source image to the width of the resolution breakpoint we're working with
  $ratio      = $height/$width;
  $new_width  = $resolution;
  $new_height = ceil($new_width * $ratio);

  switch ($extension) {
    case 'png':
      $src = @ImageCreateFromPng($source_file); // original image
    break;
    case 'gif':
      $src = @ImageCreateFromGif($source_file); // original image
    break;
    default:
      $src = @ImageCreateFromJpeg($source_file); // original image
    break;
  }

  $dst = ImageCreateTrueColor($new_width, $new_height); // re-sized image
  ImageCopyResampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory
  ImageDestroy($src);

  // sharpen the image?
  if ($sharpen == TRUE) {
    $intSharpness = findSharp($width, $new_width);
    $arrMatrix = array(
        array(-1, -2, -1),
        array(-2, $intSharpness + 12, -2),
        array(-1, -2, -1)
    );
    imageconvolution($dst, $arrMatrix, $intSharpness, 0);
  }

  $cache_dir = dirname($cache_file);

  // does the directory exist already?
  if (!is_dir($cache_dir)) { 
    if (!mkdir($cache_dir, 0777, true)) {
      // check again if it really doesn't exist to protect against race conditions
      if (!is_dir($cache_dir)) {
        // uh-oh, failed to make that directory
        ImageDestroy($dst);
        sendErrorImage("Failed to create directory: $cache_dir");
      }
    }
  }

  if (!is_writable($cache_dir)) {
    sendErrorImage("The cache directory is not writable: $cache_dir");
  }

  // save the new file in the appropriate path, and send a version to the browser
  switch ($extension) {
    case 'png':
      $gotSaved = ImagePng($dst, $cache_file);
    break;
    case 'gif':
      $gotSaved = ImageGif($dst, $cache_file);
    break;
    default:
      $gotSaved = ImageJpeg($dst, $cache_file, $jpg_quality);
    break;
  }
  ImageDestroy($dst);

  if (!$gotSaved && !file_exists($cache_file)) {
    sendErrorImage("Failed to create image: $cache_file");
  }

  return $cache_file;
}

// check if the file exists at all
if (!file_exists($source_file)) {
  header("Status: 404 Not Found");
  exit();
}

/* check that PHP has the GD library available to use for image re-sizing */
if (!extension_loaded('gd')) { // it's not loaded
  if (!function_exists('dl') || !dl('gd.so')) { // and we can't load it either
    // no GD available, so deliver the image straight up
    trigger_error('You must enable the GD extension to make use of Adaptive Images', E_USER_WARNING);
    sendImage($source_file, $browser_cache);
  }
}

/* Check to see if a valid cookie exists */
if (isset($_COOKIE['resolution'])) {
  if (is_numeric($_COOKIE['resolution'])) {
    $client_width = (int) $_COOKIE["resolution"]; // store the cookie value in a variable

    /* the client width in the cookie is valid, now fit that number into the correct resolution break point */
    rsort($resolutions); // make sure the supplied break-points are in reverse size order
    $resolution = $resolutions[0]; // by default it's the largest supported break-point

    foreach ($resolutions as $break_point) { // filter down
      if ($client_width <= $break_point) {
        $resolution = $break_point;
      }
    }
  } else {
    setcookie("resolution", "", time() -1); // delete the mangled cookie
  }
}

/* No resolution was found (no cookie or invalid cookie) */
if (!$resolution) {
  // We send the lowest resolution for mobile-first approach
  if ($mobile_first === TRUE) {
    sort($resolutions); // make sure the supplied break-points are in ascending size order
    $resolution = $resolutions[0]; // we only want the smallest supported break-point (the mobile resolution)
  }
  // We want to send the original resolution image if JS is disabled
  else {
    sendImage($source_file, $browser_cache);
  }
}

$cache_file = $document_root."/$cache_path/$resolution/".$requested_uri;

/* Use the resolution value as a path variable and check to see if an image of the same name exists at that path */
if (file_exists($cache_file)) { // it exists cached at that size
  if ($watch_cache) { // if cache watching is enabled, compare cache and source modified dates to ensure the cache isn't stale
    $cache_file = refreshCache($source_file, $cache_file, $resolution);
  }

  sendImage($cache_file, $browser_cache);
}

/* It exists as a source file, so lets work with that: */
$file = generateImage($source_file, $cache_file, $resolution);
sendImage($file, $browser_cache);
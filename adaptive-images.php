<?php
/* PROJECT INFO --------------------------------------------------------------------------------------------------------
                Version: 1.3.8
                Changelog: http://adaptive-images.com/changelog.txt

                Homepage: http://adaptive-images.com
                GitHub:   https://github.com/MattWilcox/Adaptive-Images
                Twitter:  @responsiveimg
                
                LEGAL:
                Adaptive Images by Matt Wilcox is licensed under a Creative Commons Attribution 3.0 Unported License.

/* CONFIG ----------------------------------------------------------------------------------------------------------- */

$resolutions   = array(1382, 992, 768, 480); // the resolution break-points to use (screen widths, in pixels)
$cache_path    = "ai-cache"; // where to store the generated re-sized images. This folder must be writable.
$jpg_quality   = 80; // the quality of any generated JPGs on a scale of 0 to 100
$sharpen       = TRUE; // Shrinking images can blur details, perform a sharpen on re-scaled images?
$watch_cache   = TRUE; // check that the responsive image isn't stale (ensures updated source images are re-cached)
$browser_cache = 60*60*24*7; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
$mobile_first  = TRUE; // If there's no cookie FALSE sends the largest $resolutions version (TRUE sends smallest)

/* END CONFIG ----------------------------------------------------------------------------------------------------------
------------------------ Don't edit anything after this line unless you know what you're doing -------------------------
--------------------------------------------------------------------------------------------------------------------- */

/* get all of the required data from the HTTP request */
$document_root  = $_SERVER['DOCUMENT_ROOT'];
$requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
$requested_file = basename($requested_uri);
$source_file    = $document_root.$requested_uri;
$resolution     = FALSE;

/* Browser engine detect 
   NOTE: only required to work around a bug where some browsers can't set the cookie fast enough on the first visit to the
         website. Such browsers therefor act as though no cookie was set on the very first visit. This means we can't
         allow desktop browsers to have $mobile_first = TRUE (which we don't want anyway) */
function browser_detect() {
  $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);

  // Identify the OS platform. Match only desktop OSs
  if (
      strpos($userAgent,'macintosh') ||
      strpos($userAgent,'windows nt') ||
      strpos($userAgent,'x11')
     ) {
      return TRUE;
  }
}

/* Do we need to switch mobile first off? */
if(browser_detect()){
  $mobile_first = FALSE;
}

/* helper function: Send headers and returns an image. */
function sendImage($filename, $browser_cache) {
  $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (in_array($extension, array('png', 'gif', 'jpeg'))) {
    header("Content-Type: image/".$extension);
  } else {
    header("Content-Type: image/jpeg");
  }
  header("Cache-Control: private, max-age=".$browser_cache);
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
  header("Cache-Control: no-store");
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

	if($extension=='png'){
		imagealphablending($dst, false);
		imagesavealpha($dst,true);
		$transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
		imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
	}
  ImageCopyResampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory
  ImageDestroy($src);

  // sharpen the image?
  // NOTE: requires PHP compiled with the bundled version of GD (see http://php.net/manual/en/function.imageconvolution.php)
  if($sharpen == TRUE && function_exists('imageconvolution')) {
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
  // We send the lowest resolution for mobile-first approach, and highest otherwise
  $resolution = $mobile_first ? min($resolutions) : max($resolutions);
}

/* if the requested URL starts with a slash, remove the slash */
if(substr($requested_uri, 0,1) == "/") {
  $requested_uri = substr($requested_uri, 1);
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
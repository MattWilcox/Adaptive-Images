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
$requested_uri  = (stripos($_SERVER['REQUEST_URI'], '?')) ? substr($_SERVER['REQUEST_URI'], 0, stripos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI'];
$requested_file = basename($requested_uri);
$extension      = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION)); // filename extension of the passed uri

/* set up some variables we'll use later */
switch ($extension) { // sort out MIME types for different file types
  case png:
    $mime_type = "Content-Type: image/png";
  break;
  case gif:
    $mime_type = "Content-Type: image/gif";
  break;
  default:
    $mime_type = "Content-Type: image/jpeg";
  break;
}

/* helper function: Send headers and returns an image. */
function sendImage($filename, $mime_type, $browser_cache) {
    header($mime_type);
    header("Pragma: public");
    header("Cache-Control: maxage=".$browser_cache);
    header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
    header('Content-Length: '.filesize($filename));
    readfile($filename);
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

/* check that PHP has the GD library available to use for image re-sizing */
if (!extension_loaded('gd')) { // it's not loaded
  if (!dl('gd.so')) { // and we can't load it either
    if (!file_exists($document_root.$requested_uri)) { // and the requested file doesn't exist
      header("Status: 404 Not Found");
      exit();
    }

    // no GD available, but the requested image exists, so deliver that straight up
    sendImage($document_root.$requested_uri,$mime_type,$browser_cache);
  }
}

/* Check to see if a cookie exists */
if (!$_COOKIE["resolution"]) { // no cookie
  if (!file_exists($document_root.$requested_uri)) { // and the requested file doesn't exist either
    header("Status: 404 Not Found");
    exit();
  }
  /* there isn't a cookie (likely no JS available) */
  if ($mobile_first !== TRUE) { // We want to send the original resolution image if JS is disabled
    sendImage($document_root.$requested_uri,$mime_type,$browser_cache);
  }
  else { // We want to send the mobile resolution if JS is disabled
    sort($resolutions); // make sure the supplied break-points are in ascending size order
    $resolution = $resolutions[0]; // we only want the smallest supported break-point (the mobile resolution)
    
    if (file_exists($document_root."/$cache_path/$resolution/".$requested_uri)) { // it exists cached at that size
      sendImage($document_root."/$cache_path/$resolution/".$requested_uri,$mime_type,$browser_cache);
    }
    else { // it doesn't exist at that size cached
      if ($watch_cache) { // if cache watching is enabled, compare cache and source modified dates to ensure the cache isn't stale
        $cache_date  = filemtime($document_root."/".$cache_path."/".$resolution."/".$requested_uri);
        $source_date = filemtime($document_root.$requested_uri);

        if ($cache_date < $source_date) { // the source file has been replaced since the cache was generated
          // Check the image dimensions
          $source_image = $document_root.$requested_uri;
          $dimensions   = GetImageSize($source_image);
          $width        = $dimensions[0];
          $height       = $dimensions[1];

          // Do we need to downscale the image?
          if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
            sendImage($document_root.$requested_uri);
          }

          // We need to resize the source image to the width of the resolution breakpoint we're working with
          $ratio      = $height/$width;
          $new_width  = $resolution;
          $new_height = ceil($new_width * $ratio);

          switch ($extension) {
            case png:
              $src = @ImageCreateFromPng($source_image); // original image
            break;
            case gif:
              $src = @ImageCreateFromGif($source_image); // original image
            break;
            default:
              $src = @ImageCreateFromJpeg($source_image); // original image
            break;
          }

          $dst = ImageCreateTrueColor($new_width,$new_height); // re-sized image
          ImageCopyResampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory

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

          // check the path directory exists and is writable
          $directories = str_replace("/$requested_file","",$requested_uri); // get the directories only
          $directories = substr($directories,1); // clean the string

          if (!is_dir("$document_root/$cache_path/$resolution/$directories")) { // does the directory exist already?
            if (!mkdir("$document_root/$cache_path/$resolution/$directories", 0777, true)) { // make the directory
              // uh-oh, failed to make that directory
              ImageDestroy($src); // clean-up after ourselves
              ImageDestroy($dst); // clean-up after ourselves

              /* notify the client by way of throwing a message in a bottle, as that's all we can do */
              $im         = ImageCreateTrueColor(800, 200);
              $text_color = ImageColorAllocate($im, 233, 14, 91);
              ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
              header("Pragma: public");
              header("Cache-Control: maxage=".$browser_cache);
              header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
              header('Content-Type: image/jpeg');
              ImageJpeg($im); ImageDestroy($im);
              exit();
            }
          }

          // save the new file in the appropriate path, and send a version to the browser
          switch ($extension) {
            case png:
              $gotSaved = ImagePng($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
            break;
            case gif:
              $gotSaved = ImageGif($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
            break;
            default:
              $gotSaved = ImageJpeg($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file", $jpg_quality);
            break;
          }

          if (!$gotSaved) {
            /* Couldn't save image, notify the client by way of throwing a message in a bottle, as that's all we can do */
            $im         = ImageCreateTrueColor(800, 200);
            $text_color = ImageColorAllocate($im, 233, 14, 91);
            ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
            header('Content-Type: image/jpeg');
            ImageJpeg($im); ImageDestroy($im);
            exit();
          }
          else { // we saved the image to cache, now deliver the image to the client
            ImageDestroy($src); ImageDestroy($dst);
            sendImage("$document_root/$cache_path/$resolution/$directories/$requested_file",$mime_type,$browser_cache);
          }
        }
      } // end of if watch-cache
    } // end it doesn't exist at the mobile size cached
  }
}

$client_width = $_COOKIE["resolution"]; // store the cookie value in a variable

/* check the cookie value makes sense */
if (!is_numeric($client_width)) { // nope, that's not a numeric value
  setcookie("resolution", "", time() -1); // delete the mangled cookie

  /* now check to see if the requested file exists */
  if (!file_exists($document_root.$requested_uri)) { // the requested file doesn't exist
    header("Status: 404 Not Found");
    exit();
  }

  // cookie was mangled so we don't know the client width, but the requested original image exists. Serve that.
  sendImage($document_root.$requested_uri,$mime_type,$browser_cache);
}

/* the client width in the cookie is valid, now fit that number into the correct resolution break point */
rsort($resolutions); // make sure the supplied break-points are in reverse size order
$resolution = $resolutions[0]; // by default it's the largest supported break-point

foreach ($resolutions as $break_point) { // filter down
   if ($client_width <= $break_point) {
     $resolution = $break_point;
   }
}

/* Use the resolution value as a path variable and check to see if an image of the same name exists at that path */
if (file_exists($document_root."/$cache_path/$resolution/".$requested_uri)) { // it exists cached at that size

  if ($watch_cache) { // if cache watching is enabled, compare cache and source modified dates to ensure the cache isn't stale
    $cache_date  = filemtime($document_root."/".$cache_path."/".$resolution."/".$requested_uri);
    $source_date = filemtime($document_root.$requested_uri);

    if ($cache_date < $source_date) { // the source file has been replaced since the cache was generated
      // Check the image dimensions
      $source_image = $document_root.$requested_uri;
      $dimensions   = GetImageSize($source_image);
      $width        = $dimensions[0];
      $height       = $dimensions[1];

      // Do we need to downscale the image?
      if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
        sendImage($document_root.$requested_uri);
      }

      // We need to resize the source image to the width of the resolution breakpoint we're working with
      $ratio      = $height/$width;
      $new_width  = $resolution;
      $new_height = ceil($new_width * $ratio);

      switch ($extension) {
        case png:
          $src = @ImageCreateFromPng($source_image); // original image
        break;
        case gif:
          $src = @ImageCreateFromGif($source_image); // original image
        break;
        default:
          $src = @ImageCreateFromJpeg($source_image); // original image
        break;
      }

      $dst = ImageCreateTrueColor($new_width,$new_height); // re-sized image
      ImageCopyResampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory

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

      // check the path directory exists and is writable
      $directories = str_replace("/$requested_file","",$requested_uri); // get the directories only
      $directories = substr($directories,1); // clean the string

      if (!is_dir("$document_root/$cache_path/$resolution/$directories")) { // does the directory exist already?
        if (!mkdir("$document_root/$cache_path/$resolution/$directories", 0777, true)) { // make the directory
          // uh-oh, failed to make that directory
          ImageDestroy($src); // clean-up after ourselves
          ImageDestroy($dst); // clean-up after ourselves

          /* notify the client by way of throwing a message in a bottle, as that's all we can do */
          $im         = ImageCreateTrueColor(800, 200);
          $text_color = ImageColorAllocate($im, 233, 14, 91);
          ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
          header("Pragma: public");
          header("Cache-Control: maxage=".$browser_cache);
          header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
          header('Content-Type: image/jpeg');
          ImageJpeg($im); ImageDestroy($im);
          exit();
        }
      }

      // save the new file in the appropriate path, and send a version to the browser
      switch ($extension) {
        case png:
          $gotSaved = ImagePng($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
        break;
        case gif:
          $gotSaved = ImageGif($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
        break;
        default:
          $gotSaved = ImageJpeg($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file", $jpg_quality);
        break;
      }

      if (!$gotSaved) {
        /* Couldn't save image, notify the client by way of throwing a message in a bottle, as that's all we can do */
        $im         = ImageCreateTrueColor(800, 200);
        $text_color = ImageColorAllocate($im, 233, 14, 91);
        ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
        header('Content-Type: image/jpeg');
        ImageJpeg($im);
        ImageDestroy($im);
        exit();
      }

      // we saved the image to cache, now deliver the image to the client
      ImageDestroy($src);
      ImageDestroy($dst);
      sendImage("$document_root/$cache_path/$resolution/$directories/$requested_file",$mime_type,$browser_cache);
    }
  } // end of if watch-cache

  sendImage($document_root."/$cache_path/$resolution/".$requested_uri,$mime_type,$browser_cache);
}

/* It doesn't exist as a cached file, check the default image folder to see if it exists there */
if (!file_exists($document_root.$requested_uri)) { // the file doesn't exist at the original size either
  header("Status: 404 Not Found");
  exit();
}

/* It exists as a source file, so lets work with that: */

// Check the image dimensions
$source_image = $document_root.$requested_uri;
$dimensions   = GetImageSize($source_image);
$width        = $dimensions[0];
$height       = $dimensions[1];

// Do we need to downscale the image?
if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
  sendImage($document_root.$requested_uri,$mime_type,$browser_cache);
}

// We need to resize the source image to the width of the resolution breakpoint we're working with
$ratio      = $height/$width;
$new_width  = $resolution;
$new_height = ceil($new_width * $ratio);

switch ($extension) {
  case png:
    $src = @ImageCreateFromPng($source_image); // original image
  break;
  case gif:
    $src = @ImageCreateFromGif($source_image); // original image
  break;
  default:
    $src = @ImageCreateFromJpeg($source_image); // original image
  break;
}

$dst = ImageCreateTrueColor($new_width,$new_height); // re-sized image
ImageCopyResampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory

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

// check the path directory exists and is writable
$directories = str_replace("/$requested_file","",$requested_uri); // get the directories only
$directories = substr($directories,1); // clean the string

if (!is_dir("$document_root/$cache_path/$resolution/$directories")) { // does the directory exist already?
  if (!mkdir("$document_root/$cache_path/$resolution/$directories", 0777, true)) { // make the directory
    // uh-oh, failed to make that directory
    ImageDestroy($src); // clean-up after ourselves
    ImageDestroy($dst); // clean-up after ourselves

    /* notify the client by way of throwing a message in a bottle, as that's all we can do */
    $im         = ImageCreateTrueColor(800, 200);
    $text_color = ImageColorAllocate($im, 233, 14, 91);
    ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
    header("Pragma: public");
    header("Cache-Control: maxage=".$browser_cache);
    header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
    header('Content-Type: image/jpeg');
    ImageJpeg($im); ImageDestroy($im);
    exit();
  }
}

// save the new file in the appropriate path, and send a version to the browser
switch ($extension) {
  case png:
    $gotSaved = ImagePng($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
  break;
  case gif:
    $gotSaved = ImageGif($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file");
  break;
  default:
    $gotSaved = ImageJpeg($dst, "$document_root/$cache_path/$resolution/$directories/$requested_file", $jpg_quality);
  break;
}

if (!$gotSaved) {
  /* Couldn't save image, notify the client by way of throwing a message in a bottle, as that's all we can do */
  $im         = ImageCreateTrueColor(800, 200);
  $text_color = ImageColorAllocate($im, 233, 14, 91);
  ImageString($im, 1, 5, 5,  "Failed to create directories: $document_root/$cache_path/$resolution/$directories", $text_color);
  header('Content-Type: image/jpeg');
  ImageJpeg($im);
  ImageDestroy($im);
  exit();
}

// we saved the image to cache, now deliver the image to the client
ImageDestroy($src);
ImageDestroy($dst);
sendImage("$document_root/$cache_path/$resolution/$directories/$requested_file",$mime_type,$browser_cache);

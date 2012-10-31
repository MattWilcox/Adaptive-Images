<?php

    /* Adaptive Images (is forked and adapted from original "Adaptive Images" by Matt Wilcox) {
    
        forked from:
            GitHub:     https://github.com/MattWilcox/Adaptive-Images
            Version:    1.5.2
            Homepage:   http://adaptive-images.com
            Twitter:    @responsiveimg
            LEGAL:      Adaptive Images by Matt Wilcox is licensed under a Creative Commons Attribution 3.0 Unported License.
        
        forked and adapted by:
            GitHub:     https://github.com/johannheyne/Adaptive-Images
            Version:    1
        
    } */

    include('setup.php');

    $enable_resolutions = $config['enable_resolutions']; // the resolution break-points to use (screen widths, in pixels)
    $resolutions        = $config['resolutions']; // the resolution break-points to use (screen widths, in pixels)
    $breakpoints        = $config['breakpoints']; // the image break-points to use in the src-parameter 
    $cache_path         = $config['cache_path']; // @ Johann Heyne where to store the generated re-sized images. Specify from your document root!
    $jpg_quality        = $config['jpg_quality']; // the quality of any generated JPGs on a scale of 0 to 100
    $jpg_quality_retina = $config['jpg_quality_retina']; // the quality of any generated JPGs on a scale of 0 to 100 for retina
    $sharpen            = $config['sharpen']['status']; // Shrinking images can blur details, perform a sharpen on re-scaled images?
    $watch_cache        = $config['watch_cache']; // check that the adapted image isn't stale (ensures updated source images are re-cached)
    $browser_cache      = $config['browser_cache']; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
    $debug_mode         = $config['debug_mode']; // Write new Image dimentions into the stored imageif(!$_GET['w']) $_GET['w'] = 100;
    $prevent_cache      = $config['prevent_cache']; // always generate and deliver new images
    $setup_ratio_arr    = FALSE;
    
    if ( isset($_GET['size']) ) {
    
        if(isset($setup[$_GET['size']]['ratio'])) $setup_ratio_arr  = explode(':', $setup[$_GET['size']]['ratio']);
    
        if( isset($setup[$_GET['size']]['sharpen']['amount']) ) $config['sharpen']['amount'] = $setup[$_GET['size']]['sharpen']['amount'];
        if( isset($setup[$_GET['size']]['jpg_quality']) ) $jpg_quality = $setup[$_GET['size']]['jpg_quality'];
        if( isset($setup[$_GET['size']]['jpg_quality_retina']) ) $jpg_quality_retina = $setup[$_GET['size']]['jpg_quality_retina'];
    
    
        /* get the image size and build the breakpoint-string */
        foreach($setup[$_GET['size']]['breakpoints'] as $key => $value) {
            $param_array[] = $key . '-' . $value;
        }
        $param = implode( '_', $param_array );
        $_GET['bp'] = $param;
    }
    
    /* get the image parameter-string and convert it into an array */
    if( isset($_GET['bp']) ) {
    
        $temp = explode('_', $_GET['bp']);
        foreach($temp as $key => $item)  {
            $arr = explode('-', $item);
            $x = explode('%', $arr[1] );
            if(count($x) === 2) {
                $images_param[$arr[0]]['unit'] = '%';
                $images_param[$arr[0]]['val'] = $x[0];
            }
            $x = explode('px', $arr[1] );
            if(count($x) === 2) {
                $images_param[$arr[0]]['unit'] = 'px';
                $images_param[$arr[0]]['val'] = $x[0];
            }
        }
    }



    /* get all of the required data from the HTTP request */
    $document_root  = $_SERVER['DOCUMENT_ROOT'];
    $requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
    $requested_file = basename($requested_uri);
    $source_file    = $document_root.$requested_uri;
    $resolution     = FALSE;
    
    if( !$enable_resolutions ) {
        if( !isset($images_param) || count($images_param) === 0 ) {
            sendImage($source_file, $browser_cache);
            die();
        }
    }

    /* Mobile detection 
    NOTE: only used in the event a cookie isn't available. */
    function is_mobile() {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($userAgent, 'mobile');
    }

    /* Does the UA string indicate this is a mobile? */
    if(!is_mobile()){
        $is_mobile = FALSE;
    }
    else {
        $is_mobile = TRUE;
    }

    // does the $cache_path directory exist already?
    if (!is_dir("$document_root/$cache_path")) { // no
        if (!mkdir("$document_root/$cache_path", 0755, true)) { // so make it
            if (!is_dir("$document_root/$cache_path")) { // check again to protect against race conditions
                // uh-oh, failed to make that directory
                sendErrorImage("Failed to create cache directory at: $document_root/$cache_path");
            }
        }
    }

    /* helper function: Send headers and returns an image. */
    function sendImage($filename, $browser_cache) {
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, array('png', 'gif', 'jpeg'))) {
            header("Content-Type: image/".$extension);
        }
        else {
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
        /* get all of the required data from the HTTP request */
        $document_root  = $_SERVER['DOCUMENT_ROOT'];
        $requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $requested_file = basename($requested_uri);
        $source_file    = $document_root.$requested_uri;

        if(!is_mobile()){
            $is_mobile = "FALSE";
        }
        else {
            $is_mobile = "TRUE";
        }

        $im            = ImageCreateTrueColor(800, 300);
        $text_color    = ImageColorAllocate($im, 233, 14, 91);
        $message_color = ImageColorAllocate($im, 91, 112, 233);

        ImageString($im, 5, 5, 5, "Adaptive Images encountered a problem:", $text_color);
        ImageString($im, 3, 5, 25, $message, $message_color);

        ImageString($im, 5, 5, 85, "Potentially useful information:", $text_color);
        ImageString($im, 3, 5, 105, "DOCUMENT ROOT IS: $document_root", $text_color);
        ImageString($im, 3, 5, 125, "REQUESTED URI WAS: $requested_uri", $text_color);
        ImageString($im, 3, 5, 145, "REQUESTED FILE WAS: $requested_file", $text_color);
        ImageString($im, 3, 5, 165, "SOURCE FILE IS: $source_file", $text_color);
        ImageString($im, 3, 5, 185, "DEVICE IS MOBILE? $is_mobile", $text_color);

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

        // prevents caching by config ($prevent_cache and $debug mode)
        global $debug_mode;
        global $prevent_cache;
        if($prevent_cache) unlink($cache_file);

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
    
        global $sharpen, $jpg_quality, $jpg_quality_retina, $setup_ratio_arr;

        $extension = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));

        // Check the image dimensions
        $dimensions   = GetImageSize($source_file);
        $width        = $dimensions[0];
        $height       = $dimensions[1];

        // Do we need to downscale the image?
        /* because of cropping, we need to prozess the image
        if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
            return $source_file;
        }
        */
    
        // We need to resize the source image to the width of the resolution breakpoint we're working with
        $ratio = $height / $width;
        if ($width <= $resolution) {
            $new_width  = $width;
        }
        else {
            $new_width  = $resolution;
        }
    
        $new_height = ceil($new_width * $ratio);
    
        $debug_width = $new_width;
        $debug_height = $new_height;
        
        $start_x = 0;
        $start_y = 0;
        
        if ( $setup_ratio_arr ) {
        
            // set height for new image 
            $orig_ratio = $new_width / $new_height;
            $crop_ratio = $setup_ratio_arr[0] / $setup_ratio_arr[1];
            $ratio_diff = $orig_ratio / $crop_ratio;
            $ini_new_height = ceil($new_height * $ratio_diff);
        
            $dst = ImageCreateTrueColor($new_width, $ini_new_height); // re-sized image
        
            $debug_width = $new_width;
            $debug_height = $ini_new_height;
        
            // set new width and height for skaleing image to fit new height
            
            if($ini_new_height > $new_height) {
                $crop_factor = $ini_new_height / $new_height;
                $temp_new_width = ceil($new_width * $crop_factor);
                $new_height = ceil($new_height * $crop_factor);
                $start_x = ($new_width - $temp_new_width) / 2;
                $new_width = $temp_new_width;
            }
            else {
                $start_y = -($new_height - $ini_new_height) / 2;
            }
        }
        else {
            $dst = ImageCreateTrueColor($new_width, $new_height); // re-sized image
        }
    
        switch ($extension) {
            case 'png':
            $src = @ImageCreateFromPng($source_file); // original image
            break;
            case 'gif':
            $src = @ImageCreateFromGif($source_file); // original image
            break;
            default:
            $src = @ImageCreateFromJpeg($source_file); // original image
            ImageInterlace($dst, true); // Enable interlancing (progressive JPG, smaller size file)
            break;
        }
        if($extension=='png') {
            imagealphablending($dst, false);
            imagesavealpha($dst,true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
        }
    
        ImageCopyResampled($dst, $src, $start_x, $start_y, 0, 0, $new_width, $new_height, $width, $height); // do the resize in memory
        // debug mode
        global $debug_mode;
        if($debug_mode) {
            // write a textstring with the dimensions
            $color = imagecolorallocate($dst, 255, 255, 255); // ugly red 
            $cookie_data = explode(',', $_COOKIE['resolution']);
            $debug_ratio = false;
            if( $setup_ratio_arr ) $debug_ratio = $setup_ratio_arr[0] . ':' . $setup_ratio_arr[1];
            imagestring( $dst, 5, 10, 5, $debug_width." x ".$debug_height . ' ' . $debug_ratio . ' device:' . $cookie_data[0] . '*' . $cookie_data[1] . '=' . ceil($cookie_data[0] * $cookie_data[1]),$color);
        }

        ImageDestroy($src);

        // sharpen the image
        if($sharpen == TRUE) {
            global $config;
            $amount = $config['sharpen']['amount']; // max 500
            $radius = '1'; // 50
            $threshold = '0'; // max 255
            
            if ( strtolower($extension) == 'jpg' OR strtolower($extension) == 'jpeg') {
                if($amount !== '0') $dst = UnsharpMask($dst, $amount, $radius, $threshold);
            }
        }

        $cache_dir = dirname($cache_file);

        // does the directory exist already?
        if (!is_dir($cache_dir)) { 
            if (!mkdir($cache_dir, 0755, true)) {
                // check again if it really doesn't exist to protect against race conditions
                if (!is_dir($cache_dir)) {
                    // uh-oh, failed to make that directory
                    ImageDestroy($dst);
                    sendErrorImage("Failed to create cache directory: $cache_dir");
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
    if (isset($_COOKIE['resolution']) ) {
        $cookie_value = $_COOKIE['resolution'];
    
        // does the cookie look valid? [whole number, comma, potential floating number]
        if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", "$cookie_value")) { // no it doesn't look valid
            setcookie("resolution", "$cookie_value", time()-100); // delete the mangled cookie
        }
        else {
            // the cookie is valid, do stuff with it
            $cookie_data   = explode(",", $_COOKIE['resolution']);
            $client_width  = (int) $cookie_data[0]; // the base resolution (CSS pixels)
            $total_width   = $client_width;
            $pixel_density = 1; // set a default, used for non-retina style JS snippet
            if (@$cookie_data[1]) { // the device's pixel density factor (physical pixels per CSS pixel)
                $pixel_density = $cookie_data[1];
            }
            if ( $pixel_density != 2 ) $pixel_density = 1;
            if ( $pixel_density == 2 ) $jpg_quality = $jpg_quality_retina;
            
            rsort($resolutions); // make sure the supplied break-points are in reverse size order
            $resolution = $resolutions[0]; // by default use the largest supported break-point

            // if pixel density is not 1, then we need to be smart about adapting and fitting into the defined breakpoints
            if($pixel_density != 1) {
                $total_width = $client_width * $pixel_density; // required physical pixel width of the image

                // the required image width is bigger than any existing value in $resolutions
                if($total_width > $resolutions[0]) {
                    // firstly, fit the CSS size into a break point ignoring the multiplier
                    foreach ($resolutions as $break_point) { // filter down
                        if ($total_width <= $break_point) {
                            $resolution = $break_point;
                        }
                    }
                    // now apply the multiplier
                    $resolution = $resolution * $pixel_density;
                }
                //the required image fits into the existing breakpoints in $resolutions
                else {
                    foreach ($resolutions as $break_point) { // filter down
                        if ($total_width <= $break_point) {
                            $resolution = $break_point;
                        }
                    }
                }
            }
            else { // pixel density is 1, just fit it into one of the breakpoints
                foreach ($resolutions as $break_point) { // filter down
                    if ($total_width <= $break_point) {
                        $resolution = $break_point;
                    }
                }
            }

            // recalculate the resolution depending on the image parameters
            if(isset($images_param)) {
                foreach($images_param as $key => $item) {
                    global $breakpoints;
                    $width = $breakpoints[$key];
                    if ($item['unit'] === '%' AND $width * $pixel_density <= $resolution) $resolution_new = $resolution * ($item['val'] / 100);
                    if ($item['unit'] === 'px' AND $width * $pixel_density <= $resolution) $resolution_new = $item['val'] * $pixel_density;
                }
                if(isset($resolution_new)) $resolution = $resolution_new;
                $resolution = ceil($resolution);
            }
        }
    }

    /* No resolution was found (no cookie or invalid cookie) */
    if (!$resolution) {
        // We send the lowest resolution for mobile-first approach, and highest otherwise
        // $resolution = $is_mobile ? min($resolutions) : max($resolutions);
    
        foreach($setup[$_GET['size']]['breakpoints'] as $key => $value) {
            $array[] = strtr($value, array ('px'=>'','%'=>''));
        }
        $resolution = $is_mobile ? min($array) : max($array);
    }
    
    /* if the requested URL starts with a slash, remove the slash */
    if(substr($requested_uri, 0,1) == "/") {
        $requested_uri = substr($requested_uri, 1);
    }

    /* whew might the cache file be? */
    $ratio_slug = '';
    if( $setup_ratio_arr ) {
        $ratio_slug = '-' . $setup_ratio_arr[0] . '-' . $setup_ratio_arr[1];
    }
    
    $pixel_density_slug = '-' . $pixel_density;
     
    $cache_file = $document_root."/$cache_path/$resolution$pixel_density_slug$ratio_slug/".$requested_uri;
    
    
    
    /* Use the resolution value as a path variable and check to see if an image of the same name exists at that path */
    if (file_exists($cache_file)) { // it exists cached at that size
        if ($watch_cache) { // if cache watching is enabled, compare cache and source modified dates to ensure the cache isn't stale
            $cache_file = refreshCache($source_file, $cache_file, $resolution);
        }
        
        sendImage($cache_file, $browser_cache);
    }

    /* It exists as a source file, and it doesn't exist cached - lets make one: */
    $file = generateImage($source_file, $cache_file, $resolution);
    sendImage($file, $browser_cache);

    function UnsharpMask($img, $amount, $radius, $threshold) {

        /*
            New:  
            - In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements. 
            - From version 2 (July 17 2006) the script uses the imageconvolution function in PHP  
            version >= 5.1, which improves the performance considerably. 


            Unsharp masking is a traditional darkroom technique that has proven very suitable for  
            digital imaging. The principle of unsharp masking is to create a blurred copy of the image 
            and compare it to the underlying original. The difference in colour values 
            between the two images is greatest for the pixels near sharp edges. When this  
            difference is subtracted from the original image, the edges will be 
            accentuated.  

            The Amount parameter simply says how much of the effect you want. 100 is 'normal'. 
            Radius is the radius of the blurring circle of the mask. 'Threshold' is the least 
            difference in colour values that is allowed between the original and the mask. In practice 
            this means that low-contrast areas of the picture are left unrendered whereas edges 
            are treated normally. This is good for pictures of e.g. skin or blue skies. 

            Any suggenstions for improvement of the algorithm, expecially regarding the speed 
            and the roundoff errors in the Gaussian blur process, are welcome. 

        */

        ////////////////////////////////////////////////////////////////////////////////////////////////   
        ////   
        ////                  Unsharp Mask for PHP - version 2.1.1   
        ////   
        ////    Unsharp mask algorithm by Torstein Hønsi 2003-07.   
        ////             thoensi_at_netcom_dot_no.   
        ////               Please leave this notice.   
        ////   
        ///////////////////////////////////////////////////////////////////////////////////////////////   

        // $img is an image that is already created within php using  
        // imgcreatetruecolor. No url! $img must be a truecolor image.  

        // Attempt to calibrate the parameters to Photoshop:  
        if ($amount > 500)    $amount = 500;  
        $amount = $amount * 0.016;  
        if ($radius > 50)    $radius = 50;  
        $radius = $radius * 2;  
        if ($threshold > 255)    $threshold = 255;  

        $radius = abs(round($radius));     // Only integers make sense.  
        if ($radius == 0) {  
            return $img; imagedestroy($img); break;
        }  
        $w = imagesx($img); $h = imagesy($img);  
        $imgCanvas = imagecreatetruecolor($w, $h);  
        $imgBlur = imagecreatetruecolor($w, $h);  


        // Gaussian blur matrix:  
        //                          
        //    1    2    1          
        //    2    4    2          
        //    1    2    1          
        //                          
        //////////////////////////////////////////////////  


        if (function_exists('imageconvolution')) { // PHP >= 5.1   
            $matrix = array(   
            array( 1, 2, 1 ),   
            array( 2, 4, 2 ),   
            array( 1, 2, 1 )   
            );   
            imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h);  
            imageconvolution($imgBlur, $matrix, 16, 0);   
        }   
        else {   

            // Move copies of the image around one pixel at the time and merge them with weight  
            // according to the matrix. The same matrix is simply repeated for higher radii.  
            for ($i = 0; $i < $radius; $i++) {  
                imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left  
                imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right  
                imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center  
                imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);  

                imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up  
                imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down  
            }  
        }  

        if($threshold>0) {  
            // Calculate the difference between the blurred pixels and the original  
            // and set the pixels  
            for ($x = 0; $x < $w-1; $x++)    { // each row 
                for ($y = 0; $y < $h; $y++)    { // each pixel  

                    $rgbOrig = ImageColorAt($img, $x, $y);  
                    $rOrig = (($rgbOrig >> 16) & 0xFF);  
                    $gOrig = (($rgbOrig >> 8) & 0xFF);  
                    $bOrig = ($rgbOrig & 0xFF);  

                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);  

                    $rBlur = (($rgbBlur >> 16) & 0xFF);  
                    $gBlur = (($rgbBlur >> 8) & 0xFF);  
                    $bBlur = ($rgbBlur & 0xFF);  

                    // When the masked pixels differ less from the original  
                    // than the threshold specifies, they are set to their original value.  
                    $rNew = (abs($rOrig - $rBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))   
                    : $rOrig;  
                    $gNew = (abs($gOrig - $gBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))   
                    : $gOrig;  
                    $bNew = (abs($bOrig - $bBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))   
                    : $bOrig;  



                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {  
                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);  
                        ImageSetPixel($img, $x, $y, $pixCol);  
                    }  
                }  
            }  
        }  
        else {  
            for ($x = 0; $x < $w; $x++) { // each row  
                for ($y = 0; $y < $h; $y++) { // each pixel  
                    $rgbOrig = ImageColorAt($img, $x, $y);  
                    $rOrig = (($rgbOrig >> 16) & 0xFF);  
                    $gOrig = (($rgbOrig >> 8) & 0xFF);  
                    $bOrig = ($rgbOrig & 0xFF);  

                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);  

                    $rBlur = (($rgbBlur >> 16) & 0xFF);  
                    $gBlur = (($rgbBlur >> 8) & 0xFF);  
                    $bBlur = ($rgbBlur & 0xFF);  

                    $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;  
                    if($rNew>255){$rNew=255;}  
                    elseif($rNew<0){$rNew=0;}  
                    $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;  
                    if($gNew>255){$gNew=255;}  
                    elseif($gNew<0){$gNew=0;}  
                    $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;  
                    if($bNew>255){$bNew=255;}  
                    elseif($bNew<0){$bNew=0;}  
                    $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;  
                    ImageSetPixel($img, $x, $y, $rgbNew);  
                }  
            }  
        }  
        imagedestroy($imgCanvas);  
        imagedestroy($imgBlur);  

        return $img;
    }
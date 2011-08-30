<?php
$maxwidth = $_GET['maxwidth'];
if($maxwidth == "unknown") { $maxwidth = 3000; } // we need a number, so give it something unfeasable
setcookie('resolution',$maxwidth,time()+604800,'/'); // set the cookie

// respond with a (fake) blank image
header('content-type: image/jpeg');
exit();
?>
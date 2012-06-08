<?php
$maxwidth = isset($_GET['maxwidth']) ? intval($_GET['maxwidth']) : null;

if(null === $maxwidth || $maxwidth === "unknown") { $maxwidth = 3000; } // we need a number, so give it something unfeasable
setcookie('resolution',$maxwidth,time()+604800,'/'); // set the cookie

// respond with an empty content
header('HTTP/1.1 204 No Content');
exit();
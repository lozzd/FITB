<?php

include_once('functions.php');

# Set some defaults if they are not specified. 
if (isset($_GET['duration'])) {
    $start = $_GET['duration'];
} else {
    $start = "-86400";
}

if (isset($_GET['end'])) {
    $end = $_GET['end'];
} else {
    $end = "-60";
}

if (isset($_GET['type'])) {
    $graphtype = $_GET['type'];
} else {
    $graphtype = "bits";
}

if (isset($_GET['height'])) {
    $height = $_GET['height'];
} else {
    $height = 120;
}

if (isset($_GET['width'])) {
    $width = $_GET['width'];
} else {
    $width = 500;
}

$friendlytitle = "";
if (isset($_GET['friendlytitle'])) {
    $friendlytitle = $_GET['friendlytitle'];
}


$rrdtoolcmd = printRRDgraphcmd($_GET['rrdname'], $_GET['host'], $graphtype, $start, $end, $height, $width, $friendlytitle);


# Anti-caching techniques courtesy of Ganglia
header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");   // Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header ("Cache-Control: no-cache, must-revalidate");   // HTTP/1.1
header ("Pragma: no-cache");                     // HTTP/1.0

    
header ("Content-type: image/png");
passthru($rrdtoolcmd);


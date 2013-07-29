<?php

include_once('functions.php');

# Set some defaults if they are not specified. 
if (isset($_GET['debug']) && is_numeric($_GET['debug'])) {
  $debug =  $_GET['debug'];
} else {
  $debug = 0;
}
if (isset($_GET['duration'])) {
    $start = $_GET['duration'];
} else {
    $start = $default_duration;
}

if (isset($_GET['end'])) {
    $end = $_GET['end'];
} else {
    $end = "-60";
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

if (isset($_GET['aggregate_id'])) {
    $aggregate_id = $_GET['aggregate_id'];
    $data = getAggregateData($aggregate_id);
    $graphs_array = $data['graphs_array'];
    $meta = $data['meta'];
    $rrdtoolcmd = getStackedGraphsCmd($graphs_array, $meta['type'], $meta['stack'], $start, $end, $height, $width, $meta['friendlytitle']);

} else {
    $friendlytitle = "";
    if (isset($_GET['friendlytitle'])) {
        $friendlytitle = $_GET['friendlytitle'];
    }

    if (isset($_GET['count'])) {
        $graph_count = $_GET['count'];
    } else {
        $graph_count = 1;
    }

    if (isset($_GET['type'])) {
        $type = $_GET['type'];
    } else {
        $type = "bits";
    }

    if ($graph_count > 1) {    
        $graphs_array = getAggregateGraphsArrayFromRequest($_GET);
        $stack = !isset($_GET['stack']) || $_GET['stack'] != 'false';
        $rrdtoolcmd = getStackedGraphsCmd($graphs_array, $type, $stack, $start, $end, $height, $width, $friendlytitle);
    } else {
        $rrdname = $_GET['rrdname'];
        $rrdfolder = $_GET['host'];
        $rrdtoolcmd = getGraphCmd($rrdname, $rrdfolder, $type, $start, $end, $height, $width, $friendlytitle);
    }
}


# Anti-caching techniques courtesy of Ganglia

header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");   // Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header ("Cache-Control: no-cache, must-revalidate");   // HTTP/1.1
header ("Pragma: no-cache");                     // HTTP/1.0

header ("Content-type: image/png");

if ($debug >= 2) {
  header ("Content-type: text/html");
  echo $rrdtoolcmd;
}
else {
  if ($debug >= 1) {
    error_log("rrdtool graph command:" . $rrdtoolcmd);
  }
  passthru($rrdtoolcmd);
}


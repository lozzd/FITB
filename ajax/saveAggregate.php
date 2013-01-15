<?php
include_once('../functions.php');

header("Content-type: application/json");

$friendlytitle = "";
if (isset($_POST['friendlytitle'])) {
    $friendlytitle = $_POST['friendlytitle'];
}
$stack = !isset($_POST['stack']) || $_POST['stack'] !== 'false';

$type = $_POST['type'];

$graphs_array = getAggregateGraphsArrayFromRequest($_POST);

if (!is_null($graphs_array)) {
    $meta = array(
        'friendlytitle' => $friendlytitle,
        'type' => $type,
        'stack' => $stack
    );
    $agg_id = saveAggregate($graphs_array, $meta);
} else {
    $agg_id = null;
}

$success = !is_null($agg_id);

$res = array('success' => $success, 'aggregate_id' => $agg_id);
echo json_encode($res);

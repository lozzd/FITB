<?php
include_once('../functions.php');

header("Content-type: application/json");

$deleted = deleteAggregate($_GET['aggregate_id']);

$res = array('success' => $deleted);
echo json_encode($res);
<?php
include_once('../functions.php');

header("Content-type: application/json");

$ports = getPortsForHostAndType($_GET['host'], $_GET['type']);
echo json_encode($ports);

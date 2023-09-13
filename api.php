<?php
// Make sure the APP that requests data receives JSON-response
//------------------------------------------------------------------------------
global $connect, $data, $sql;
header('Content-Type: application/json');
//------------------------------------------------------------------------------

include ('config.php');

echo $connect->startResponse($data, $sql);

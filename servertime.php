<?php 

$timestamp = time();
echo "time: " . $timestamp;
$now = "1709108502"; 
$lastlogin = "1707388245"; 
$diff = $now - $lastlogin;
echo "string subraction : " . "now: " . $now . "lastlogin: ". $lastlogin . ", differenz = " . $diff; 
$var = (time()); 
echo "strtotime(time()) " . $var; 
<?php

$host="sql105.infinityfree.com";
$user="***********";
$pass="************";
$db="**********_wt_db";
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error){
    echo "Failed to connect DB".$conn->connect_error;
}
?>

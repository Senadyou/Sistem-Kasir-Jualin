<?php
date_default_timezone_set("Asia/Jakarta");

$host="localhost";
$user="root";
$password="";
$dbname="db_pos_multitoko";


$conn = new mysqli($host,$user,$password,$dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => false, "message" => "connection failed:" . $conn->connect_error]));
}

?>
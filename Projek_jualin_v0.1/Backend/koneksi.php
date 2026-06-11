<?php
date_default_timezone_set("Asia/Jakarta");

$host="sql108.infinityfree.com";
$user="if0_42036787";
$password="XQ1LYCYZeVWP";
$dbname="if0_42036787_db_pos_multitoko";


$conn = new mysqli($host,$user,$password,$dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => false, "message" => "connection failed:" . $conn->connect_error]));
}

?>
<?php
$host     = "localhost";
$user     = "root";
$password = "";
$dbname   = "db_pos_multitoko"; // ✅ multi-toko

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4"); // ✅ charset
?>

<?php
require "vendor/autoload.php";
use Firebase\JWT\JWT;

$key = "secret_key_jualin"; // ganti dengan key lebih aman
$payload = [
    "id" => $user['id'],
    "username" => $user['username'],
    "role" => $user['role'],
    "exp" => time() + 3600 // berlaku 1 jam
];

$jwt = JWT::encode($payload, $key, 'HS256');
echo json_encode(["token" => $jwt]);

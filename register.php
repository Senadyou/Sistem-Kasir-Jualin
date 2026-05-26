<?php
include "koneksi.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = $_POST['nama'];
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (NAMA, PASSWORD, ROLE) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama, $passwordHash, $role);

    if ($stmt->execute()) {
        echo "User berhasil ditambahkan!";
    } else {
        echo "Gagal menambahkan user: " . $conn->error;
    }
}
?>

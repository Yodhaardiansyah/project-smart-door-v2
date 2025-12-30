<?php
// config.php

// 🔧 Koneksi ke Database
$host = "localhost";
$user = "root";
$pass = "arunovasi@2025";
$db = "smart_door";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>

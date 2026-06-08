<?php
session_start();
if (isset($_SESSION['NAMA_USER'])) {
  echo "👋 Halo, " . htmlspecialchars($_SESSION['NAMA_USER']);
} else {
  echo "<span class='text-warning'>Belum login</span>";
}
?>

<?php
$conn = mysqli_connect("localhost", "root", "root123456", "guild");

if (!$conn) {
    die("資料庫連線失敗：" . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
?>
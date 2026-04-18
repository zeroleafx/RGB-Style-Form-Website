<?php
require_once "db.php";

$sql = "SELECT * FROM users";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("SQL錯誤：" . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($result)) {
    echo $row['username'] . "<br>";
}
?>
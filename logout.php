<?php
session_start();

// 清掉所有 session
session_unset();
session_destroy();

// 回到首頁
header("Location: index.php");
exit;
?>
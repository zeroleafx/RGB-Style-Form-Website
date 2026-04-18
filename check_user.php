<?php
require_once "db.php";

$username = $_POST['username'];

$sql = "SELECT * FROM users WHERE username='$username'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    echo "❌ Username is taken";
} else {
    if($username!='' && strlen($username)>=3)
        echo "✅ Username Available";
    else
        echo "❌ Username must be at least 3 characters";
}
?>
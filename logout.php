<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php"); // Make sure this points to your actual login page
exit();
?>
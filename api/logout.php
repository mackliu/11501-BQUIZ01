<?php 
include_once "db.php";
unset($_SESSION['login']);
to("../index.php?do=login");
?>

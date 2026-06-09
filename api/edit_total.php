<?php 
include_once "db.php";

$Total->save($_POST);

to("../admin.php?do=total");
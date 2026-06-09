<?php
include_once "db.php";

$table=$_GET['table'];
$db=${ucfirst($table)};

if(!empty($_FILES['img']['tmp_name'])){
    move_uploaded_file($_FILES['img']['tmp_name'],"../upload/{$_FILES['img']['name']}");

    $row=$db->find($_POST['id']);

    $row['img']=$_FILES['img']['name'];
    
    $db->save($row);
}

to("../admin.php?do=$table");
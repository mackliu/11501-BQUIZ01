<?php 
include_once "db.php";

$table=$_GET['table'];
$db=${ucfirst($table)};

if(!empty($_FILES['img']['tmp_name'])){
    move_uploaded_file($_FILES['img']['tmp_name'],"../upload/".$_FILES['img']['name']);
    $_POST['img']=$_FILES['img']['name'];
    
    }
    switch($table){
        case 'title':
            $_POST['sh']=0;
        break;
        case 'admin':
            unset($_POST['pw2']);
        break;
        case "menu":
            $_POST['main_id']=0;
            $_POST['sh']=1;
        break;
        default:
            $_POST['sh']=1;
    }

    $db->save($_POST);
    

to("../admin.php?do=$table");
<?php 
include_once "db.php";

$table=$_GET['table'];
$db=${ucfirst($table)};

foreach($_POST['id'] as $idx => $id){
    if(isset($_POST['del']) && in_array($id,$_POST['del'])){
        $db->del($id);
    }else{
        $row=$db->find($id);

        switch($table){
            case "title":
                $row['text']=$_POST['text'][$idx];
                $row['sh']=(isset($_POST['sh']) && $_POST['sh']==$id)?1:0;
            break;
            case "ad":
            case "news":
                $row['text']=$_POST['text'][$idx];
                $row['sh']=(isset($_POST['sh']) && in_array($id,$_POST['sh']))?1:0;
            break;
            case "mvim":
            case "image":
                $row['sh']=(isset($_POST['sh']) && in_array($id,$_POST['sh']))?1:0;
            break;
            case "admin":
                $row['acc']=$_POST['acc'][$idx];
                $row['pw']=$_POST['pw'][$idx];
            break;
            case "menu":
                $row['href']=$_POST['href'][$idx];
                $row['text']=$_POST['text'][$idx];
                $row['sh']=(isset($_POST['sh']) && in_array($id,$_POST['sh']))?1:0;
            break;

        }
        

        
        $db->save($row);
    }

}

to("../admin.php?do=$table");
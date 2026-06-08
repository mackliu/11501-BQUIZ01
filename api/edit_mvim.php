<?php 
include_once "db.php";

foreach($_POST['id'] as $idx => $id){
    if(isset($_POST['del']) && in_array($id,$_POST['del'])){
        $Mvim->del($id);
    }else{
        $row=$Mvim->find($id);
        $row['sh']=(isset($_POST['sh']) && in_array($id,$_POST['sh']))?1:0;

        $Mvim->save($row);
    }

}

to("../admin.php?do=mvim");
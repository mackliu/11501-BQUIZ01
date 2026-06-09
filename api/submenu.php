<?php 
include_once "db.php";
//編輯與刪除
foreach($_POST['id'] as $idx => $id){
    if(isset($_POST['del']) && in_array($id,$_POST['del'])){
        $Menu->del($id);
    }else{
        $row=$Menu->find($id);
        $row['text']=$_POST['text'][$idx];
        $row['href']=$_POST['href'][$idx];
        $Menu->save($row);
    }

}

//新增
if(isset($_POST['text2'])){
    foreach($_POST['text2'] as $idx => $text){
        if(!empty($text)){
            
            $Menu->save([
                'text'=>$text,
                'href'=>$_POST['href2'][$idx],
                'sh'=>1,
                'main_id'=>$_POST['main_id']
            ]);
        }
    }
}

to("../admin.php?do=menu");
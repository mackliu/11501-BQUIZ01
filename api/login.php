<?php 
include_once "db.php";
if(isset($_POST['acc'])){
	if($Admin->count($_POST)>0){
		$_SESSION['login']=1;
		to("../admin.php");
	}else{
		echo "<script>";
		echo "alert('帳號或密碼錯誤');";
        echo "location.href='../index.php?do=login'";
		echo "</script>";
	}
}

echo "HI";
?>

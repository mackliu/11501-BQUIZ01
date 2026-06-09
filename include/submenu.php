<?php include_once "../api/db.php";?>
<h3 class="cent">編輯次選單</h3>
<hr>
<form action="api/submenu.php?table=menu" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;" id="subMenu">
        <tr>
            <td class="tt">次選單名稱</td>
            <td class="tt">次選單連結網址</td>
            <td class="tt">刪除</td>
        </tr>
        <?php 
        if($Menu->count(['main_id'=>$_GET['id']])>0):
            $rows=$Menu->all(['main_id'=>$_GET['id']]);
            foreach($rows as $row):
        ?>
        <tr>
            <td><input type="text" name="text[]" value="<?= $row['text']; ?>"></td>
            <td><input type="text" name="href[]" value="<?= $row['href']; ?>"></td>
            <td><input type="checkbox" name="del[]" value="<?= $row['id']; ?>"></td>
            <input type="hidden" name="id[]" value="<?= $row['id']; ?>">
        </tr>
        <?php
            endforeach;
            endif;
        ?>
    </table>
    <div class="cent">
        <input type="hidden" name="main_id" value="<?= $_GET['id']; ?>">
        <input type="submit" value="修改確定">
        <input type="reset" value="重置">
        <input type="button" value="更多次選單" onclick="more()">
    </div>
    </form>
<script>
    
function more(){
    let row=`<tr>
                <td><input type="text" name="text2[]"></td>
                <td><input type="text" name="href2[]"></td>
                <td></td>
            </tr>`
     $('#subMenu').append(row)


}
</script>
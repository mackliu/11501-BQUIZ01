<h3 class="cent">新增網站標題圖片</h3>
<hr>
<form action="api/add.php?table=title" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">網站標題圖片：</td>
            <td><input type="file" name="img"></td>
        </tr>
        <tr>
            <td class="tt">替代文字：</td>
            <td><input type="text" name="text"></td>
        </tr>
    </table>
    <div class="cent">
        <input type="submit" value="新增">
        <input type="reset" value="重置">
    </div>
</form>
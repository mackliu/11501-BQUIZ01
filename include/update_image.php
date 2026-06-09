<h3 class="cent">更換校園映像圖片</h3>
<hr>

<form action="api/update.php?table=image" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">校園映像圖片：</td>
            <td><input type="file" name="img"></td>
        </tr>
    </table>
    <div class="cent">
    <div class="cent">
        <input type="hidden" name="id" value="<?= $_GET['id'];?>">
        <input type="submit" value="更新">
        <input type="reset" value="重置">
    </div>
</form>
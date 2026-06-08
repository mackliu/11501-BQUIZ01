<h3 class="cent">新增校園映像資料</h3>
<hr>
<form action="api/add.php?table=image" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">校園映像資料：</td>
            <td><input type="file" name="img"></td>
        </tr>
    </table>
    <div class="cent">
        <input type="submit" value="新增">
        <input type="reset" value="重置">
    </div>
</form>
<h3 class="cent">新增最新消息資料</h3>
<hr>
<form action="api/add.php?table=news" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">最新消息資料：</td>
            <td><textarea name="text" style="width:300px;height:200px;"></textarea></td>
        </tr>
    </table>
    <div class="cent"><input type="submit" value="新增"><input type="reset" value="重置"></div>
    </form>
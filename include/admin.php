<h3 class="cent">新增管理者帳號</h3>
<hr>
<form action="api/add.php?table=admin" method="post" enctype="multipart/form-data">
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">帳號：</td>
            <td><input type="text" name="acc"></td>
        </tr>
        <tr>
            <td class="tt">密碼：</td>
            <td><input type="password" name="pw"></td>
        </tr>
        <tr>
            <td class="tt">確認密碼：</td>
            <td><input type="password" name="pw2"></td>
        </tr>
    </table>
    <div class="cent"><input type="submit" value="新增"><input type="reset" value="重置"></div>
    </form>
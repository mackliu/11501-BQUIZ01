<div class="di"
    style="height:540px; border:#999 1px solid; width:76.5%; margin:2px 0px 0px 0px; float:left; position:relative; left:20px;">
    <!--正中央-->
    <?php include "back_header.php";?>
    <div style="width:99%; height:87%; margin:auto; overflow:auto; border:#666 1px solid;">
        <p class="t cent botli">進站總人數管理</p>
        <form method="post" action="./api/edit_value.php?table=<?= $do ?>">
            <table width="50%" style="margin:auto">
                <tbody>
                    <tr class="yel">
                        <td width="50%">進站總人數</td>
                        <td width="50%">
                            <input type="number" name='total' value="<?= $Total->find(1)['total'] ?>">
                            <input type="hidden" name="id" value='1'>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table style="margin-top:40px; width:70%;">
                <tbody>
                    <tr>
                        <td width="200px"></td>
                        <td class="cent">
                            <input type="submit" value="修改確定">
                            <input type="reset" value="重置">
                        </td>
                    </tr>
                </tbody>
            </table>

        </form>
    </div>
</div>
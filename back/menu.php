
<div class="di"
    style="height:540px; border:#999 1px solid; width:76.5%; margin:2px 0px 0px 0px; float:left; position:relative; left:20px;">
    <!--正中央-->
    <table width="100%">
        <tbody>
            <tr>
                <td style="width:70%;font-weight:800; border:#333 1px solid; border-radius:3px;" class="cent">
                    <a href="?do=admin" style="color:#000; text-decoration:none;">後台管理區</a>
                </td>
                <td>
                    <button onclick="document.cookie=&#39;user=&#39;;location.replace(&#39;index.php&#39;)"
                        style="width:99%; margin-right:2px; height:50px;">管理登出</button>
                </td>
            </tr>
        </tbody>
    </table>
    <div style="width:99%; height:87%; margin:auto; overflow:auto; border:#666 1px solid;">
        <p class="t cent botli">選單管理</p>
        <form method="post" action="./api/edit.php?table=<?= $do ?>">
            <table width="100%">
                <tbody>
                    <tr class="yel">
                        <td width="30%">主選單名稱</td>
                        <td width="30%">選單連結網址</td>
                        <td width="10%">次選單數</td>
                        <td width="10%">顯示</td>
                        <td width="10%">刪除</td>
                        <td width="10%"></td>
                    </tr>
                    <?php 
                    $db=${ucfirst($do)};
                    $rows=$db->all(['main_id'=>0]);
                    foreach($rows as $row):
                    ?>
                    <tr>
                        <td>
                            <input type="text" name="text[]" value="<?= $row['text']; ?>" style="width:95%">
                        </td>
                        <td>
                            <input type="text" name="href[]" value="<?= $row['href']; ?>">
                        </td>
                        <td><?= $Menu->count(['main_id'=>$row['id']]); ?></td>
                        <td>
                            <input type="checkbox" name="sh[]" value="<?= $row['id']; ?>"  <?= ($row['sh']==1)?'checked':''; ?> >
                        </td>                        
                        <td>
                            <input type="checkbox" name="del[]" value="<?= $row['id']; ?>">
                        </td>
                        <td>
                            <input type="button" value="編輯次選單"  onclick="op('#cover','#cvr','include/submenu.php?id=<?= $row['id'];?>')">
                        </td>
                        <input type="hidden" name="id[]" value="<?= $row['id']; ?>">
                    </tr>
                    <?php
                    endforeach;
                    ?>
                </tbody>
            </table>
            <table style="margin-top:40px; width:70%;">
                <tbody>
                    <tr>
                        <td width="200px">
                            <input type="button" onclick="op('#cover','#cvr','include/<?= $do; ?>.php')" value="新增主選單">
                        </td>
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
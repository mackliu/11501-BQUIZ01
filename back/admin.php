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
                    <form method="post" action="./api/logout.php" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" style="width:99%; margin-right:2px; height:50px;">管理登出</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
    <div style="width:99%; height:87%; margin:auto; overflow:auto; border:#666 1px solid;">
        <p class="t cent botli">管理者帳號管理</p>
        <form method="post" action="./api/edit.php?table=<?= e($do) ?>">
            <?= csrf_field() ?>
            <table width="100%">
                <tbody>
                    <tr class="yel">
                        <td width="45%">帳號</td>
                        <td width="45%">新密碼（不修改請留空）</td>
                        <td width="10%">刪除</td>
                    </tr>
                    <?php
                    // admin 表不在 table() 的一般白名單內，需明確指定 allowAdmin
                    $db   = table('admin', true);
                    $rows = $db->all();
                    foreach($rows as $row):
                        // id=1 為系統預設管理員，不可由此頁修改或刪除
                        if((int)$row['id'] !== 1):
                    ?>
                    <tr>
                        <td width="45%">
                            <input type="text" name="acc[]" value="<?= e($row['acc']); ?>" style="width:95%">
                        </td>
                        <td width="45%">
                            <!--
                              舊版此欄會把 pw 欄位的值直接輸出到 value 屬性，
                              等於把明文密碼寫進 HTML 原始碼（對應 A04-2）。
                              改為雜湊儲存後更不能回填 —— 回填的雜湊會被再次雜湊，
                              密碼就壞了。因此固定留空，只有實際填入才更新。
                            -->
                            <input type="password" name="pw[]" value="" autocomplete="new-password" placeholder="留空表示不變更">
                        </td>
                        <td width="10%">
                            <input type="checkbox" name="del[]" value="<?= e($row['id']); ?>">
                        </td>
                        <input type="hidden" name="id[]" value="<?= e($row['id']); ?>">
                    </tr>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </tbody>
            </table>
            <table style="margin-top:40px; width:70%;">
                <tbody>
                    <tr>
                        <td width="200px">
                            <input type="button" onclick="op('#cover','#cvr','include/<?= e($do); ?>.php')" value="新增管理者帳號">
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
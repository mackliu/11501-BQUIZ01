<?php
/**
 * 於 2026-08-14 的安全修復中加上守門。
 *
 * 本檔是由後台以 AJAX 載入的表單片段，但它同時也是一個可以被直接
 * 開啟的 URL（例如 http://站台/include/xxx.php）。舊版完全沒有驗證，
 * 未登入者即可取得後台表單結構（對應 A01-3）。
 */
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/csrf.php';
require_login();
?>
<h3 class="cent">新增最新消息資料</h3>
<hr>
<form action="api/add.php?table=news" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">最新消息資料：</td>
            <td><textarea name="text" style="width:300px;height:200px;"></textarea></td>
        </tr>
    </table>
    <div class="cent"><input type="submit" value="新增"><input type="reset" value="重置"></div>
    </form>
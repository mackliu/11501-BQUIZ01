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
<h3 class="cent">進站總人數管理</h3>
<hr>
<!--
  舊版此表單的欄位名為 text，但 total 資料表根本沒有 text 欄位，
  標籤文字也誤植為「動態文字廣告」——是複製貼上留下的殘骸，送出必定失敗。
  一併修正為正確的欄位與端點。
-->
<form action="api/edit_value.php?table=total" method="post">
    <?= csrf_field() ?>
    <table class="all" style="width:70%; margin:auto;">
        <tr>
            <td class="tt">進站總人數：</td>
            <td><input type="number" name="total" min="0" value="<?= e($Total->find(1)['total'] ?? 0) ?>"></td>
        </tr>
    </table>
    <div class="cent"><input type="submit" value="修改確定"><input type="reset" value="重置"></div>
    </form>
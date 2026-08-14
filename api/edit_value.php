<?php
/**
 * 修改單一設定值（頁尾版權文字 / 進站總人數）
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A01-2  加入 require_login()。舊版未登入即可送出
 *          api/edit_value.php?table=bottom 竄改全站頁尾文字。
 *   A06-2  加入 CSRF token 驗證
 *   A05-6  移除可變變數，改為白名單查表
 *   A05-1  逐欄位取值後以 prepared statement 更新，不再 save($_POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
csrf_check();

$table = (string) ($_GET['table'] ?? '');

switch ($table) {

    case 'bottom':
        $value = ['bottom' => trim((string) ($_POST['bottom'] ?? ''))];
        break;

    case 'total':
        // 人數必為非負整數
        $value = ['total' => max(0, (int) ($_POST['total'] ?? 0))];
        break;

    default:
        app_log('edit_value.illegal_table', ['table' => $table]);
        app_fail(400, "不允許修改的資料表：{$table}");
}

// 這兩張表都只有 id=1 一筆設定資料
table($table)->update(1, $value);

app_log('setting.updated', ['table' => $table]);

to("/admin.php?do={$table}");

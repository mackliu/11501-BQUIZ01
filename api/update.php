<?php
/**
 * 更換既有記錄的圖片
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A01-2  加入 require_login()
 *   A06-2  加入 CSRF token 驗證
 *   A05-6  移除可變變數，改為白名單查表
 *   A06-1  上傳改走 save_uploaded_image()
 *   A05-1  id 強制轉型為整數，以 prepared statement 更新
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';

require_login();
csrf_check();

const UPDATE_ALLOWED_TABLES = ['title', 'mvim', 'image'];

$table = (string) ($_GET['table'] ?? '');
if (!in_array($table, UPDATE_ALLOWED_TABLES, true)) {
    app_log('update.illegal_table', ['table' => $table]);
    app_fail(400, "不允許更換圖片的資料表：{$table}");
}

$db = table($table);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    app_fail(400, '缺少有效的資料編號。');
}

if ($db->find($id) === null) {
    app_fail(404, "找不到編號 {$id} 的資料。", '找不到指定的資料。');
}

$img = save_uploaded_image($_FILES['img'] ?? null);
if ($img === null) {
    app_fail(400, '請選擇要上傳的圖片。', '請選擇要上傳的圖片。');
}

$db->update($id, ['img' => $img]);

app_log('record.image_updated', ['table' => $table, 'id' => $id, 'img' => $img]);

to("/admin.php?do={$table}");

<?php
/**
 * 新增資料
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A01-2  加入 require_login()。舊版未登入即可呼叫
 *          api/add.php?table=admin 直接建立自己的管理帳號。
 *   A06-2  加入 CSRF token 驗證。
 *   A05-6  移除 $db = ${ucfirst($table)}（可變變數），改為白名單查表。
 *   A05-1  不再把整包 $_POST 丟進 SQL，改為逐欄位取出並經 prepared statement 寫入。
 *   A06-1  檔案上傳改走 save_uploaded_image()，做型別 / 大小驗證並由伺服器產生檔名。
 *   A04-1  admin 帳號的密碼以 password_hash() 儲存。
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';

require_login();
csrf_check();

const ADD_ALLOWED_TABLES = ['title', 'ad', 'mvim', 'image', 'news', 'menu', 'admin'];

$table = (string) ($_GET['table'] ?? '');
if (!in_array($table, ADD_ALLOWED_TABLES, true)) {
    app_log('add.illegal_table', ['table' => $table]);
    app_fail(400, "不允許新增的資料表：{$table}");
}

$db   = table($table, $table === 'admin');
$data = [];

switch ($table) {

    case 'title':
        $img = save_uploaded_image($_FILES['img'] ?? null);
        if ($img === null) {
            app_fail(400, '請選擇要上傳的標題圖片。', '請選擇要上傳的標題圖片。');
        }
        $data = [
            'img'  => $img,
            'text' => trim((string) ($_POST['text'] ?? '')),
            'sh'   => 0,   // 新增的標題預設不顯示，需另行勾選
        ];
        break;

    case 'image':
    case 'mvim':
        $img = save_uploaded_image($_FILES['img'] ?? null);
        if ($img === null) {
            app_fail(400, '請選擇要上傳的圖片。', '請選擇要上傳的圖片。');
        }
        $data = ['img' => $img, 'sh' => 1];
        break;

    case 'ad':
    case 'news':
        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            app_fail(400, '內容不可為空白。', '內容不可為空白。');
        }
        $data = ['text' => $text, 'sh' => 1];
        break;

    case 'menu':
        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            app_fail(400, '選單名稱不可為空白。', '選單名稱不可為空白。');
        }
        $data = [
            'text'    => $text,
            'href'    => trim((string) ($_POST['href'] ?? '')),
            'sh'      => 1,
            'main_id' => 0,
        ];
        break;

    case 'admin':
        $acc = trim((string) ($_POST['acc'] ?? ''));
        $pw  = (string) ($_POST['pw'] ?? '');
        $pw2 = (string) ($_POST['pw2'] ?? '');

        if ($acc === '') {
            app_fail(400, '帳號不可為空白。', '帳號不可為空白。');
        }
        // 舊版直接 unset($_POST['pw2'])，從未比對過確認密碼。
        // 密碼改為雜湊儲存後，打錯了就再也看不到，因此這裡必須擋下來。
        if ($pw !== $pw2) {
            app_fail(400, '兩次輸入的密碼不一致。', '兩次輸入的密碼不一致。');
        }
        if (strlen($pw) < 8) {
            app_fail(400, '密碼長度至少 8 個字元。', '密碼長度至少 8 個字元。');
        }
        if ($db->find(['acc' => $acc]) !== null) {
            app_fail(409, '此帳號已存在。', '此帳號已存在。');
        }

        $data = [
            'acc' => $acc,
            'pw'  => password_hash($pw, PASSWORD_DEFAULT),
        ];
        break;
}

$newId = $db->insert($data);

app_log('record.created', ['table' => $table, 'id' => $newId]);

to("/admin.php?do={$table}");

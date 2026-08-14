<?php
/**
 * 批次修改 / 刪除
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A01-2  加入 require_login()。舊版未登入即可送出
 *          id[]=1&acc[]=admin&pw[]=xxx 改掉主管理員密碼，或 del[]=1 直接刪除。
 *   A01-5  「不可修改預設管理員」的規則改為在伺服端強制執行。
 *          舊版只是在 back/admin.php 不顯示那一列，API 完全沒有把關。
 *   A06-2  加入 CSRF token 驗證。
 *   A05-6  移除可變變數，改為白名單查表。
 *   A05-1  逐欄位取值後以 prepared statement 更新。
 *   A04-1  admin 密碼以 password_hash() 儲存，且留空表示不變更。
 *   A10-3  檢查 $_POST['id'] 確實存在且為陣列，不再直接 foreach。
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
csrf_check();

const EDIT_ALLOWED_TABLES = ['title', 'ad', 'mvim', 'image', 'news', 'menu', 'admin'];

/** 系統預設管理員，不可由後台修改或刪除，避免把自己鎖在門外 */
const PROTECTED_ADMIN_ID = 1;

$table = (string) ($_GET['table'] ?? '');
if (!in_array($table, EDIT_ALLOWED_TABLES, true)) {
    app_log('edit.illegal_table', ['table' => $table]);
    app_fail(400, "不允許修改的資料表：{$table}");
}

$db = table($table, $table === 'admin');

$ids = $_POST['id'] ?? [];
if (!is_array($ids)) {
    app_fail(400, 'id 參數格式錯誤。');
}

// 勾選要刪除的 id
$delete = array_map('intval', (array) ($_POST['del'] ?? []));

// 勾選要顯示的 id。title 用 radio（單值），其餘用 checkbox（陣列）
$shownRaw   = $_POST['sh'] ?? [];
$shownIds   = array_map('intval', is_array($shownRaw) ? $shownRaw : [$shownRaw]);

$changed = 0;
$removed = 0;

foreach ($ids as $idx => $rawId) {
    $id = (int) $rawId;
    if ($id <= 0) {
        continue;
    }

    // 伺服端強制保護預設管理員
    if ($table === 'admin' && $id === PROTECTED_ADMIN_ID) {
        app_log('edit.protected_admin_blocked', ['id' => $id]);
        continue;
    }

    // --- 刪除 ---
    if (in_array($id, $delete, true)) {
        $db->del($id);
        $removed++;
        continue;
    }

    // --- 修改 ---
    $update = [];

    switch ($table) {

        case 'title':
            $update['text'] = trim((string) ($_POST['text'][$idx] ?? ''));
            // radio：只有被選中的那一筆為 1
            $update['sh'] = in_array($id, $shownIds, true) ? 1 : 0;
            break;

        case 'ad':
        case 'news':
            $update['text'] = trim((string) ($_POST['text'][$idx] ?? ''));
            $update['sh']   = in_array($id, $shownIds, true) ? 1 : 0;
            break;

        case 'mvim':
        case 'image':
            $update['sh'] = in_array($id, $shownIds, true) ? 1 : 0;
            break;

        case 'menu':
            $update['text'] = trim((string) ($_POST['text'][$idx] ?? ''));
            $update['href'] = trim((string) ($_POST['href'][$idx] ?? ''));
            $update['sh']   = in_array($id, $shownIds, true) ? 1 : 0;
            break;

        case 'admin':
            $acc = trim((string) ($_POST['acc'][$idx] ?? ''));
            if ($acc !== '') {
                $update['acc'] = $acc;
            }
            // 密碼欄留空 = 不變更。有填才重新雜湊。
            $pw = (string) ($_POST['pw'][$idx] ?? '');
            if ($pw !== '') {
                if (strlen($pw) < 8) {
                    app_fail(400, '密碼長度至少 8 個字元。', '密碼長度至少 8 個字元。');
                }
                $update['pw'] = password_hash($pw, PASSWORD_DEFAULT);
            }
            break;
    }

    if ($update !== []) {
        $db->update($id, $update);
        $changed++;
    }
}

app_log('record.edited', ['table' => $table, 'changed' => $changed, 'removed' => $removed]);

to("/admin.php?do={$table}");

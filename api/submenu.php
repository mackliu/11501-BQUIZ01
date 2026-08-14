<?php
/**
 * 次選單的批次修改 / 刪除 / 新增
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A01-2  加入 require_login()
 *   A06-2  加入 CSRF token 驗證
 *   A05-1  id 與 main_id 強制轉型為整數，逐欄位以 prepared statement 寫入
 *   A10-3  檢查 $_POST['id'] 確實存在且為陣列，不再直接 foreach
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
csrf_check();

$Menu = table('menu');

// ---------------------------------------------------------------
// 修改與刪除既有的次選單
// ---------------------------------------------------------------
$ids = $_POST['id'] ?? [];
if (!is_array($ids)) {
    app_fail(400, 'id 參數格式錯誤。');
}

$delete = array_map('intval', (array) ($_POST['del'] ?? []));

foreach ($ids as $idx => $rawId) {
    $id = (int) $rawId;
    if ($id <= 0) {
        continue;
    }

    if (in_array($id, $delete, true)) {
        $Menu->del($id);
        continue;
    }

    $Menu->update($id, [
        'text' => trim((string) ($_POST['text'][$idx] ?? '')),
        'href' => trim((string) ($_POST['href'][$idx] ?? '')),
    ]);
}

// ---------------------------------------------------------------
// 新增次選單
// ---------------------------------------------------------------
$mainId = (int) ($_POST['main_id'] ?? 0);

if ($mainId > 0 && isset($_POST['text2']) && is_array($_POST['text2'])) {
    // 確認父選單確實存在，避免掛在不存在的 main_id 底下
    if ($Menu->find($mainId) === null) {
        app_fail(400, "找不到編號 {$mainId} 的主選單。", '找不到指定的主選單。');
    }

    foreach ($_POST['text2'] as $idx => $rawText) {
        $text = trim((string) $rawText);
        if ($text === '') {
            continue;
        }
        $Menu->insert([
            'text'    => $text,
            'href'    => trim((string) ($_POST['href2'][$idx] ?? '')),
            'sh'      => 1,
            'main_id' => $mainId,
        ]);
    }
}

app_log('submenu.saved', ['main_id' => $mainId]);

to('/admin.php?do=menu');

<?php
/**
 * CSRF（跨站請求偽造）防護
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。對應 A06-2。
 *
 * 舊版所有表單都沒有 token，伺服端也沒有任何驗證。管理員只要在登入狀態下
 * 瀏覽一個惡意頁面，該頁面就能代替他送出 api/add.php?table=admin，
 * 在無感知的情況下建立攻擊者的管理帳號。
 *
 * 防護由兩層組成：
 *   1. 同步器 token（本檔）—— 主要防線
 *   2. SameSite=Lax cookie（見 api/bootstrap.php）—— 第二道防線
 *
 * 用法：
 *   表單內：  <?= csrf_field() ?>
 *   API 開頭：csrf_check();
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** 取得目前 session 的 CSRF token，沒有就產生一組 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** 產生可直接插入表單的隱藏欄位 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * 驗證請求所帶的 token。
 *
 * 一併強制要求 POST —— 具有副作用的操作不該能用 GET 觸發，
 * 否則一個 <img src="..."> 就能造成資料異動。
 */
function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        app_log('csrf.non_post', ['method' => $_SERVER['REQUEST_METHOD'] ?? '-']);
        app_fail(405, '此端點只接受 POST 請求。', '請求方式不正確。');
    }

    $sent = $_POST['_token'] ?? '';

    // hash_equals 為時間恆定比較，避免以回應時間逐字元猜出 token
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        app_log('csrf.mismatch', ['referer' => $_SERVER['HTTP_REFERER'] ?? '-']);
        // 用 403 而不是 Laravel 慣用的 419 —— 419 不是 IANA 註冊的狀態碼，
        // Apache 會把它降級成 500，反而蓋掉真正的失敗原因。
        app_fail(403, 'CSRF token 驗證失敗。請回上一頁重新整理後再送出。', '連線已逾時，請重新操作。');
    }
}

<?php
/**
 * 全站共用啟動程序
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。所有進入點都會先載入本檔。
 *
 * 負責：
 *   - 載入 api/config.php（憑證不再寫死在程式碼裡，對應 A02-1）
 *   - 設定錯誤與例外處理，避免把絕對路徑、SQL 語句吐給使用者（對應 A10-1）
 *   - 以安全的 cookie 屬性啟動 session（HttpOnly / SameSite，強化 A06-2 的 CSRF 防護）
 *   - 提供輸出跳脫、稽核日誌、安全轉址等共用工具
 */

declare(strict_types=1);

if (defined('WEB21_BOOTSTRAPPED')) {
    return;
}
define('WEB21_BOOTSTRAPPED', true);

// ===================================================================
// 設定
// ===================================================================

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('缺少 api/config.php，請複製 api/config.sample.php 後填入連線設定。');
        }
        $config = require $file;
    }

    return $config;
}

// ===================================================================
// 日誌（對應 A09-1 的最小實作；完整稽核日誌屬第 2 階段待辦）
// ===================================================================

function app_log(string $event, array $context = []): void
{
    $line = json_encode([
        'ts'    => date('c'),
        'event' => $event,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '-',
        'uri'   => $_SERVER['REQUEST_URI'] ?? '-',
        'admin' => $_SESSION['admin_id'] ?? null,
        'ctx'   => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        return;
    }

    $file = app_config()['log_file'];
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ===================================================================
// 錯誤處理
// ===================================================================

/**
 * 中止請求並顯示訊息。
 * 正式環境（env=prod）只顯示通用訊息，不洩漏內部細節。
 */
function app_fail(int $status, string $devMessage, string $userMessage = '系統發生錯誤，請稍後再試。'): never
{
    http_response_code($status);
    $show = app_config()['env'] === 'prod' ? $userMessage : $devMessage;

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>錯誤</title>';
    echo '<div style="font:16px/1.8 sans-serif;margin:60px auto;max-width:640px">';
    echo '<h2>發生錯誤</h2><p>' . e($show) . '</p></div>';
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    app_log('uncaught_exception', [
        'type'    => get_class($e),
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    app_fail(500, get_class($e) . ': ' . $e->getMessage());
});

// 正式環境不要把警告訊息印到畫面上（會洩漏絕對路徑）
if (app_config()['env'] === 'prod') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
error_reporting(E_ALL);

// ===================================================================
// Session
// ===================================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        // HTTPS 上線後請在 config 把 cookie_secure 改為 true
        'secure'   => app_config()['cookie_secure'],
        // 阻擋 JavaScript 讀取 session cookie
        'httponly' => true,
        // 跨站送出的請求不帶 cookie，作為 CSRF token 之外的第二道防線
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ===================================================================
// 輸出跳脫
// ===================================================================

/**
 * HTML 輸出跳脫。
 * 注意：這是「輸出時」的防護，不是 SQL 的防護 ——
 * SQL 的防護一律由 prepared statement 負責（見 api/db.php）。
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ===================================================================
// 轉址
// ===================================================================

/**
 * 站內轉址。
 *
 * 舊版的 to() 沒有 exit，導致 header() 之後的程式仍會執行 ——
 * 這正是「導向了但沒擋住」這類存取控制繞過的成因（對應 A07-2）。
 * 同時限制只能導向站內路徑，避免開放式重新導向與標頭注入。
 */
function to(string $path): never
{
    // 去掉換行，阻擋 HTTP 標頭注入
    $path = str_replace(["\r", "\n"], '', $path);

    // 只允許相對路徑；出現 scheme 或 // 開頭一律導回首頁
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path) || str_starts_with($path, '//')) {
        $path = '/index.php';
    }

    header('Location: ' . $path, true, 302);
    exit;
}

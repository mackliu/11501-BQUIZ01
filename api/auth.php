<?php
/**
 * 身分驗證
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。
 *
 * 修復項目：
 *   A01-1  後台首頁完全沒有登入檢查
 *   A01-2  所有 api/*.php 寫入端點沒有身分驗證
 *
 * 舊版把 $_SESSION['login']=1 寫進 session 後，全專案沒有任何一行讀取它，
 * 等於認證機制只寫不讀 —— 任何人直接開 admin.php 就是管理員。
 *
 * 用法：在需要保護的檔案最上方
 *     require_once __DIR__ . '/auth.php';
 *     require_login();
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** 目前是否為已登入狀態 */
function is_logged_in(): bool
{
    return !empty($_SESSION['login']) && !empty($_SESSION['admin_id']);
}

/** 取得目前登入者的 id，未登入時為 null */
function current_admin_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['admin_id'] : null;
}

/**
 * 要求必須登入，否則導回登入頁。
 * 務必記得：header() 之後一定要 exit（to() 內已含 exit）。
 */
function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    app_log('auth.denied', ['target' => $_SERVER['REQUEST_URI'] ?? '-']);

    // AJAX 片段（include/*.php）不做轉址，直接回 401，避免把登入頁塞進彈出視窗
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
        echo '<p style="padding:20px">登入狀態已失效，請重新登入。</p>';
        exit;
    }

    to('/index.php?do=login');
}

/**
 * 建立登入狀態。
 *
 * session_regenerate_id(true) 用於防止 Session Fixation（對應 A07-1）：
 * 攻擊者事先取得的 session id 在登入後即失效。
 */
function login_as(array $adminRow): void
{
    session_regenerate_id(true);

    $_SESSION['login']     = 1;
    $_SESSION['admin_id']  = (int) $adminRow['id'];
    $_SESSION['admin_acc'] = (string) $adminRow['acc'];
    $_SESSION['login_at']  = time();

    // 換發 CSRF token，避免沿用登入前的值
    unset($_SESSION['csrf']);
}

/** 完整登出：清空 session 資料、刪除 cookie、銷毀 session */
function logout(): void
{
    app_log('auth.logout', ['acc' => $_SESSION['admin_acc'] ?? null]);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

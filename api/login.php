<?php
/**
 * 管理員登入
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 修復項目：
 *   A05-1  SQL Injection —— 舊版把整個 $_POST 交給 $Admin->count($_POST)，
 *          攻擊者只要多送一個參數 z=1' OR '1'='1 就能無帳密登入。
 *          現在改為以 acc 單一欄位做 prepared 查詢，再用 password_verify 比對。
 *   A04-1  密碼明文 —— 改用 password_hash / password_verify，
 *          並在舊帳號首次登入成功時自動升級為雜湊。
 *   A06-2  加入 CSRF token 驗證。
 *   A07-1  登入成功後 session_regenerate_id()，防止 Session Fixation。
 *   A07-4  強制 POST，並修正「在 isset() 之前就存取 $_POST」的問題。
 *
 * 同時移除了舊版的 htmlspecialchars(trim($_POST['acc'])) ——
 * htmlspecialchars 是「輸出到 HTML 時」的跳脫函式，不是 SQL 的防護；
 * 用在密碼上還會改寫密碼內容，導致含 < > & " ' 的密碼永遠登入失敗。
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

// 已登入就不必再登入一次
if (is_logged_in()) {
    to('/admin.php');
}

// 強制 POST + 驗證 CSRF token
csrf_check();

$acc = trim((string) ($_POST['acc'] ?? ''));
$pw  = (string) ($_POST['pw'] ?? '');   // 密碼不做 trim，也不做任何字元改寫

if ($acc === '' || $pw === '') {
    login_failed($acc, 'empty_input');
}

/** @var DB $Admin */
$row = $Admin->find(['acc' => $acc]);

if ($row === null) {
    // 帳號不存在時也跑一次雜湊比對，讓成功與失敗的回應時間相近，
    // 避免用回應時間差判斷帳號是否存在
    password_verify($pw, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1MlVkd.uq');
    login_failed($acc, 'no_such_account');
}

$stored = (string) $row['pw'];
$info   = password_get_info($stored);

if ($info['algo'] !== null && $info['algo'] !== 0) {
    // 已經是雜湊
    if (!password_verify($pw, $stored)) {
        login_failed($acc, 'bad_password');
    }
    // 演算法或成本參數有更新時，順手換新雜湊
    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        $Admin->update((int) $row['id'], ['pw' => password_hash($pw, PASSWORD_DEFAULT)]);
        app_log('auth.rehash', ['acc' => $acc]);
    }
} else {
    // 舊資料是明文。以時間恆定方式比對，成功後立刻升級為雜湊。
    if (!hash_equals($stored, $pw)) {
        login_failed($acc, 'bad_password_legacy');
    }
    $Admin->update((int) $row['id'], ['pw' => password_hash($pw, PASSWORD_DEFAULT)]);
    app_log('auth.legacy_password_upgraded', ['acc' => $acc]);
}

login_as($row);
app_log('auth.login_ok', ['acc' => $acc]);

to('/admin.php');


/**
 * 登入失敗的統一出口。
 * 對外一律只說「帳號或密碼錯誤」，不透露是帳號不存在還是密碼錯 ——
 * 否則等於提供了帳號枚舉的管道。
 */
function login_failed(string $acc, string $reason): never
{
    // 失敗原因只寫進日誌，不回傳給使用者
    app_log('auth.login_failed', ['acc' => $acc, 'reason' => $reason]);

    // TODO（第 2 階段 A06-3）：此處尚無失敗次數限制與帳號鎖定，
    //                          目前仍可被暴力破解。
    $_SESSION['flash_error'] = '帳號或密碼錯誤';
    to('/index.php?do=login');
}

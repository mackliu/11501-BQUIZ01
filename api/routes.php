<?php
/**
 * 頁面路由白名單
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。對應 A05-2（LFI / 路徑遍歷）。
 *
 * 舊版的寫法是：
 *     $do   = $_GET['do'] ?? "main";
 *     $file = "front/$do.php";
 *     if (file_exists($file)) { include $file; }
 *
 * file_exists() 只確認檔案存在，完全不阻擋 ../ ——
 * 攻擊者先上傳 upload/shell.php，再開 index.php?do=../upload/shell
 * 就能讓自己的 PHP 程式碼在伺服器上執行（遠端程式碼執行）。
 *
 * 現在改為「使用者輸入只能當作查表的 key」，
 * 實際被 include 的路徑一律來自程式碼中的常數，永遠不做字串拼接。
 */

declare(strict_types=1);

/** 前台可用的頁面 */
const FRONT_ROUTES = [
    'main'  => 'front/main.php',
    'news'  => 'front/news.php',
    'login' => 'front/login.php',
];

/** 後台可用的頁面 */
const BACK_ROUTES = [
    'title'  => 'back/title.php',
    'ad'     => 'back/ad.php',
    'mvim'   => 'back/mvim.php',
    'image'  => 'back/image.php',
    'total'  => 'back/total.php',
    'bottom' => 'back/bottom.php',
    'news'   => 'back/news.php',
    'admin'  => 'back/admin.php',
    'menu'   => 'back/menu.php',
];

/**
 * 把使用者傳來的 do 參數解析成安全的頁面代號。
 * 查不到就退回預設頁，不會有任何使用者輸入進入路徑。
 *
 * @param  array  $routes  FRONT_ROUTES 或 BACK_ROUTES
 * @param  string $default 預設頁代號
 * @return string 確定存在於白名單中的代號
 */
function resolve_route(array $routes, string $default): string
{
    $do = $_GET['do'] ?? $default;

    if (!is_string($do) || !isset($routes[$do])) {
        return $default;
    }

    return $do;
}

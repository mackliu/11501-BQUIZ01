<?php
/**
 * 資料庫與環境設定範本
 * ---------------------------------------------------------------
 * 使用方式：
 *   1. 複製本檔為 api/config.php
 *   2. 依實際環境填入連線資訊
 *   3. api/config.php 已列入 .gitignore，不會被提交進版本庫
 *
 * 設定值優先順序：環境變數 > 本檔預設值
 * 正式環境請一律用環境變數提供，不要把密碼寫在檔案裡。
 *
 * 對應 OWASP A02:2025 Security Misconfiguration（A02-1）
 */

return [
    // ---- 資料庫連線 ----
    // 不要使用 root。請先執行 db/create_app_user.sql 建立最小權限帳號。
    'db_host'    => getenv('WEB21_DB_HOST') ?: '127.0.0.1',
    'db_name'    => getenv('WEB21_DB_NAME') ?: 'db21',
    'db_charset' => 'utf8mb4',
    'db_user'    => getenv('WEB21_DB_USER') ?: 'db21_app',
    'db_pass'    => getenv('WEB21_DB_PASS') ?: '請改成你自己的強密碼',

    // ---- 執行環境 ----
    // 'prod' 會關閉畫面上的錯誤輸出，只寫入日誌
    'env' => getenv('WEB21_ENV') ?: 'dev',

    // ---- Session Cookie ----
    // 站台改用 HTTPS 後，請把 cookie_secure 設為 true
    'cookie_secure' => filter_var(getenv('WEB21_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL),

    // ---- 稽核日誌位置 ----
    // 建議指向 web root 之外的目錄，避免日誌被下載
    'log_file' => getenv('WEB21_LOG_FILE') ?: __DIR__ . '/../db/app.log',
];

-- ===============================================================
-- 建立應用程式專用的最小權限資料庫帳號
-- 2026-08-14 安全修復，對應 OWASP A02:2025（A02-1）
-- ===============================================================
--
-- 舊版 api/db.php 直接以 root / 空密碼連線：
--     $this->pdo = new PDO($this->dsn, 'root', '');
--
-- 這代表一旦發生 SQL Injection，攻擊者拿到的是資料庫最高權限 ——
-- 可以讀寫 mysql 系統資料表、跨資料庫存取，若 secure_file_priv 未設定，
-- 甚至能用 SELECT ... INTO OUTFILE 直接寫出一個 webshell。
--
-- 本腳本建立的 db21_app 只有 db21 這個資料庫的四種基本操作權限，
-- 沒有 DROP / ALTER / CREATE / FILE / GRANT。
--
-- ---------------------------------------------------------------
-- 執行方式（XAMPP，於命令提示字元）：
--     C:\xampp\mysql\bin\mysql.exe -u root -p < db\create_app_user.sql
--
-- 或在 phpMyAdmin 的 SQL 頁籤貼上執行。
-- ---------------------------------------------------------------
--
-- ⚠ 下方密碼與 api/config.php 中的 db_pass 必須一致。
--   要換密碼時，請同時修改這兩個地方（或改用 WEB21_DB_PASS 環境變數）。
-- ===============================================================

CREATE DATABASE IF NOT EXISTS `db21`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- 本機連線用
CREATE USER IF NOT EXISTS 'db21_app'@'localhost'
    IDENTIFIED BY '24a6d692f300d0d5dcfca0639e85f027c9d4';
CREATE USER IF NOT EXISTS 'db21_app'@'127.0.0.1'
    IDENTIFIED BY '24a6d692f300d0d5dcfca0639e85f027c9d4';

-- 只給資料操作權限，不給結構變更與檔案存取權限
GRANT SELECT, INSERT, UPDATE, DELETE ON `db21`.* TO 'db21_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `db21`.* TO 'db21_app'@'127.0.0.1';

FLUSH PRIVILEGES;

-- 確認結果
SELECT user, host FROM mysql.user WHERE user = 'db21_app';
SHOW GRANTS FOR 'db21_app'@'localhost';

-- ===============================================================
-- 後續建議（本次未自動執行，需要時再手動處理）
-- ===============================================================
-- 1. 為 MySQL 的 root 帳號設定密碼（XAMPP 預設為空密碼）：
--        ALTER USER 'root'@'localhost' IDENTIFIED BY '<強密碼>';
--        FLUSH PRIVILEGES;
--    設定後記得同步更新 C:\xampp\phpMyAdmin\config.inc.php。
--
-- 2. 若資料庫不需要對外服務，讓 MySQL 只監聽本機：
--    在 my.ini 的 [mysqld] 區段加入
--        bind-address = 127.0.0.1
-- ===============================================================

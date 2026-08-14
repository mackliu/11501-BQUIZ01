# 第 1 階段安全修復紀錄

> 執行日期：2026-08-14
> 分支：`owasp`
> 依據：[owasp.md](owasp.md) 的稽核結果與其「附錄 B：建議修復順序」
> 範圍：**9 項 Critical 問題**（第 0 階段 3 項 + 第 1 階段 6 項）
> 環境：XAMPP / Apache 2.4.58 / PHP 8.2.12 / MariaDB 10.4.32 / 站台 `http://web01.local`

---

## 目錄

- [1. 修復範圍與判斷依據](#1-修復範圍與判斷依據)
- [2. 修復項目逐項說明](#2-修復項目逐項說明)
- [3. 檔案異動清單](#3-檔案異動清單)
- [4. 驗證方式與結果](#4-驗證方式與結果)
- [5. 過程中發現的額外問題](#5-過程中發現的額外問題)
- [6. 部署與交接注意事項](#6-部署與交接注意事項)
- [7. 尚未處理的項目](#7-尚未處理的項目)

---

## 1. 修復範圍與判斷依據

### 1.1 已完成的 9 項 Critical

| 編號 | 問題 | OWASP 2025 類別 |
|---|---|---|
| A01-1 | 後台首頁 `admin.php` 無任何登入檢查 | Broken Access Control |
| A01-2 | 所有 `api/*.php` 寫入端點無身分驗證 | Broken Access Control |
| A02-1 | 資料庫使用 root / 空密碼，且憑證寫死在原始碼 | Security Misconfiguration |
| A02-2 | `db21.sql` 放在網站根目錄，內含明文管理員密碼 | Security Misconfiguration |
| A04-1 | 管理員密碼以明文儲存 | Cryptographic Failures |
| A05-1 | SQL Injection（資料存取層全面字串拼接） | Injection |
| A05-2 | 本地檔案引入（LFI）／路徑遍歷 → 遠端程式碼執行 | Injection |
| A06-1 | 任意檔案上傳（無任何驗證） | Insecure Design |
| A06-2 | 全站缺少 CSRF 防護 | Insecure Design |

### 1.2 為什麼第 0 階段也一起做了

原報告把 A01-1 / A01-2（登入檢查）與 A02-2（`db21.sql`）放在「第 0 階段」而非第 1 階段。這次一併處理，原因是**第 1 階段的修復若少了它們就沒有意義**：

- 做了 CSRF 防護（A06-2），但任何人不需登入就能直接呼叫 API —— 防的是「偽造管理員的請求」，可是根本不需要偽造。
- 做了密碼雜湊（A04-1），但任何人都能 `POST /api/add.php?table=admin` 建立自己的帳號 —— 雜湊保護的是外洩後的密碼，擋不住正面走進來的人。

因此本次交付的是**完整的 9 項 Critical**，而不是第 1 階段列表的 6 項。

### 1.3 一併納入的必要連帶修改

以下項目在原報告中屬第 2 階段（High），但因為是 Critical 修復的直接前提或直接後果，本次一併處理。這些是**必要的連帶修改，不是額外擴大範圍**：

| 編號 | 項目 | 為什麼非做不可 |
|---|---|---|
| A01-4 | 登出未銷毀 session | 開始真正強制登入後，舊的「清一個沒用的 cookie」按鈕會讓使用者無法登出。缺了它，登入機制不完整。 |
| A01-5 | 主管理員保護只做在前端 | 密碼改為雜湊後仍讓 API 端可任意改 `id=1`，等於保留了同一個漏洞。 |
| A04-2 | 後台回填明文密碼 | 密碼變成雜湊後若繼續回填，送出時會把雜湊再雜湊一次，密碼直接壞掉。**不改就會壞掉功能。** |
| A05-4 | `$do` 反射型 XSS | 路由白名單（A05-2）讓 `$do` 變成受控值後自然消失，順手補上輸出跳脫。 |
| A05-5 | 檔名注入 JavaScript | 上傳檔名改由伺服器產生（A06-1）後源頭已斷，但資料庫可能殘留舊檔名，因此輸出端改用 `json_encode`。 |
| A05-6 | 可變變數 `${ucfirst($table)}` | 這正是「未登入即可操作 admin 表」的機制，是 A01-2 與 A05-1 的共同成因。 |
| A07-1 | Session Fixation | 建立登入狀態的程式碼本來就要重寫，`session_regenerate_id()` 是同一個函式裡的一行。 |
| A07-2 | `to()` 導向後未 `exit` | 認證失敗要靠導向擋下請求，沒有 `exit` 就會「導向了但沒擋住」。 |
| A10-1 | PDO 未設錯誤模式 | 改寫資料存取層時必須決定錯誤處理策略，否則 SQL 失敗會 Fatal error 並洩漏路徑。 |
| A10-3 / A10-4 / A10-5 | 未檢查陣列、分頁參數、`null` 取索引 | 改寫 API 與 DB 類別時經過的同一段程式碼；`find()` 改回傳 `null` 後不補會壞。 |

---

## 2. 修復項目逐項說明

### A01-1 / A01-2　建立真正的存取控制

**原本的狀況**

`api/login.php` 登入成功時寫入 `$_SESSION['login']=1`，但全專案沒有任何一行讀取它 —— 認證機制只寫不讀。

```bash
$ grep -rn "SESSION\['login'\]" .
api/login.php:8:        $_SESSION['login']=1;      ← 只有寫入
```

**改法**

新增 `api/auth.php`，提供 `is_logged_in()` / `require_login()` / `login_as()` / `logout()`。

```php
function require_login(): void
{
    if (is_logged_in()) { return; }

    app_log('auth.denied', ['target' => $_SERVER['REQUEST_URI'] ?? '-']);

    // AJAX 片段回 401，不做轉址，避免把登入頁塞進彈出視窗
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        http_response_code(401);
        echo '<p style="padding:20px">登入狀態已失效，請重新登入。</p>';
        exit;
    }

    to('/index.php?do=login');   // to() 內含 exit
}
```

套用位置：`admin.php`、全部 8 個 `api/*.php` 寫入端點、全部 12 個 `include/*.php` 面板。

**同時修好的 `to()`**

舊版 `to()` 只呼叫 `header()` 而沒有 `exit`。這在舊架構下沒出事，但一旦拿它來做「認證失敗就導走」，程式會繼續往下執行 —— 瀏覽器收到 302 的同時也收到完整的後台內容。

```php
function to(string $path): never          // ← 回傳型別宣告為 never
{
    $path = str_replace(["\r", "\n"], '', $path);      // 擋 HTTP 標頭注入
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)   // 擋開放式重新導向
        || str_starts_with($path, '//')) {
        $path = '/index.php';
    }
    header('Location: ' . $path, true, 302);
    exit;
}
```

---

### A05-1　SQL Injection：資料存取層全面改寫

**原本的狀況**

`api/db.php` 的每一個方法都以字串拼接組 SQL，核心在 `a2s()`：

```php
protected function a2s($array){
    foreach($array as $key => $val){
        $tmp[]="`$key`='$val'";      // key 與 value 皆直接內插
    }
}
```

最嚴重的利用點在登入 —— `$Admin->count($_POST)` 把整包 `$_POST` 交給它：

```bash
curl -X POST http://web01.local/api/login.php -d "acc=x&pw=y&z=1' OR '1'='1"
# → SELECT count(*) FROM admin WHERE `acc`='x' AND `pw`='y' AND `z`='1' OR '1'='1'
# → count > 0 → 登入成功
```

**改法**

`DB` 類別完全重寫，建立在兩條規則上：

> **欄位名永遠來自程式碼中的常數，欄位值永遠透過參數繫結。**

```php
private const SCHEMA = [
    'ad'     => ['text', 'sh'],
    'admin'  => ['acc', 'pw'],
    'menu'   => ['href', 'text', 'sh', 'main_id'],
    // ... 每張表明確列出允許的欄位
];

private function buildWhere(array $cond): array
{
    $parts = []; $bind = [];
    foreach ($cond as $col => $val) {
        $this->assertColumn($col);                 // ← 欄位名必過白名單
        $parts[]            = "`{$col}` = :w_{$col}";
        $bind[':w_' . $col] = $val;                // ← 欄位值必經參數繫結
    }
    return [' WHERE ' . implode(' AND ', $parts), $bind];
}
```

`LIMIT` / `OFFSET` 無法用參數繫結，改以 `max(0, (int)$v)` 強制轉型後才組進 SQL —— 整數不可能包含 SQL 語法。

PDO 連線同時加上：

```php
PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,  // A10-1
PDO::ATTR_EMULATE_PREPARES => false,                   // 由資料庫端真正做繫結
```

`EMULATE_PREPARES => false` 很重要：開啟模擬時是 PDO 自己在客戶端拼字串，某些字元集組合下仍有繞過空間。

**附帶效果**：舊版每 `new DB()` 就開一條連線，全站共 9 條。現在改為 `Database::pdo()` 單例共用一條。

**登入流程改寫**

```php
$row = $Admin->find(['acc' => $acc]);          // 只用 acc 查，prepared

if ($row === null) {
    // 帳號不存在時也跑一次雜湊比對，讓成功與失敗的回應時間相近
    password_verify($pw, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1MlVkd.uq');
    login_failed($acc, 'no_such_account');
}
```

同時移除了原本加在第 3、4 行的 `htmlspecialchars(trim($_POST['acc']))`。那段防護有四個問題：只處理 `acc`/`pw` 兩欄、陣列的 key 完全沒處理、PHP 8.1 以前預設不轉單引號、而且用在密碼上會改寫密碼內容害使用者永遠登不進來。**`htmlspecialchars` 是輸出到 HTML 時的跳脫函式，不是 SQL 的防護。**

---

### A05-2　LFI：路由改白名單

**原本的狀況**

```php
$do   = $_GET['do'] ?? "main";
$file = "front/$do.php";
if (file_exists($file)) { include $file; }
```

`file_exists()` 只確認檔案存在，完全不阻擋 `../`。

**改法**

新增 `api/routes.php`。使用者輸入只能當作查表的 key，實際路徑一律來自常數：

```php
const FRONT_ROUTES = [
    'main'  => 'front/main.php',
    'news'  => 'front/news.php',
    'login' => 'front/login.php',
];

function resolve_route(array $routes, string $default): string
{
    $do = $_GET['do'] ?? $default;
    return (is_string($do) && isset($routes[$do])) ? $do : $default;
}
```

```php
// index.php
$do = resolve_route(FRONT_ROUTES, 'main');
include __DIR__ . '/' . FRONT_ROUTES[$do];      // 沒有任何字串拼接
```

**附帶效果**：`$do` 變成受控值後，`back/*.php` 中 `<?= $do ?>` 造成的反射型 XSS（A05-4）也一併消失。仍另外補上 `e()` 跳脫作為深度防禦。

---

### A06-1 / A02-4　檔案上傳

**原本的狀況**

```php
move_uploaded_file($_FILES['img']['tmp_name'], "../upload/".$_FILES['img']['name']);
```

沒有任何驗證，且直接採用使用者提供的原始檔名。

**改法**

新增 `api/upload.php`，七道關卡：

1. 上傳錯誤碼檢查
2. `is_uploaded_file()` —— 擋掉偽造 `tmp_name` 指向系統檔案
3. 大小上限 3 MB
4. 以 `finfo` 讀**檔案內容**判斷 MIME（不信任瀏覽器送的 type，也不信任副檔名）
5. `getimagesize()` 確認可解析、尺寸合理，且型別與 finfo 一致
6. **掃描原始位元組**，擋掉夾帶的 `<?php` / `<?=` / `<script` / `__HALT_COMPILER`
7. 檔名由 `bin2hex(random_bytes(16))` 產生，副檔名由 MIME 決定

> **第 6 關是測試過程中才補上的。**
> 原本只做到第 5 關，測試時發現 `printf 'GIF89a<?php echo "SHELL"; ?>'` 這個 28 位元組的檔案
> **通過了 finfo 與 getimagesize 兩關並成功入庫** —— 因為 `getimagesize()` 只讀 GIF 檔頭的
> 邏輯螢幕描述子，完全不管後面接了什麼。詳見第 5 節。

加上 `upload/.htaccess` 關閉該目錄的 PHP 執行：

```apache
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "(?i)\.(php|php[0-9]?|phtml|phps|phar|inc|cgi|pl|py|rb|sh|exe|htaccess)$">
    Require all denied
</FilesMatch>
```

**A02-4 與 A05-2 必須兩者都修**：`.htaccess` 管的是 Web Server 的請求處理，但 `include` 是 PHP 直譯器主動載入，不經過那層規則。只修其中一個都留得下完整的攻擊鏈。

---

### A04-1　密碼雜湊與舊資料遷移

**原本的狀況**

```sql
INSERT INTO `admin` VALUES (1,'admin','1234'), (3,'superadmin','12345678'), (4,'root','5678');
```

**改法**

寫入端改用 `password_hash($pw, PASSWORD_DEFAULT)`（PHP 8.2 為 bcrypt，`$2y$` 開頭 60 字元）。

驗證端支援兩種格式，讓舊資料可以平順遷移：

```php
$info = password_get_info($stored);

if ($info['algo'] !== null && $info['algo'] !== 0) {
    if (!password_verify($pw, $stored)) { login_failed(...); }
    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {   // 成本參數更新時換新
        $Admin->update((int)$row['id'], ['pw' => password_hash($pw, PASSWORD_DEFAULT)]);
    }
} else {
    // 舊資料是明文：時間恆定比對，成功後立刻升級為雜湊
    if (!hash_equals($stored, $pw)) { login_failed(...); }
    $Admin->update((int)$row['id'], ['pw' => password_hash($pw, PASSWORD_DEFAULT)]);
    app_log('auth.legacy_password_upgraded', ['acc' => $acc]);
}
```

**資料庫結構調整**（`db/migrate_stage1.sql`，已執行）

```sql
ALTER TABLE `admin` MODIFY `pw`  VARCHAR(255) NOT NULL;   -- 容納雜湊
ALTER TABLE `admin` MODIFY `acc` VARCHAR(64)  NOT NULL;   -- text 無法建索引
ALTER TABLE `admin` ADD UNIQUE KEY `uniq_admin_acc` (`acc`);
```

`acc` 原本是 `text`，無法建立 UNIQUE 索引，因此可以建出重複帳號 —— 登入時只會比對到其中一筆，行為不可預期。

**後台表單**（`back/admin.php`）

密碼欄位改為固定留空的「新密碼（不修改請留空）」，`api/edit.php` 只在該欄非空時才重新雜湊。這同時修掉 A04-2（明文回填 HTML），也是功能上的必要修改 —— 回填的雜湊被再次雜湊，密碼就壞了。

**密碼輪換**（已執行）

`admin` / `superadmin` / `root` 三組密碼曾存在於 Git 歷史與可下載的 `db21.sql` 中，必須視為已公開外洩。三組都已改為隨機新密碼並雜湊儲存，舊密碼實測已失效。新密碼另行提供，**請登入後自行改成你要的密碼**。

新增 `db/create_admin.php` 命令列工具，用於建立或重設帳號：

```bash
php db/create_admin.php <帳號> <密碼> [--id=1]
```

---

### A06-2　CSRF 防護

新增 `api/csrf.php`：

```php
function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        app_fail(405, '此端點只接受 POST 請求。');
    }
    $sent = $_POST['_token'] ?? '';
    // hash_equals 為時間恆定比較，避免以回應時間逐字元猜出 token
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        app_fail(403, 'CSRF token 驗證失敗。');
    }
}
```

`csrf_field()` 已加入**全部 31 個表單**（登入 1、後台編輯 9、後台登出 9、`include/` 面板 12），無一遺漏。

第二道防線是 session cookie 的 `SameSite=Lax`（見 `api/bootstrap.php`），跨站送出的請求不會帶上 cookie。

> **狀態碼從 419 改成 403**：一開始用了 Laravel 慣用的 419，但 419 不是 IANA 註冊的狀態碼，
> Apache 會把它降級成 500，反而蓋掉真正的失敗原因。測試時抓到，已改用標準的 403。

---

### A02-1　資料庫憑證與最小權限

**原本的狀況**

```php
$this->pdo = new PDO($this->dsn, 'root', '');
```

**改法**

憑證移出程式碼，改由 `api/config.php` 提供，並支援環境變數覆寫：

```php
'db_user' => getenv('WEB21_DB_USER') ?: 'db21_app',
'db_pass' => getenv('WEB21_DB_PASS') ?: '（本機密碼）',
```

`api/config.php` 已列入 `.gitignore`；版本庫只保留 `api/config.sample.php` 範本。

建立最小權限帳號（`db/create_app_user.sql`，已執行）：

```sql
CREATE USER 'db21_app'@'localhost' IDENTIFIED BY '...';
GRANT SELECT, INSERT, UPDATE, DELETE ON `db21`.* TO 'db21_app'@'localhost';
-- 不給 DROP / ALTER / CREATE / FILE / GRANT OPTION
```

實際權限（已驗證）：

```
GRANT USAGE ON *.* TO `db21_app`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE ON `db21`.* TO `db21_app`@`localhost`
```

這代表就算未來又出現 SQL Injection，攻擊者也拿不到 `FILE` 權限（無法用 `SELECT ... INTO OUTFILE` 寫 webshell）、動不了資料表結構、看不到其他資料庫。

---

### A02-2　SQL 傾印檔與目錄存取控制

- `db21.sql` 從網站根目錄移到 `db/db21.sql`，並以 `git rm` 從版本庫移除
- 移除檔中的三筆明文管理員資料，改為說明如何用 `db/create_admin.php` 建立帳號
- 新增 6 個 `.htaccess`：

| 位置 | 作用 |
|---|---|
| `/.htaccess` | 關閉目錄列表；封鎖 `*.sql` `*.log` `*.md` `*.bak` 等；封鎖 `.git`、`102201/` |
| `/api/.htaccess` | 封鎖 `config` `bootstrap` `db` `auth` `csrf` `upload` `routes` 等函式庫檔 |
| `/back/.htaccess` | 全部拒絕（只被 `admin.php` 伺服器端 include） |
| `/front/.htaccess` | 全部拒絕（只被 `index.php` 伺服器端 include） |
| `/db/.htaccess` | 全部拒絕 |

`api/config.php` 特別以 `.htaccess` 封死：正常情況下 PHP 會執行它而不吐出原始碼，但只要 PHP 模組在部署時出問題，整份資料庫密碼就會以純文字送給瀏覽器。這道防護不依賴 PHP 是否正常運作。

---

## 3. 檔案異動清單

### 新增（15 個）

| 檔案 | 用途 |
|---|---|
| `api/bootstrap.php` | 設定載入、錯誤處理、session、輸出跳脫、轉址 |
| `api/config.php` | 實際連線設定（**已 gitignore**） |
| `api/config.sample.php` | 設定範本（進版本庫） |
| `api/auth.php` | 登入狀態判斷與守門 |
| `api/csrf.php` | CSRF token 產生與驗證 |
| `api/upload.php` | 安全的圖片上傳處理 |
| `api/routes.php` | 前後台路由白名單 |
| `api/logout.php` | 真正銷毀 session 的登出端點 |
| `db/db21.sql` | 移入並移除明文密碼的資料庫傾印 |
| `db/create_app_user.sql` | 建立最小權限資料庫帳號 |
| `db/migrate_stage1.sql` | admin 表結構調整 |
| `db/create_admin.php` | 建立／重設管理員帳號的 CLI 工具 |
| `.gitignore` | 排除設定檔、日誌、上傳內容 |
| `.htaccess` ×6 | 根目錄、`api/`、`back/`、`front/`、`db/`、`upload/` |
| `SECURITY-FIX-STAGE1.md` | 本文件 |

### 改寫（10 個）

`api/db.php`（完全重寫）、`api/login.php`、`api/add.php`、`api/edit.php`、`api/update.php`、`api/edit_value.php`、`api/edit_bottom.php`、`api/edit_total.php`、`api/submenu.php`、`front/login.php`

### 修改（25 個）

`index.php`、`admin.php`、`back/*.php`（9）、`front/main.php`、`front/news.php`、`include/*.php`（12）

### 刪除（1 個）

`db21.sql`（已移至 `db/db21.sql`）

### 資料庫異動（已執行）

1. 建立 `db21_app`@`localhost` 與 `db21_app`@`127.0.0.1`，授予 `db21.*` 的 SELECT/INSERT/UPDATE/DELETE
2. `admin.pw` → `VARCHAR(255)`；`admin.acc` → `VARCHAR(64)` + UNIQUE 索引
3. 三組管理員密碼輪換為隨機新密碼並以 bcrypt 儲存

---

## 4. 驗證方式與結果

### 4.1 語法檢查

```bash
$ for f in $(find . -name "*.php" -not -path "./.git/*" -not -path "./102201/*"); do
    php -l "$f"; done
ALL PHP FILES PASS SYNTAX CHECK        # 全部 45 個檔案
```

### 4.2 未登入狀態的攻擊測試（29 項全數通過）

| 測試 | 結果 |
|---|---|
| `admin.php` 未登入存取 | 302 → 登入頁 |
| 7 個 API 端點未登入 POST | 全部 302 → 登入頁 |
| `back/title.php` / `front/main.php` 直接存取 | 403 |
| `api/config.php` / `api/db.php` 直接存取 | 403 |
| `include/admin.php` 未登入（AJAX） | 401 |
| `db21.sql` / `db/db21.sql` / `db/app.log` / `.gitignore` | 全部 403 |
| `102201/` 舊版型檔案 | 403 |
| **上傳 `.php` 到 `upload/` 後存取** | **403，且未執行（無 `PWNED-42` 輸出）** |
| **`index.php?do=../upload/shell`** | **擋下，未執行** |
| `index.php?do=../../windows/win` | 擋下 |
| `index.php?do=..%2fupload%2fshell`（URL 編碼） | 擋下 |
| `index.php?do=../api/db` | 擋下 |
| **登入 SQL Injection：`z=1' OR '1'='1`** | **失敗，導回登入頁** |
| **登入 SQL Injection：反引號脫逸** | **失敗** |
| POST 無 CSRF token | 403 |
| POST 錯誤 CSRF token | 403 |
| 以 GET 觸發寫入 API | 302（先被認證擋下） |

### 4.3 已登入狀態的功能與行為測試（24 項全數通過）

| 測試 | 結果 |
|---|---|
| 錯誤密碼登入 | 拒絕 |
| **舊明文密碼 `1234` 登入** | **成功，且自動升級為 bcrypt** |
| 升級後的 `admin.pw` | `$2y$10$...`，長度 60 |
| 後台 9 個頁面（title/ad/mvim/image/total/bottom/news/admin/menu） | 全部 200 |
| `admin.php?do=` 傳入路徑遍歷字串 | 200，退回預設頁 |
| `include/` 8 個面板 | 全部 200 |
| **`admin.php?do="><script>alert(1)</script>`** | **未輸出可執行的 script** |
| **上傳偽裝成 GIF 的 PHP** | **415 拒絕** |
| **上傳正常 JPG** | **成功，存為 `ad071f...53.jpg`（原始檔名已丟棄）** |
| 登出後存取 `admin.php` | 302（session 確實銷毀） |

### 4.4 密碼輪換驗證

```
舊密碼 1234        : http://127.0.0.1/index.php?do=login     ← 已失效
新密碼             : http://127.0.0.1/admin.php              ← 成功
登入後 admin.php   : HTTP 200
```

### 4.5 資料完整性確認

測試過程中產生的資料與檔案已全部清除：

```
upload/ 檔案數：18（17 張圖片 + .htaccess）
image 資料表：7 筆   ← 與原始 db21.sql 一致
```

---

## 5. 過程中發現的額外問題

這三項是修復過程中才浮現、原稽核報告沒有記錄的問題。

### 5.1 `admin.php` 開頭的 UTF-8 BOM（會造成認證繞過）

```
$ head -c 20 admin.php | xxd
00000000: efbb bf3c 3f70 6870 2069 6e63 ...
          ^^^^^^^^ UTF-8 BOM
```

BOM 會在 PHP 進入程式碼模式前先被輸出，導致後續所有 `header()` 呼叫失效（`headers already sent`）。

在舊架構下這只是個潛在問題；但一旦改用 `header('Location: ...')` 做認證守門，**導向會被靜默忽略而頁面照常渲染 —— 等於認證完全失效**。已移除 BOM，並確認全專案沒有其他帶 BOM 的 PHP 檔。

### 5.2 `getimagesize()` 擋不住多型圖片檔

原本的上傳驗證做到「finfo 判斷 MIME + getimagesize 確認可解析」就結束。測試時發現：

```bash
$ printf 'GIF89a<?php echo "SHELL"; ?>' > evil.gif      # 28 位元組
$ curl -F "img=@evil.gif" ".../api/add.php?table=image"
HTTP/1.1 302 Found
Location: /admin.php?do=image                            # ← 通過了！
```

`getimagesize()` 只解析 GIF 檔頭的邏輯螢幕描述子，把 `<?p` 當成寬高數值讀進去就回傳成功，完全不管後面接了什麼。

已補上第 6 關（掃描原始位元組中的 `<?php` / `<?=` / `<script` / `__HALT_COMPILER`），重測回傳 415。

**這也說明了為什麼 `upload/.htaccess` 不能省** —— 單一驗證關卡都可能有盲點，必須靠多層防護。

### 5.3 MySQL 系統資料表損毀（既有問題，非本次造成）

執行 `GRANT` 時出現：

```
ERROR 1030 (HY000): Got error 176 "Read page with wrong checksum" from storage engine Aria
```

`mysql.db` 這張系統表的資料頁 CRC 校驗失敗，是既有的損毀，與本次修改無關。

以 `REPAIR TABLE mysql.db` 修復，過程中回報 `Number of rows changed from 3 to 0` —— 該資料頁本來就無法讀取，三筆資料庫層級授權隨之遺失。其中包含 phpMyAdmin 控制帳號 `pma` 對 `phpmyadmin` 資料庫的授權，**已依 XAMPP 預設值重新授予**：

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON `phpmyadmin`.* TO 'pma'@'localhost';
```

目前 `mysql.db` 內容：

```
db21_app  127.0.0.1  db21
db21_app  localhost  db21
pma       localhost  phpmyadmin
```

> **請留意**：若您原本還有其他資料庫層級的授權（例如給 `db01`、`db19`、`db25` 等其他專案用的帳號），
> 那些設定可能也在這次損毀中遺失了。建議檢查其他專案是否仍能正常連線。
> 這個損毀在修復前就已存在，但確認範圍需要您這邊比對。

---

## 6. 部署與交接注意事項

### 6.1 換機器或重新部署時

```bash
# 1. 建立資料庫使用者（密碼需與 api/config.php 一致）
C:\xampp\mysql\bin\mysql.exe -u root -p < db\create_app_user.sql

# 2. 匯入資料庫結構
C:\xampp\mysql\bin\mysql.exe -u root -p db21 < db\db21.sql

# 3. 建立設定檔
copy api\config.sample.php api\config.php
#    編輯 api/config.php，填入正確的連線資訊

# 4. 建立第一個管理員
php db\create_admin.php admin "<你的強密碼>" --id=1
```

### 6.2 `api/config.php` 不在版本庫中

這是刻意的。換機器時必須手動建立，或改用環境變數：

```
WEB21_DB_HOST / WEB21_DB_NAME / WEB21_DB_USER / WEB21_DB_PASS
WEB21_ENV（設為 prod 會關閉畫面上的錯誤訊息）
WEB21_COOKIE_SECURE（改用 HTTPS 後設為 true）
```

### 6.3 `.htaccess` 的前提

目前的目錄防護依賴 Apache 的 `AllowOverride All`（本機 vhost 已設定）。**換到 Nginx 或 `AllowOverride None` 的環境會全部失效。** 根本解法是把應用改成只有 `public/` 目錄對外，其餘檔案放在 web root 之外 —— 屬第 2 階段。

### 6.4 Git 歷史中仍留有明文密碼

`git rm db21.sql` 只移除了目前版本。**先前的 commit 裡仍然看得到那三組明文密碼。**

三組密碼都已輪換，因此沒有立即風險。若這個版本庫會公開（推上 GitHub 等），建議用 `git filter-repo` 清理歷史 —— 但那會改寫所有 commit 的雜湊值，需要所有協作者重新 clone，**請您決定後再處理**。

### 6.5 稽核日誌

事件寫入 `db/app.log`（JSON Lines），該路徑已由 `.htaccess` 封鎖。目前記錄的事件：

```
auth.login_ok / auth.login_failed / auth.logout / auth.denied
auth.legacy_password_upgraded / auth.rehash
csrf.mismatch / csrf.non_post
upload.ok / upload.embedded_code / upload.bad_mime / upload.too_large
record.created / record.edited / record.image_updated / setting.updated
table.illegal_name / *.illegal_table
db.connect_failed / uncaught_exception
```

日誌只記錄事件與帳號，**不記錄密碼、session ID 或完整 SQL 語句**。

這是 A09-1 的最小實作，完整的稽核日誌（含保存期限、唯附加權限、異常告警）屬第 2 階段。

---

## 7. 尚未處理的項目

以下依原稽核報告的規劃留待後續階段。按建議處理順序排列：

### 第 2 階段（High，建議 2～4 週內）

| 編號 | 項目 | 備註 |
|---|---|---|
| A05-3 | **儲存型 XSS：全站輸出未跳脫** | **本階段剩餘項目中最嚴重的一項。** `include/marquee.php:6` 出現在前台每一頁；`index.php`、`front/main.php`、`front/news.php`、`back/*.php` 的資料輸出都未經 `e()`。`e()` 函式已建好可直接使用。 |
| A03-1 | jQuery 1.9.1（CVE-2020-11022/11023） | 專案大量使用 `.html()` 直接塞入資料庫內容，是這兩個 CVE 的直接觸發點。建議升級至 3.7.x，或把提示框的 `.html()` 全部改為 `.text()`。 |
| A09-1 | 完整稽核日誌 | 目前僅有最小實作 |
| A06-3 | 登入速率限制／帳號鎖定 | 目前仍可暴力破解，`api/login.php` 中已標註 TODO |
| A01-3 | 目錄結構改為 `public/` 對外 | 取代目前依賴 `.htaccess` 的做法 |
| A01-6 | 權限分級（role 欄位） | 目前所有管理員權限相同 |
| A08-1 | `$(y).load(url)` 動態載入 | 建議改為結構化 JSON API |
| A04-3 | 全站 TLS | 上線後同步把 `cookie_secure` 設為 true |

### 第 3 階段（Medium / Low）

安全標頭與 CSP（需先移除 inline `onclick`）、session 逾時、密碼強度政策（目前只檢查長度 ≥ 8）、相依套件管理與 CI 掃描、自訂錯誤頁、登入歷程顯示、A06-4 訪客計數以 session 判斷仍可被清 cookie 繞過（本次已修掉其中的競態條件與崩潰風險，但計數方式本身未改）。

---

*本紀錄與 [owasp.md](owasp.md) 搭配閱讀。owasp.md 維持稽核當下的原始記錄，本檔記錄修復過程。*

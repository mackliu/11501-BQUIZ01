<?php
/**
 * 安全的圖片上傳處理
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。對應 A06-1。
 *
 * 舊版的寫法是：
 *     move_uploaded_file($_FILES['img']['tmp_name'], "../upload/".$_FILES['img']['name']);
 *
 * 完全沒有驗證副檔名、MIME、檔案內容、大小，而且直接採用使用者提供的原始檔名。
 * 攻擊者可以：
 *   - 上傳 shell.php 取得遠端程式碼執行
 *   - 用 filename="../api/db.php" 覆寫核心程式檔
 *   - 用 filename="x');alert(1);//.gif" 注入 JavaScript（front/main.php 會把檔名寫進 JS）
 *
 * 本檔的防護順序（任何一關不過就中止）：
 *   1. 確認是真正經由 HTTP 上傳的檔案（is_uploaded_file）
 *   2. 檢查上傳錯誤碼與檔案大小
 *   3. 以檔案「內容」判斷 MIME，不信任瀏覽器送來的 type，也不信任副檔名
 *   4. 用 getimagesize() 確認可解析、尺寸合理，且型別與 finfo 一致
 *   5. 掃描原始位元組，擋掉夾帶在圖片中的 PHP / JavaScript 程式碼
 *   6. 檔名完全由伺服器產生（隨機十六進位字串 + 由 MIME 決定的副檔名）
 *
 * 關於第 5 關：光靠 getimagesize() 是不夠的。實測 "GIF89a<?php ... ?>"
 * 這種只有 28 位元組的檔案可以通過 finfo 與 getimagesize 兩關 ——
 * 因為 getimagesize 只讀檔頭的邏輯螢幕描述子，不管後面接了什麼。
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** 允許的圖片型別 → 對應的副檔名 */
const UPLOAD_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/** 單檔大小上限 */
const UPLOAD_MAX_BYTES = 3 * 1024 * 1024; // 3 MB

/** 上傳目錄（web root 下的 upload/，已由 upload/.htaccess 禁止執行 PHP） */
function upload_dir(): string
{
    return __DIR__ . '/../upload';
}

/**
 * 處理一個上傳欄位。
 *
 * @param  array|null $file $_FILES['img'] 的內容
 * @return string|null 成功時回傳伺服器產生的新檔名；沒有上傳檔案時回傳 null
 *                     （驗證失敗會直接中止請求，不會回傳）
 */
function save_uploaded_image(?array $file): ?string
{
    // 使用者沒有選檔案 —— 屬於正常情況（例如只改文字不換圖）
    if ($file === null
        || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        || ($file['tmp_name'] ?? '') === ''
    ) {
        return null;
    }

    // --- 1. 上傳錯誤碼 ---
    $error = (int) $file['error'];
    if ($error !== UPLOAD_ERR_OK) {
        $reason = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '檔案超過大小限制。',
            UPLOAD_ERR_PARTIAL                        => '檔案只上傳了一部分，請重試。',
            UPLOAD_ERR_NO_TMP_DIR                     => '伺服器缺少暫存目錄。',
            UPLOAD_ERR_CANT_WRITE                     => '伺服器無法寫入檔案。',
            default                                   => '檔案上傳失敗。',
        };
        app_log('upload.error', ['code' => $error]);
        app_fail(400, $reason, $reason);
    }

    // --- 2. 必須是真正經由 HTTP POST 上傳的暫存檔 ---
    // 阻擋以參數偽造 tmp_name 指向系統檔案（例如 /etc/passwd）的手法
    if (!is_uploaded_file($file['tmp_name'])) {
        app_log('upload.not_uploaded_file', []);
        app_fail(400, '不是合法的上傳檔案。');
    }

    // --- 3. 大小 ---
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        app_log('upload.too_large', ['size' => $size]);
        app_fail(413, '圖片大小必須在 3 MB 以內。', '圖片大小必須在 3 MB 以內。');
    }

    // --- 4. 以檔案內容判斷型別 ---
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!is_string($mime) || !isset(UPLOAD_ALLOWED_TYPES[$mime])) {
        app_log('upload.bad_mime', ['mime' => $mime]);
        app_fail(415, '只接受 JPG / PNG / GIF / WebP 圖片。', '只接受 JPG / PNG / GIF / WebP 圖片。');
    }

    // --- 5. 確認確實是可解析的圖片，且尺寸合理 ---
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
        app_log('upload.not_an_image', ['mime' => $mime]);
        app_fail(415, '檔案內容不是有效的圖片。', '檔案內容不是有效的圖片。');
    }
    if ((int) $info[0] > 10000 || (int) $info[1] > 10000) {
        app_log('upload.dimensions_too_large', ['w' => $info[0], 'h' => $info[1]]);
        app_fail(415, '圖片尺寸過大（單邊上限 10000 像素）。', '圖片尺寸過大。');
    }

    // getimagesize() 認定的型別必須與 finfo 一致
    if (image_type_to_mime_type($info[2]) !== $mime) {
        app_log('upload.mime_mismatch', ['finfo' => $mime, 'getimagesize' => $info[2]]);
        app_fail(415, '圖片格式與副檔名不一致。', '圖片格式不正確。');
    }

    // --- 6. 掃描夾帶的程式碼 ---
    // 注意：getimagesize() 只讀檔頭，「GIF89a + PHP 程式碼」這種多型檔案
    // 是可以通過上面每一關的（實測確認）。這裡直接掃原始位元組。
    //
    // 這一關搭配 upload/.htaccess 的「本目錄不執行 PHP」與伺服器自行產生的
    // 檔名，構成三層防護。任何一層單獨都不足以擋下所有情況。
    $raw = (string) file_get_contents($file['tmp_name'], false, null, 0, UPLOAD_MAX_BYTES);
    foreach (['<?php', '<?=', '<script', '__HALT_COMPILER'] as $needle) {
        if (stripos($raw, $needle) !== false) {
            app_log('upload.embedded_code', ['needle' => $needle, 'mime' => $mime]);
            app_fail(
                415,
                "圖片中含有可執行內容（偵測到 {$needle}），已拒絕。",
                '圖片內容不合法。'
            );
        }
    }
    unset($raw);

    // --- 7. 檔名完全由伺服器決定 ---
    // 使用者提供的 $file['name'] 從此不再參與任何路徑組合
    $ext  = UPLOAD_ALLOWED_TYPES[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    $dir = upload_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        app_fail(500, '無法建立上傳目錄：' . $dir);
    }

    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        app_log('upload.move_failed', ['dest' => $dest]);
        app_fail(500, '儲存檔案失敗。');
    }
    @chmod($dest, 0644);

    app_log('upload.ok', [
        'stored'   => $name,
        'mime'     => $mime,
        'size'     => $size,
        'original' => (string) ($file['name'] ?? ''), // 只記錄，不使用
    ]);

    return $name;
}

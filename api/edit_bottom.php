<?php
/**
 * 修改頁尾版權資料（舊路徑，保留相容）
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中改寫。
 *
 * 本檔原本是 edit_value.php 的複製品，同樣沒有身分驗證，
 * 是一條容易被遺漏的未授權寫入管道。現在改為統一委派給 edit_value.php，
 * 只保留一份邏輯與一份防護（require_login + csrf_check 都在該檔中）。
 */

declare(strict_types=1);

$_GET['table'] = 'bottom';
require __DIR__ . '/edit_value.php';

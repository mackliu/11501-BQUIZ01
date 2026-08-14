<?php
/**
 * 建立或重設管理員帳號（命令列工具）
 * ---------------------------------------------------------------
 * 2026-08-14 安全修復的配套工具。
 *
 * 密碼改為雜湊儲存後，就沒辦法再用 phpMyAdmin 直接手打密碼了 ——
 * 手打進去的明文雖然還能靠 api/login.php 的相容邏輯登入一次，
 * 但那條路徑是為了遷移舊資料而留的，不該當成正常做法。
 *
 * 用法：
 *     php db/create_admin.php <帳號> <密碼> [--id=1]
 *
 * 範例：
 *     php db/create_admin.php admin "我的新密碼2026!"
 *     php db/create_admin.php admin "我的新密碼2026!" --id=1
 *
 * 帳號已存在時會「重設密碼」，不會重複建立。
 * --id=1 用於指定主帳號的 id（程式會保護 id=1 不被後台刪改）。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("本工具只能從命令列執行。\n");
}

require_once __DIR__ . '/../api/db.php';

$args = array_slice($argv, 1);
$opts = ['id' => null];

foreach ($args as $i => $a) {
    if (str_starts_with($a, '--id=')) {
        $opts['id'] = (int) substr($a, 5);
        unset($args[$i]);
    }
}
$args = array_values($args);

if (count($args) < 2) {
    fwrite(STDERR, "用法：php db/create_admin.php <帳號> <密碼> [--id=1]\n");
    exit(1);
}

[$acc, $pw] = $args;
$acc = trim($acc);

if ($acc === '') {
    fwrite(STDERR, "錯誤：帳號不可為空白。\n");
    exit(1);
}
if (strlen($pw) < 8) {
    fwrite(STDERR, "錯誤：密碼長度至少 8 個字元。\n");
    exit(1);
}

/** @var DB $Admin */
$hash     = password_hash($pw, PASSWORD_DEFAULT);
$existing = $Admin->find(['acc' => $acc]);

if ($existing !== null) {
    $Admin->update((int) $existing['id'], ['pw' => $hash]);
    printf("已重設帳號 %s 的密碼（id=%d）。\n", $acc, (int) $existing['id']);
    exit(0);
}

// 指定 id（通常用於建立 id=1 的主帳號）
if ($opts['id'] !== null && $opts['id'] > 0) {
    $target = $Admin->find($opts['id']);
    if ($target !== null) {
        $Admin->update($opts['id'], ['acc' => $acc, 'pw' => $hash]);
        printf("已將 id=%d 的帳號改為 %s 並設定密碼。\n", $opts['id'], $acc);
        exit(0);
    }
    // id 不存在時只能靠 INSERT 指定，DB 類別不允許寫 id，改用原生語句
    $st = Database::pdo()->prepare(
        'INSERT INTO `admin` (`id`, `acc`, `pw`) VALUES (:id, :acc, :pw)'
    );
    $st->execute([':id' => $opts['id'], ':acc' => $acc, ':pw' => $hash]);
    printf("已建立管理員 %s（id=%d）。\n", $acc, $opts['id']);
    exit(0);
}

$newId = $Admin->insert(['acc' => $acc, 'pw' => $hash]);
printf("已建立管理員 %s（id=%d）。\n", $acc, $newId);

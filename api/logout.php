<?php
/**
 * 管理員登出
 * ---------------------------------------------------------------
 * 於 2026-08-14 的安全修復中新增。
 *
 * 舊版的「管理登出」按鈕只執行 document.cookie='user='，
 * 清掉一個系統根本沒在用的 cookie，PHPSESSID 依然有效 ——
 * 等於按了登出但其實沒登出。
 *
 * 本次因為開始真正強制登入（A01-1 / A01-2），必須同時提供可用的登出端點，
 * 否則使用者無法結束自己的工作階段。
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

csrf_check();
logout();

to('/index.php');

<?php
// 작성자: 김예원 — 공통 초기화. DB는 db() 호출 시에만 연결합니다.
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

const DATA_MIN_MONTH = '2015-01';
const DATA_MAX_MONTH = '2025-12';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isDataMonth(string $value): bool
{
    return preg_match('/^[0-9]{4}-(?:0[1-9]|1[0-2])$/D', $value) === 1
        && $value >= DATA_MIN_MONTH
        && $value <= DATA_MAX_MONTH;
}

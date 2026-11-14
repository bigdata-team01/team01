<?php
// 작성자: 김예원 — 공통 초기화. DB는 db() 호출 시에만 연결합니다.
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

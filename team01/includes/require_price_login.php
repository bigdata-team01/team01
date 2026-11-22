<?php
// 작성자: 이혜령 — 가격 관리 페이지 공통 로그인 확인.
// 유채은 로그인 코드의 세션 키(user_id, login_id)를 사용합니다.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    // POST도 저장 처리 전에 중단하고 GET 로그인 화면으로 이동합니다.
    header('Location: ../auth/login.php', true, 303);
    exit;
}

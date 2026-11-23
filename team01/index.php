<?php
// 작성자: 김예원 — 초기 진입 페이지. 최종 메인 화면과 공통 레이아웃은 이미소 담당.
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>이상기후와 과일 가격 · Team01</title>
    <link rel="stylesheet" href="assets/css/common.css">
</head>
<body>
<main>
    <p class="eyebrow">빅데이터응용 · Team01</p>
    <h1>이상기후와 과일 가격</h1>
    <p>KAMIS 과일 가격과 기상청 자료를 활용하는 분석 프로젝트입니다.</p>
    <section>
        <h2>가격 자료 관리</h2>
        <?php if (isset($_SESSION['user_id'])): ?>
            <p><?= e((string)($_SESSION['login_id'] ?? '회원')) ?>님 로그인 중 · <a href="auth/logout.php">로그아웃</a></p>
        <?php else: ?>
            <p>가격 등록·수정은 로그인 후 이용할 수 있습니다. <a href="auth/login.php">로그인</a> · <a href="auth/register.php">회원가입</a></p>
        <?php endif; ?>
        <ul>
            <li><a href="crud/price_insert.php">새 가격 등록</a></li>
            <li><a href="crud/price_edit_list.php">기존 가격 수정</a></li>
        </ul>
    </section>
    <section>
        <h2>개발 준비</h2>
        <p>공통 PHP 실행 환경이 준비되었습니다. 분석 기능과 데이터는 아직 준비 중입니다.</p>
        <ul>
            <li><a href="analysis/grouping.php">다중 그룹핑 집계</a></li>
            <li>Rollup / Drill down</li>
            <li><a href="analysis/ranking.php">Ranking · 소비지역별 월평균 가격 순위</a></li>
            <li>Windowing</li>
            <li>데이터 조회·등록·수정·삭제 및 로그인</li>
        </ul>
    </section>
</main>
</body>
</html>

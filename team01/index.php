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
        <h2>개발 준비</h2>
        <p>공통 PHP 실행 환경이 준비되었습니다. 분석 기능과 데이터는 아직 준비 중입니다.</p>
        <ul>
            <li>다중 그룹핑 집계</li>
            <li>Rollup / Drill down</li>
            <li>Ranking</li>
            <li>Windowing</li>
            <li>데이터 조회·등록·수정·삭제 및 로그인</li>
        </ul>
    </section>
</main>
</body>
</html>

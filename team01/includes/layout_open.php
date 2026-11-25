<?php
// 작성자: 이미소 — 공통 사이드바 레이아웃 (열기).
// $pageTitle, $pageName, $breadcrumb, $depth 를 include 전에 선언.
declare(strict_types=1);
$depth      ??= 0;
$pageTitle  ??= '이상기후와 과일 가격 · Team01';
$pageName   ??= '';
$breadcrumb ??= [];
$root = $depth === 0 ? '' : str_repeat('../', $depth);
$li  = fn(string $n) => $pageName === $n ? ' class="active"' : '';

$dataPages     = ['data', 'data-browse', 'data-add', 'data-edit'];
$analysisPages = ['grouping', 'rollup', 'ranking', 'windowing'];
$dataOpen     = in_array($pageName, $dataPages, true);
$analysisOpen = in_array($pageName, $analysisPages, true);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $root ?>assets/css/common.css">
    <link rel="stylesheet" href="<?= $root ?>assets/css/layout.css">
</head>
<body>
<div class="site-wrapper">
    <nav class="sidebar" aria-label="주요 메뉴">
        <div class="sidebar-header">
            <p class="sidebar-brand">Fruit × Climate</p>
            <p class="sidebar-eyebrow">Team01 | $_POST</p>
        </div>
        <ul class="sidebar-nav">
            <li<?= $li('overview') ?>>
                <a href="<?= $root ?>index.php">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 7L8 2l6 5v7a1 1 0 01-1 1H9v-4H7v4H3a1 1 0 01-1-1V7z"/></svg>
                    Overview
                </a>
            </li>

            <li class="nav-group<?= $dataOpen ? ' open' : '' ?>" data-group="management">
                <button class="nav-group-btn" aria-expanded="<?= $dataOpen ? 'true' : 'false' ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><ellipse cx="8" cy="4.5" rx="5.5" ry="2"/><path d="M2.5 4.5v3c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-3"/><path d="M2.5 7.5v3c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-3"/></svg>
                    Data Management
                    <svg class="nav-chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg>
                </button>
                <ul class="nav-sub">
                    <li<?= $li('data-browse') ?>>
                        <a href="<?= $root ?>crud/list.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="6.5" cy="6.5" r="4.5"/><path d="M11 11l3 3"/></svg>
                            Browse Records
                        </a>
                    </li>
                    <li<?= $li('data-add') ?>>
                        <a href="<?= $root ?>crud/price_insert.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M8 2v12M2 8h12"/></svg>
                            Add New Price
                        </a>
                    </li>
                    <li<?= $li('data-edit') ?>>
                        <a href="<?= $root ?>crud/price_edit_list.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11 2l3 3-8 8H3v-3L11 2z"/></svg>
                            Edit Prices
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-group<?= $analysisOpen ? ' open' : '' ?>" data-group="analysis">
                <button class="nav-group-btn" aria-expanded="<?= $analysisOpen ? 'true' : 'false' ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 14V9M6 14V5M10 14V8M14 14V3"/></svg>
                    Analysis
                    <svg class="nav-chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg>
                </button>
                <ul class="nav-sub">
                    <li<?= $li('grouping') ?>>
                        <a href="<?= $root ?>analysis/grouping.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 4h12M2 8h12M2 12h7"/><circle cx="13" cy="12" r="2"/></svg>
                            Grouping
                        </a>
                    </li>
                    <li<?= $li('rollup') ?>>
                        <a href="<?= $root ?>analysis/rollup.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="6" y="1" width="4" height="3" rx=".5"/><rect x="1" y="11" width="4" height="3" rx=".5"/><rect x="6" y="11" width="4" height="3" rx=".5"/><rect x="11" y="11" width="4" height="3" rx=".5"/><path d="M8 4v3M8 7H3v4M8 7v4M8 7h5v4"/></svg>
                            Rollup / Drilldown
                        </a>
                    </li>
                    <li<?= $li('ranking') ?>>
                        <a href="<?= $root ?>analysis/ranking.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 14V9M6 14V5M10 14V8M14 14V3"/></svg>
                            Ranking
                        </a>
                    </li>
                    <li<?= $li('windowing') ?>>
                        <a href="<?= $root ?>analysis/windowing.php">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
                            Windowing
                        </a>
                    </li>
                </ul>
            </li>
        </ul>

        <div class="sidebar-auth">
<?php if (isset($_SESSION['user_id'])): ?>
            <span class="sidebar-auth-user">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
                <?= htmlspecialchars((string)($_SESSION['login_id'] ?? '회원'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
            <a href="<?= $root ?>auth/logout.php" class="sidebar-auth-link">Logout</a>
<?php else: ?>
            <a href="<?= $root ?>auth/login.php" class="sidebar-auth-link">Login</a>
            <a href="<?= $root ?>auth/register.php" class="sidebar-auth-link">Sign Up</a>
<?php endif; ?>
        </div>

        <script>
        (function () {
            var KEYS = { management: 'nav-management', analysis: 'nav-analysis' };

            function getStored(k) {
                try { return sessionStorage.getItem(k); } catch (e) { return null; }
            }
            function setStored(k, v) {
                try { sessionStorage.setItem(k, v); } catch (e) {}
            }

            // sessionStorage에 저장된 값이 있으면 PHP 기본값(현재 페이지 기준 auto-open) 오버라이드
            document.querySelectorAll('.nav-group[data-group]').forEach(function (group) {
                var key    = KEYS[group.dataset.group];
                var stored = key ? getStored(key) : null;
                if (stored !== null) {
                    var open = stored === 'open';
                    group.classList.toggle('open', open);
                    var btn = group.querySelector('.nav-group-btn');
                    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
                // 저장된 값 없으면 PHP가 세팅한 open 클래스 그대로 유지
            });

            // 클릭 시에만 sessionStorage 저장
            document.querySelectorAll('.nav-group-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var group = this.closest('.nav-group');
                    var open  = group.classList.toggle('open');
                    this.setAttribute('aria-expanded', open ? 'true' : 'false');
                    var key = KEYS[group.dataset.group];
                    if (key) setStored(key, open ? 'open' : 'closed');
                });
            });
        }());
        </script>
    </nav>
    <main class="content-area">
<?php if (!empty($breadcrumb)): ?>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?php if ($i > 0): ?><span class="bc-sep">/</span><?php endif; ?>
                <?php if (isset($crumb['url'])): ?>
                    <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></a>
                <?php else: ?>
                    <span class="bc-current"><?= htmlspecialchars($crumb['label'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
<?php endif; ?>
        <div class="content-body">

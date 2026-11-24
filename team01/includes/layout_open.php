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
            <li<?= $li('data') ?>>
                <a href="<?= $root ?>crud/price_insert.php">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><ellipse cx="8" cy="4.5" rx="5.5" ry="2"/><path d="M2.5 4.5v3c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-3"/><path d="M2.5 7.5v3c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-3"/></svg>
                    Data Management
                </a>
            </li>
            <li<?= $li('grouping') ?>>
                <a href="<?= $root ?>analysis/grouping.php">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 4h12M2 8h12M2 12h7"/><circle cx="13" cy="12" r="2"/></svg>
                    Grouping
                </a>
            </li>
            <li class="nav-disabled">
                <span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="6" y="1" width="4" height="3" rx=".5"/><rect x="1" y="11" width="4" height="3" rx=".5"/><rect x="6" y="11" width="4" height="3" rx=".5"/><rect x="11" y="11" width="4" height="3" rx=".5"/><path d="M8 4v3M8 7H3v4M8 7v4M8 7h5v4"/></svg>
                    Rollup / Drilldown
                </span>
            </li>
            <li<?= $li('ranking') ?>>
                <a href="<?= $root ?>analysis/ranking.php">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 14V9M6 14V5M10 14V8M14 14V3"/></svg>
                    Ranking
                </a>
            </li>
            <li class="nav-disabled">
                <span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
                    Windowing
                </span>
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

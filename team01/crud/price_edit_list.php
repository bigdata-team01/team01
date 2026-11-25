<?php
// 작성자: 이혜령 — UPDATE 대상 선택 목록. 공통 검색 페이지와 별개입니다.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/require_price_login.php';
$input = $_GET['page'] ?? '1';
$page = is_string($input) && ctype_digit($input) ? max(1, min(1000000, (int)$input)) : 1;
$rows = [];
$error = false;
try {
    $pdo = db();
    $total = (int)$pdo->query('SELECT COUNT(*) FROM PRICE_MONTHLY')->fetchColumn();
    $pages = max(1, (int)ceil($total / 50));
    $page = min($page, $pages);
    $stmt = $pdo->prepare('SELECT p.*, f.fruit_name, v.variety_name, v.unit, m.market_name FROM PRICE_MONTHLY p JOIN FRUIT_VARIETY v ON v.variety_id=p.variety_id JOIN FRUIT f ON f.fruit_id=v.fruit_id JOIN MARKET_REGION m ON m.market_id=p.market_id ORDER BY p.ym DESC,p.price_id DESC LIMIT 50 OFFSET ?');
    $stmt->bindValue(1, ($page-1)*50, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('price_edit_list failed: '.$e->getMessage());
    $error = true;
}
$pageTitle  = '수정할 가격 선택 · Team01';
$pageName   = 'data-edit';
$breadcrumb = [
    ['label' => 'Overview',         'url' => '../index.php'],
    ['label' => 'Data Management'],
    ['label' => 'Edit Prices'],
];
$depth = 1;
require_once dirname(__DIR__) . '/includes/layout_open.php';
?>
<link rel="stylesheet" href="../assets/css/price-form.css">
<div>
<h1>수정할 가격 선택</h1>
<p>최근 월부터 표시됩니다. 수정할 자료의 ‘수정’을 눌러주세요.</p>
<?php if ($error): ?><p class="errors" role="alert">목록을 불러오지 못했습니다. 잠시 후 다시 시도해주세요.</p>
<?php elseif (!$rows): ?><p class="notice">등록된 가격 자료가 없습니다. 먼저 <a href="price_insert.php">가격을 등록</a>해주세요.</p>
<?php else: ?>
<div class="table-scroll" tabindex="0" role="region" aria-label="수정할 가격 목록">
<table><thead><tr><th scope="col">월</th><th scope="col">품목·품종</th><th scope="col">도시</th><th scope="col">등급</th><th scope="col">평균 가격</th><th scope="col">작업</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e(substr($row['ym'],0,7)) ?></td><td><?= e($row['fruit_name'].' / '.$row['variety_name']) ?><br><small><?= e($row['unit']) ?> 기준</small></td><td><?= e($row['market_name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e(number_format((int)$row['avg_price'])) ?>원</td><td><a href="price_update.php?id=<?= e((string)$row['price_id']) ?>" aria-label="등록 번호 <?= e((string)$row['price_id']) ?> 수정">수정</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<nav class="pagination" aria-label="목록 페이지">
<?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">← 이전</a><?php endif; ?>
<span><?= $page ?> / <?= $pages ?> 페이지</span>
<?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>">다음 →</a><?php endif; ?>
</nav>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_close.php'; ?>

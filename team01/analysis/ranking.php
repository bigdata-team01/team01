<?php
// 작성자: 박선영 — 월·품종·등급별 소비지역 평균가격 순위 분석.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

$grades = ['상품', '중품', 'S과', 'M과', 'L과'];
$minMonth = '2015-01';
$maxMonth = '2025-12';
$monthInput = is_string($_GET['ym'] ?? null) ? trim($_GET['ym']) : '';
$fruitInput = is_string($_GET['fruit'] ?? null) ? trim($_GET['fruit']) : '';
$gradeInput = is_string($_GET['grade'] ?? null) ? trim($_GET['grade']) : '';
$orderInput = is_string($_GET['order'] ?? null) ? $_GET['order'] : 'high';

$month = preg_match('/^[0-9]{4}-(?:0[1-9]|1[0-2])$/D', $monthInput)
    && $monthInput >= $minMonth
    && $monthInput <= $maxMonth
    ? $monthInput
    : '';
$grade = in_array($gradeInput, $grades, true) ? $gradeInput : '';
$order = in_array($orderInput, ['high', 'low'], true) ? $orderInput : 'high';
$rankDirection = $order === 'low' ? 'ASC' : 'DESC';

$rows = [];
$fruitNames = [];
$error = false;

try {
    $pdo = db();
    $fruitNames = $pdo->query(
        'SELECT DISTINCT fruit_name FROM FRUIT ORDER BY fruit_name'
    )->fetchAll(PDO::FETCH_COLUMN);
    $fruit = in_array($fruitInput, $fruitNames, true) ? $fruitInput : '';

    $conditions = [];
    $params = [];

    if ($month !== '') {
        $conditions[] = 'ym = ?';
        $params[] = $month . '-01';
    }
    if ($fruit !== '') {
        $conditions[] = 'fruit_name = ?';
        $params[] = $fruit;
    }
    if ($grade !== '') {
        $conditions[] = 'grade = ?';
        $params[] = $grade;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sql = "
        SELECT
            ym,
            fruit_name,
            variety_name,
            unit,
            grade,
            market_name,
            avg_price,
            RANK() OVER (
                PARTITION BY ym, fruit_name, variety_name, unit, grade
                ORDER BY avg_price {$rankDirection}
            ) AS price_rank,
            COUNT(*) OVER (
                PARTITION BY ym, fruit_name, variety_name, unit, grade
            ) AS market_count
        FROM v_price_weather
        {$where}
        ORDER BY ym DESC, fruit_name, variety_name, unit, grade, price_rank, market_name
        LIMIT 500
    ";
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('ranking analysis failed: ' . $exception->getMessage());
    $fruit = '';
    $error = true;
}

$rankLabel = $order === 'low' ? '낮은 가격 순' : '높은 가격 순';
$pageTitle  = '소비지역 가격 Ranking · Team01';
$pageName   = 'ranking';
$breadcrumb = [
    ['label' => 'Overview',  'url' => '../index.php'],
    ['label' => 'Analysis'],
    ['label' => 'Ranking'],
];
$depth = 1;
require_once dirname(__DIR__) . '/includes/layout_open.php';
?>
<link rel="stylesheet" href="../assets/css/ranking.css">
<div class="ranking-page">

    <h1>소비지역별 가격 순위 분석</h1>
    <p class="page-desc">동일한 월·과일·품종·거래단위·등급 조건에서 소비지역별 평균가격 순위를 비교합니다.</p>

    <section>
        <h2>조회 조건</h2>
        <form method="get" class="filter-grid">
            <div class="field">
                <label for="ym">기준 월</label>
                <input id="ym" name="ym" type="month" min="<?= e($minMonth) ?>" max="<?= e($maxMonth) ?>" value="<?= e($month) ?>">
            </div>
            <div class="field">
                <label for="fruit">과일</label>
                <select id="fruit" name="fruit">
                    <option value="">전체</option>
                    <?php foreach ($fruitNames as $fruitName): ?>
                        <option value="<?= e((string)$fruitName) ?>" <?= $fruit === $fruitName ? 'selected' : '' ?>><?= e((string)$fruitName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="grade">등급</label>
                <select id="grade" name="grade">
                    <option value="">전체</option>
                    <?php foreach ($grades as $gradeOption): ?>
                        <option value="<?= e($gradeOption) ?>" <?= $grade === $gradeOption ? 'selected' : '' ?>><?= e($gradeOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="order">순위 기준</label>
                <select id="order" name="order">
                    <option value="high" <?= $order === 'high' ? 'selected' : '' ?>>가격이 높은 지역부터</option>
                    <option value="low" <?= $order === 'low' ? 'selected' : '' ?>>가격이 낮은 지역부터</option>
                </select>
            </div>
            <button type="submit">순위 조회</button>
        </form>
    </section>

    <section>
        <h2>분석 결과</h2>
        <p class="result-summary">순위 기준: <strong><?= e($rankLabel) ?></strong> · 최대 500행</p>
        <?php if ($error): ?>
            <p class="errors" role="alert">순위 자료를 불러오지 못했습니다. DB와 공통 뷰 상태를 확인해주세요.</p>
        <?php elseif (!$rows): ?>
            <p class="notice">조건에 맞는 가격 자료가 없습니다.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="소비지역별 가격 순위">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">월</th>
                        <th scope="col">과일·품종</th>
                        <th scope="col">등급</th>
                        <th scope="col">거래단위</th>
                        <th scope="col">소비지역</th>
                        <th scope="col">평균가격</th>
                        <th scope="col">순위</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e(substr((string)$row['ym'], 0, 7)) ?></td>
                            <td><?= e((string)$row['fruit_name']) ?> / <?= e((string)$row['variety_name']) ?></td>
                            <td><?= e((string)$row['grade']) ?></td>
                            <td><?= e((string)$row['unit']) ?></td>
                            <td><?= e((string)$row['market_name']) ?></td>
                            <td class="number"><?= e(number_format((int)$row['avg_price'])) ?>원</td>
                            <td class="rank"><strong><?= e((string)$row['price_rank']) ?>위</strong> / <?= e((string)$row['market_count']) ?>곳</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_close.php'; ?>

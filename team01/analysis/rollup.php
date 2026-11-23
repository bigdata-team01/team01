<?php
// 작성자: 김예원 — Rollup / Drill-down 분석 화면.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/rollup.php';
header('Content-Type: text/html; charset=UTF-8');

$state = rollupInput([]);
$options = ['unit' => [], 'grade' => [], 'market' => []];
$rows = [];
$error = null;
try {
    $state = rollupInput($_GET);
    $connection = db();
    foreach (['unit' => 'unit', 'grade' => 'grade', 'market' => 'market_name'] as $key => $column) {
        $options[$key] = $connection->query(
            "SELECT DISTINCT $column FROM v_price_weather WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column"
        )->fetchAll(PDO::FETCH_COLUMN);
    }
    foreach (['unit', 'grade'] as $key) {
        if (!isset($_GET[$key])) {
            $state[$key] = (string) ($options[$key][0] ?? '');
        }
    }
    // 기본 등급은 선택 단위에 실제로 존재하는 값으로 선택합니다.
    if (!isset($_GET['grade']) && $state['unit'] !== '') {
        $defaultGrade = $connection->prepare('SELECT grade FROM v_price_weather WHERE unit = ? ORDER BY grade LIMIT 1');
        $defaultGrade->execute([$state['unit']]);
        $state['grade'] = (string) ($defaultGrade->fetchColumn() ?: '');
    }
    foreach ($options as $key => $values) {
        if ($state[$key] !== '' && !in_array($state[$key], $values, true)) {
            throw new InvalidArgumentException('선택한 조건이 데이터에 없습니다. 조건을 초기화해 주세요.');
        }
    }
    if ($state['unit'] !== '' && $state['grade'] !== '') {
        [$sql, $params] = rollupQuery($state);
        $statement = $connection->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    http_response_code(503);
    error_log('Rollup query failed: ' . $exception->getMessage());
    $error = '분석 데이터를 불러올 수 없습니다. DB 연결과 v_price_weather 뷰 및 데이터 준비 상태를 확인하세요.';
}
$level = $state['fruit'] !== '' ? '품종' : ($state['category'] !== '' ? '품목' : '과일군');
if ($state['axis'] === 'time') {
    $level = $state['year'] === '' ? '연도' : '월';
}
$hasData = array_sum(array_column($rows, 'row_count')) > 0;
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rollup / Drill-down · Team01</title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/rollup.css">
</head>
<body>
<main>
    <a href="../index.php">← 메인</a>
    <p class="eyebrow">과일 가격 · 계층별 분석</p>
    <h1>Rollup / Drill-down</h1>
    <p>과일군 → 품목 → 품종 또는 연도 → 월 순서로 상세 가격을 살펴보세요.</p>
    <section>
        <h2>조회 조건</h2>
        <form method="get" action="rollup.php">
            <label>분석 기준
                <select name="axis">
                    <option value="fruit" <?= $state['axis'] === 'fruit' ? 'selected' : '' ?>>과일 계층</option>
                    <option value="time" <?= $state['axis'] === 'time' ? 'selected' : '' ?>>시간 계층</option>
                </select>
            </label>
            <label>시작 월 <input type="month" name="start" value="<?= e($state['start']) ?>"></label>
            <label>종료 월 <input type="month" name="end" value="<?= e($state['end']) ?>"></label>
            <?php foreach (['unit' => '거래 단위', 'grade' => '등급', 'market' => '소비 도시'] as $key => $label): ?>
                <label><?= e($label) ?>
                    <select name="<?= e($key) ?>" <?= $key !== 'market' ? 'required' : '' ?>>
                        <?php if ($key === 'market'): ?><option value="">전체 도시</option><?php endif; ?>
                        <?php foreach ($options[$key] as $value): ?>
                            <option value="<?= e($value) ?>" <?= $state[$key] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <input type="hidden" name="category" value="<?= e($state['category']) ?>">
            <input type="hidden" name="fruit" value="<?= e($state['fruit']) ?>">
            <input type="hidden" name="year" value="<?= e($state['year']) ?>">
            <button type="submit">조회</button>
            <a href="rollup.php">조건 초기화</a>
        </form>
        <p class="note">서로 다른 거래 단위와 등급은 섞지 않습니다. 평균 가격은 선택 기간의 품종·도시별 월평균 가격을 동일 가중치로 평균한 값입니다. 거래량 가중 평균이나 일별 가격 전체의 평균이 아닙니다.</p>
    </section>
    <section>
        <nav aria-label="현재 분석 경로">
            <?php if ($state['axis'] === 'time'): ?>
            <a href="<?= e(rollupUrl($state, ['year' => ''])) ?>">전체 연도</a>
            <?php if ($state['year'] !== ''): ?> / <?= e($state['year']) ?>년<?php endif; ?> ·
            <?php endif; ?>
            <a href="<?= e(rollupUrl($state, ['category' => '', 'fruit' => ''])) ?>">전체 과일군</a>
            <?php if ($state['category'] !== ''): ?>
                / <a href="<?= e(rollupUrl($state, ['fruit' => ''])) ?>"><?= e($state['category']) ?></a>
            <?php endif; ?>
            <?php if ($state['fruit'] !== ''): ?> / <?= e($state['fruit']) ?><?php endif; ?>
        </nav>
        <h2><?= e($level) ?>별 가격</h2>
        <?php if ($state['axis'] === 'fruit' && $state['year'] !== ''): ?>
            <p>선택 연도: <?= e($state['year']) ?>년</p>
        <?php endif; ?>
        <?php if ($state['year'] !== ''): ?>
            <a href="<?= e(rollupUrl($state, ['year' => ''])) ?>">↑ 전체 연도로 (Rollup)</a>
        <?php endif; ?>
        <?php if ($state['category'] !== ''): ?>
            <a href="<?= e(rollupUrl($state, $state['fruit'] !== '' ? ['fruit' => ''] : ['category' => '', 'fruit' => ''])) ?>">↑ 상위 집계로 (Rollup)</a>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <p role="alert"><?= e($error) ?></p>
        <?php elseif (!$hasData): ?>
            <p role="status">조회 조건에 맞는 데이터가 없습니다.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <caption><?= e($state['unit']) ?> · <?= e($state['grade']) ?> · 가격 단위: 원</caption>
                    <thead><tr><th scope="col"><?= e($level) ?></th><th scope="col">가격 표본 수</th><th scope="col">평균 가격</th><th scope="col">최저 가격</th><th scope="col">최고 가격</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="<?= $row['name'] === null ? 'summary' : '' ?>">
                            <th scope="row">
                            <?php if ($row['name'] === null): ?>현재 범위 전체 집계
                            <?php elseif ($row['name'] !== '' && $level === '연도'): ?>
                                <a href="<?= e(rollupUrl($state, ['year' => $row['name']])) ?>"><?= e($row['name']) ?> →</a>
                            <?php elseif ($row['name'] !== '' && in_array($level, ['과일군', '품목'], true)): ?>
                                <a href="<?= e(rollupUrl($state, $level === '과일군' ? ['category' => $row['name'], 'fruit' => ''] : ['fruit' => $row['name']])) ?>"><?= e($row['name']) ?> →</a>
                            <?php else: ?><?= e($row['name'] === '' ? '이름 미등록' : $row['name']) ?><?php endif; ?>
                            </th>
                            <td><?= e((string) $row['price_count']) ?></td>
                            <td><?= e(rollupNumber($row['mean_price'])) ?></td>
                            <td><?= e(rollupNumber($row['lowest_price'])) ?></td>
                            <td><?= e(rollupNumber($row['highest_price'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="note">전체 집계는 원본 행에서 다시 계산합니다. 표본 수는 월평균 가격이 있는 행 수이며, 결측 가격은 평균에서 제외됩니다. 최저·최고는 월내 일평균 가격의 최저·최고입니다. 품종과 월이 각 계층의 마지막 단계입니다.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

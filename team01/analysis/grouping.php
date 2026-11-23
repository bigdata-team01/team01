<?php
// 작성자: 이미소 — 다중 그룹핑 집계 분석
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

const ALLOWED_GROUPS = [
    'year'          => '연도',
    'month'         => '월',
    'category_name' => '카테고리',
    'fruit_name'    => '과일',
    'variety_name'  => '품종',
    'grade'         => '등급',
    'market_name'   => '소비지역',
    'area_name'     => '생산지역',
];

$selectedGroups = [];
if (isset($_GET['groups']) && is_array($_GET['groups'])) {
    foreach ($_GET['groups'] as $g) {
        if (
            is_string($g) &&
            array_key_exists($g, ALLOWED_GROUPS) &&
            !in_array($g, $selectedGroups, true)
        ) {
            $selectedGroups[] = $g;
        }
    }
}

$yearFromRaw = is_string($_GET['year_from'] ?? null) ? trim($_GET['year_from']) : '';
$yearToRaw   = is_string($_GET['year_to']   ?? null) ? trim($_GET['year_to'])   : '';
$yearFrom    = preg_match('/^[0-9]{4}$/', $yearFromRaw) ? (int)$yearFromRaw : null;
$yearTo      = preg_match('/^[0-9]{4}$/', $yearToRaw)   ? (int)$yearToRaw   : null;
$yearInputError =
    ($yearFromRaw !== '' && $yearFrom === null) ||
    ($yearToRaw   !== '' && $yearTo   === null);

$dbYearMin = null;
$dbYearMax = null;
try {
    $yr = db()->query('SELECT MIN(year) AS y_min, MAX(year) AS y_max FROM v_price_weather')->fetch();
    if ($yr && $yr['y_min'] !== null) {
        $dbYearMin = (int)$yr['y_min'];
        $dbYearMax = (int)$yr['y_max'];
    }
} catch (PDOException $e) {
    // DB 미준비 시 무시
}

$yearRangeError = !$yearInputError
    && $yearFrom !== null
    && $yearTo !== null
    && $yearFrom > $yearTo;

$rows      = [];
$error     = false;
$submitted = !empty($selectedGroups)
    && !$yearInputError
    && !$yearRangeError;

if ($submitted) {
    try {
        $pdo        = db();
        $groupCols  = implode(', ', $selectedGroups);
        $conditions = [];
        $params     = [];

        if ($yearFrom !== null) {
            $conditions[] = 'year >= ?';
            $params[]     = $yearFrom;
        }
        if ($yearTo !== null) {
            $conditions[] = 'year <= ?';
            $params[]     = $yearTo;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT
                {$groupCols},
                COUNT(*)                     AS row_count,
                SUM(avg_price)               AS sum_price,
                ROUND(AVG(avg_price))        AS avg_price,
                MAX(avg_price)               AS max_price,
                MIN(avg_price)               AS min_price,
                ROUND(AVG(avg_temp), 1)      AS avg_temp,
                ROUND(AVG(total_rain), 1)    AS avg_rain
            FROM v_price_weather
            {$where}
            GROUP BY {$groupCols}
            ORDER BY {$groupCols}
            LIMIT 1000
        ";

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
    } catch (PDOException $exception) {
        error_log('grouping analysis failed: ' . $exception->getMessage());
        $error = true;
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>다중 그룹핑 집계 · Team01</title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/grouping.css">
</head>
<body>
<main class="grouping-page">
    <p class="eyebrow">고급 분석 · 다중 그룹핑</p>
    <a href="../index.php">← 메인으로</a>
    <h1>다중 그룹핑 집계</h1>
    <p>그룹핑 기준을 여러 개 선택하면 해당 조합별로 가격과 기상 집계를 보여줍니다.</p>

    <section>
        <h2>그룹핑 기준 선택</h2>
        <form method="get">
            <fieldset class="group-checks">
                <legend>그룹 기준 <span class="required">(1개 이상 선택)</span></legend>
                <div class="checks-grid">
                    <?php foreach (ALLOWED_GROUPS as $col => $label): ?>
                        <label class="check-label">
                            <input
                                type="checkbox"
                                name="groups[]"
                                value="<?= e($col) ?>"
                                <?= in_array($col, $selectedGroups, true) ? 'checked' : '' ?>
                            >
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <p class="agg-note">집계 항목: 건수 · 평균가격 합계(원) · 평균가격(원) · 최고 평균가격(원) · 최저 평균가격(원) · 평균기온(℃) · 평균 월강수량(mm)</p>
            <p class="agg-note">연도<?= $dbYearMin !== null ? ': ' . e((string)$dbYearMin) . '~' . e((string)$dbYearMax) . '년' : '' ?> · 비워 두면 전체 기간 조회</p>
            <div class="filter-row">
                <div class="field">
                    <label for="year_from">연도 시작</label>
                    <input id="year_from" name="year_from" type="number"
                           placeholder="<?= $dbYearMin !== null ? e((string)$dbYearMin) : '예) 2015' ?>"
                           value="<?= $yearFrom !== null ? e((string)$yearFrom) : '' ?>">
                </div>
                <div class="field">
                    <label for="year_to">연도 끝</label>
                    <input id="year_to" name="year_to" type="number"
                           placeholder="<?= $dbYearMax !== null ? e((string)$dbYearMax) : '예) 2025' ?>"
                           value="<?= $yearTo !== null ? e((string)$yearTo) : '' ?>">
                </div>
                <button type="submit">집계 조회</button>
            </div>
            <?php if ($yearInputError): ?>
                <p class="warn-note" role="alert">연도는 4자리 숫자로 입력해주세요.</p>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>집계 결과</h2>
        <?php if ($yearRangeError): ?>
            <p class="errors" role="alert">시작 연도가 종료 연도보다 클 수 없습니다.</p>
        <?php elseif (!$submitted): ?>
            <p class="notice">그룹핑 기준을 1개 이상 선택한 뒤 조회하세요.</p>
        <?php elseif ($error): ?>
            <p class="errors" role="alert">집계 자료를 불러오지 못했습니다. DB와 공통 뷰 상태를 확인해주세요.</p>
        <?php elseif (!$rows): ?>
            <p class="notice">조건에 맞는 자료가 없습니다.</p>
        <?php else: ?>
            <p class="result-summary">
                그룹 기준:
                <strong><?= e(implode(' + ', array_map(fn($c) => ALLOWED_GROUPS[$c], $selectedGroups))) ?></strong>
                · <?= count($rows) ?>개 그룹 (최대 1000)
            </p>
            <div class="table-scroll" tabindex="0" role="region" aria-label="다중 그룹핑 집계 결과">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ($selectedGroups as $col): ?>
                            <th scope="col"><?= e(ALLOWED_GROUPS[$col]) ?></th>
                        <?php endforeach; ?>
                        <th scope="col">건수</th>
                        <th scope="col">평균가격 합계(원)¹</th>
                        <th scope="col">평균가격(원)</th>
                        <th scope="col">최고 평균가격(원)</th>
                        <th scope="col">최저 평균가격(원)</th>
                        <th scope="col">평균기온(℃)²</th>
                        <th scope="col">평균 월강수량(mm)²</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($selectedGroups as $col): ?>
                                <td><?= e((string)($row[$col] ?? '')) ?></td>
                            <?php endforeach; ?>
                            <td class="number"><?= e((string)$row['row_count']) ?></td>
                            <td class="number"><?= $row['sum_price'] !== null ? e(number_format((int)$row['sum_price'])) . '원' : '-' ?></td>
                            <td class="number"><?= $row['avg_price'] !== null ? e(number_format((int)$row['avg_price'])) . '원' : '-' ?></td>
                            <td class="number"><?= $row['max_price'] !== null ? e(number_format((int)$row['max_price'])) . '원' : '-' ?></td>
                            <td class="number"><?= $row['min_price'] !== null ? e(number_format((int)$row['min_price'])) . '원' : '-' ?></td>
                            <td class="number"><?= $row['avg_temp'] !== null ? e((string)$row['avg_temp']) : '-' ?></td>
                            <td class="number"><?= $row['avg_rain'] !== null ? e((string)$row['avg_rain']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-note">¹ 평균가격 합계는 매출액이 아닌 그룹 내 월 평균가격 값의 합입니다.</p>
            <p class="table-note">² 기상 집계는 가격 관측행 기준 평균값입니다. 같은 생산지역·월의 기상값이 품종·소비지역 조합 수만큼 반복되므로, 그룹핑 기준과 무관하게 단순 지역 평균과 다를 수 있습니다.</p>
            <?php if (count($rows) === 1000): ?>
            <p class="table-note">결과가 1,000개에 달합니다. 실제 그룹이 더 있을 수 있으니 연도 범위나 그룹 기준을 좁혀 재조회하세요.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

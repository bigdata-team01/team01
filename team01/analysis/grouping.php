<?php
// 작성자: 이미소 — 다중 그룹핑 집계 분석
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

const ALLOWED_GROUPS = [
    'fruit_name'    => '과일',
    'variety_name'  => '품종',
    'year'          => '연도',
    'month'         => '월',
    'grade'         => '등급',
    'market_name'   => '소비지역',
    'area_name'     => '생산지역',
];

const ALLOWED_CLIMATE = [
    'all'          => '전체 기간',
    'heatwave'     => '폭염 발생 월',
    'frost'        => '봄철 저온 발생 월',
    'heavy_rain'   => '집중호우 발생 월',
    'drought'      => '가뭄 발생 월',
    'rainy_season' => '이상 장마 발생 월',
];

$climateRaw    = is_string($_GET['climate'] ?? null) ? trim($_GET['climate']) : 'all';
$climateFilter = array_key_exists($climateRaw, ALLOWED_CLIMATE) ? $climateRaw : 'all';

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

// 표시용 그룹핑 소섹션 (name/value/로직 무관, UI 렌더링 전용)
$groupSections = [
    '품목' => ['fruit_name', 'variety_name', 'grade'],
    '기간' => ['year', 'month'],
    '지역' => ['market_name', 'area_name'],
];

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
                ROUND(AVG(total_rain), 1)    AS avg_rain,
                MAX(CASE WHEN heatwave_days >= 1 THEN 1 ELSE 0 END)  AS heatwave_hit,
                MAX(CASE WHEN frost_days >= 1 THEN 1 ELSE 0 END)      AS frost_hit,
                MAX(CASE WHEN heavy_rain_days >= 1 THEN 1 ELSE 0 END) AS heavy_rain_hit,
                MAX(CASE WHEN drought_level >= 1 THEN 1 ELSE 0 END)   AS drought_hit,
                MAX(CASE WHEN EXISTS (
                    SELECT 1
                    FROM RAINY_SEASON rs
                    JOIN PRODUCTION_AREA pa ON rs.area_id = pa.area_id
                    WHERE pa.area_name = vpw.area_name
                      AND rs.year      = vpw.year
                      AND vpw.ym BETWEEN rs.start_date AND rs.end_date
                      AND rs.rain_days >= CASE
                          WHEN pa.area_name IN ('서귀포','안동','장수','영주','영천') THEN 23
                          ELSE 24
                      END
                ) THEN 1 ELSE 0 END)                                   AS rainy_season_hit
            FROM v_price_weather vpw
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
$pageTitle  = '다중 그룹핑 집계 · Team01';
$pageName   = 'grouping';
$breadcrumb = [
    ['label' => 'Overview',  'url' => '../index.php'],
    ['label' => 'Analysis'],
    ['label' => 'Grouping'],
];
$depth = 1;
require_once dirname(__DIR__) . '/includes/layout_open.php';
?>
<link rel="stylesheet" href="../assets/css/grouping.css">
<div class="grouping-page">
    <h1>다중 기준 집계 분석</h1>
    <p class="page-desc">연도·과일·지역 등 여러 기준을 조합하고, 이상기후 조건에 따른 가격 데이터를 집계합니다.</p>

    <section>
        <h2>그룹핑 기준 선택</h2>
        <form method="get">
            <fieldset class="group-checks">
                <legend>그룹 기준 <span class="required">(1개 이상 선택)</span></legend>
                <?php foreach ($groupSections as $sectionLabel => $cols): ?>
                    <div class="group-section">
                        <span class="group-sub-label"><?= e($sectionLabel) ?></span>
                        <div class="checks-inline">
                            <?php foreach ($cols as $col): ?>
                                <label class="check-label">
                                    <input
                                        type="checkbox"
                                        name="groups[]"
                                        value="<?= e($col) ?>"
                                        <?= in_array($col, $selectedGroups, true) ? 'checked' : '' ?>
                                    >
                                    <?= e(ALLOWED_GROUPS[$col]) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </fieldset>

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

            <p class="agg-note">연도<?= $dbYearMin !== null ? ': ' . e((string)$dbYearMin) . '~' . e((string)$dbYearMax) . '년' : '' ?> · 비워 두면 전체 기간 조회</p>
            <p class="agg-note">집계 항목: 건수 · 평균가격 합계(원) · 평균가격(원) · 최고 평균가격(원) · 최저 평균가격(원) · 평균기온(℃) · 평균 월강수량(mm)</p>
            
            <hr class="form-divider">

            <fieldset class="climate-checks">
                <legend>이상기후 조건 <span class="required">(1개 선택)</span></legend>
                <div class="climate-radios">
                    <?php foreach (ALLOWED_CLIMATE as $val => $label): ?>
                        <label class="check-label">
                            <input
                                type="radio"
                                name="climate"
                                value="<?= e($val) ?>"
                                <?= $climateFilter === $val ? 'checked' : '' ?>
                            >
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <p class="agg-note climate-note">※ 이상기후 조건은 조회 후 즉시 전환할 수 있습니다. 해당 조건이 발생한 그룹 행이 강조 표시됩니다.</p>            
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
                · <?= count($rows) ?>개 그룹 (최대 1000)<span id="climate-status"></span>
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
                        <tr data-heatwave="<?= (int)($row['heatwave_hit'] ?? 0) ?>"
                            data-frost="<?= (int)($row['frost_hit'] ?? 0) ?>"
                            data-heavy-rain="<?= (int)($row['heavy_rain_hit'] ?? 0) ?>"
                            data-drought="<?= (int)($row['drought_hit'] ?? 0) ?>"
                            data-rainy-season="<?= (int)($row['rainy_season_hit'] ?? 0) ?>">
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
<script>
(function () {
    var attrMap = {
        heatwave:     'data-heatwave',
        frost:        'data-frost',
        heavy_rain:   'data-heavy-rain',
        drought:      'data-drought',
        rainy_season: 'data-rainy-season',
    };
    var labels = <?= json_encode(ALLOWED_CLIMATE, JSON_UNESCAPED_UNICODE) ?>;
    var statusEl = document.getElementById('climate-status');

    function applyHighlight(value) {
        var trs = document.querySelectorAll('tbody tr');
        var count = 0;
        trs.forEach(function (tr) {
            if (value === 'all') {
                tr.classList.remove('row-climate');
            } else {
                var hit = tr.getAttribute(attrMap[value]) === '1';
                tr.classList.toggle('row-climate', hit);
                if (hit) count++;
            }
        });
        if (statusEl) {
            statusEl.textContent = value === 'all'
                ? ''
                : ' · 이상기후 조건: ' + labels[value] + ' — ' + count + '개 그룹 강조';
        }
    }

    document.querySelectorAll('input[name="climate"]').forEach(function (radio) {
        radio.addEventListener('change', function () { applyHighlight(this.value); });
    });

    var checked = document.querySelector('input[name="climate"]:checked');
    if (checked) applyHighlight(checked.value);
}());
</script>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_close.php'; ?>

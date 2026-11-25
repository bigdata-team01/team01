<?php
// 작성자: 박시원 — 품종별 가격 시계열 Windowing 분석.
// 3개월 이동평균, 전월·전년 동월 대비 상승률, 주산지의 당월·직전 3개월 이상기후 지표.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

const WINDOWING_GRADES = ['상품', '중품', 'S과', 'M과', 'L과'];
const WINDOWING_DROUGHT_LABELS = [0 => '없음', 1 => '약함', 2 => '보통', 3 => '심함'];
const WINDOWING_MAX_ROWS = 1000;

/** 기준 가격 대비 변동률(%). 비교할 달의 가격이 없으면 null. */
function changeRate(int $price, ?int $basePrice): ?float
{
    if ($basePrice === null || $basePrice === 0) {
        return null;
    }
    return ($price - $basePrice) * 100 / $basePrice;
}

function rateCell(?float $rate): string
{
    if ($rate === null) {
        return '<td class="number muted">–</td>';
    }
    $class = $rate > 0 ? 'up' : ($rate < 0 ? 'down' : '');
    $sign = $rate > 0 ? '+' : ($rate < 0 ? '−' : '');
    return '<td class="number ' . $class . '">' . $sign . e(number_format(abs($rate), 1)) . '%</td>';
}

function numberCell(mixed $value, int $decimals = 0, string $suffix = '', string $class = ''): string
{
    if ($value === null) {
        return '<td class="number muted ' . $class . '">–</td>';
    }
    return '<td class="number ' . $class . '">' . e(number_format((float) $value, $decimals)) . e($suffix) . '</td>';
}

function droughtCell(mixed $level): string
{
    if ($level === null) {
        return '<td class="muted">–</td>';
    }
    $level = (int) $level;
    $class = $level >= 2 ? 'alert' : '';
    return '<td class="' . $class . '">' . e(WINDOWING_DROUGHT_LABELS[$level] ?? (string) $level) . '</td>';
}

$varietyInput = is_string($_GET['variety'] ?? null) ? $_GET['variety'] : '';
$marketInput = is_string($_GET['market'] ?? null) ? trim($_GET['market']) : '서울';
$gradeInput = is_string($_GET['grade'] ?? null) ? trim($_GET['grade']) : '상품';
$fromYearInput = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
$orderInput = is_string($_GET['order'] ?? null) ? $_GET['order'] : 'new';

$grade = in_array($gradeInput, WINDOWING_GRADES, true) ? $gradeInput : '상품';
$order = in_array($orderInput, ['new', 'old'], true) ? $orderInput : 'new';
$sortDirection = $order === 'old' ? 'ASC' : 'DESC';

$varieties = [];
$markets = [];
$years = [];
$availableGrades = [];
$variety = null;
$market = '';
$fromYear = null;
$rows = [];
$error = false;

try {
    $pdo = db();
    $varieties = $pdo->query(
        'SELECT v.variety_id, v.variety_name, v.unit, v.area_id, f.fruit_name,
                a.area_name, a.station_type, a.station_id
         FROM FRUIT_VARIETY v
         JOIN FRUIT f ON f.fruit_id = v.fruit_id
         JOIN PRODUCTION_AREA a ON a.area_id = v.area_id
         ORDER BY f.kamis_item_code, v.variety_id'
    )->fetchAll();
    $markets = $pdo->query(
        'SELECT market_name FROM MARKET_REGION ORDER BY kamis_region_code'
    )->fetchAll(PDO::FETCH_COLUMN);
    $years = array_map('intval', $pdo->query(
        'SELECT DISTINCT year FROM PRICE_MONTHLY ORDER BY year'
    )->fetchAll(PDO::FETCH_COLUMN));

    // 입력값은 DB에서 읽은 목록에 있는 값만 사용합니다.
    foreach ($varieties as $candidate) {
        if ((string) $candidate['variety_id'] === $varietyInput) {
            $variety = $candidate;
            break;
        }
    }
    $variety ??= $varieties[0] ?? null;
    $market = in_array($marketInput, $markets, true) ? $marketInput : '';
    if (preg_match('/^[0-9]{4}$/D', $fromYearInput) && in_array((int) $fromYearInput, $years, true)) {
        $fromYear = (int) $fromYearInput;
    }

    if ($variety !== null) {
        $seriesParams = [$variety['fruit_name'], $variety['variety_name'], $variety['unit']];
        $statement = $pdo->prepare(
            'SELECT DISTINCT grade FROM v_price_weather
             WHERE fruit_name = ? AND variety_name = ? AND unit = ?'
        );
        $statement->execute($seriesParams);
        $found = $statement->fetchAll(PDO::FETCH_COLUMN);
        $availableGrades = array_values(array_intersect(WINDOWING_GRADES, $found));

        $conditions = ['fruit_name = ?', 'variety_name = ?', 'unit = ?', 'grade = ?'];
        $params = array_merge($seriesParams, [$grade]);
        if ($market !== '') {
            $conditions[] = 'market_name = ?';
            $params[] = $market;
        }
        $params[] = (int) $variety['area_id'];
        $yearFilter = '';
        if ($fromYear !== null) {
            $yearFilter = 'WHERE w.year >= ?';
            $params[] = $fromYear;
        }

        // 없는 달이 많은 제철 과일도 정확히 계산하도록, 가격 창은 행 개수가 아니라
        // 월 번호(year*12+month)의 RANGE로 잡습니다. 기간 조건은 창 계산 뒤에 적용합니다.
        // 날씨는 132개월이 연속이므로 ROWS로 직전 3개월을 집계합니다. 가격이 없는 달의
        // 날씨는 v_price_weather에 없어서 WEATHER_MONTHLY에서 주산지 기준으로 읽습니다.
        $sql = "
            WITH price AS (
                SELECT ym, year, market_name, avg_price,
                       total_rain, heatwave_days, heavy_rain_days, drought_level,
                       year * 12 + month AS month_index
                FROM v_price_weather
                WHERE " . implode(' AND ', $conditions) . "
            ),
            windowed AS (
                SELECT price.*,
                    AVG(avg_price) OVER (
                        PARTITION BY market_name ORDER BY month_index
                        RANGE BETWEEN 2 PRECEDING AND CURRENT ROW
                    ) AS moving_avg,
                    COUNT(*) OVER (
                        PARTITION BY market_name ORDER BY month_index
                        RANGE BETWEEN 2 PRECEDING AND CURRENT ROW
                    ) AS moving_months,
                    MAX(avg_price) OVER (
                        PARTITION BY market_name ORDER BY month_index
                        RANGE BETWEEN 1 PRECEDING AND 1 PRECEDING
                    ) AS prev_month_price,
                    MAX(avg_price) OVER (
                        PARTITION BY market_name ORDER BY month_index
                        RANGE BETWEEN 12 PRECEDING AND 12 PRECEDING
                    ) AS prev_year_price
                FROM price
            ),
            area_weather AS (
                SELECT ym,
                    SUM(total_rain) OVER prev3 AS rain_prev3,
                    SUM(heatwave_days) OVER prev3 AS heatwave_prev3,
                    SUM(heavy_rain_days) OVER prev3 AS heavy_rain_prev3,
                    MAX(drought_level) OVER prev3 AS drought_prev3,
                    COUNT(*) OVER prev3 AS prev3_months
                FROM WEATHER_MONTHLY
                WHERE area_id = ?
                WINDOW prev3 AS (ORDER BY ym ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING)
            )
            SELECT w.ym, w.market_name, w.avg_price,
                   w.moving_avg, w.moving_months, w.prev_month_price, w.prev_year_price,
                   w.total_rain, w.heatwave_days, w.heavy_rain_days, w.drought_level,
                   a.rain_prev3, a.heatwave_prev3, a.heavy_rain_prev3, a.drought_prev3, a.prev3_months
            FROM windowed w
            LEFT JOIN area_weather a ON a.ym = w.ym
            {$yearFilter}
            ORDER BY w.ym {$sortDirection}, w.market_name
            LIMIT " . WINDOWING_MAX_ROWS;
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('windowing analysis failed: ' . $exception->getMessage());
    $error = true;
}
$pageTitle  = '가격 흐름 Windowing · Team01';
$pageName   = 'windowing';
$breadcrumb = [
    ['label' => 'Overview',  'url' => '../index.php'],
    ['label' => 'Analysis'],
    ['label' => 'Windowing'],
];
$depth = 1;
require_once dirname(__DIR__) . '/includes/layout_open.php';
?>
<link rel="stylesheet" href="../assets/css/ranking.css">
<link rel="stylesheet" href="../assets/css/windowing.css">
<div class="windowing-page">

    <h1>가격 흐름과 주산지 이상기후</h1>
    <p>한 품종의 월평균 가격을 3개월 이동평균, 전월·전년 동월 대비 상승률로 살펴보고, 그 품종 주산지의 당월과 직전 3개월 기상 지표를 함께 봅니다.</p>

    <section>
        <h2>조회 조건</h2>
        <form method="get" class="filter-grid">
            <div class="field">
                <label for="variety">품종</label>
                <select id="variety" name="variety">
                    <?php foreach ($varieties as $option): ?>
                        <option value="<?= e((string)$option['variety_id']) ?>" <?= $variety !== null && $variety['variety_id'] === $option['variety_id'] ? 'selected' : '' ?>><?= e($option['fruit_name']) ?> / <?= e($option['variety_name']) ?> (<?= e($option['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="grade">등급</label>
                <select id="grade" name="grade">
                    <?php foreach (WINDOWING_GRADES as $gradeOption): ?>
                        <option value="<?= e($gradeOption) ?>" <?= $grade === $gradeOption ? 'selected' : '' ?>><?= e($gradeOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="market">소비지역</label>
                <select id="market" name="market">
                    <option value="">전체 (지역별로 따로 계산)</option>
                    <?php foreach ($markets as $marketOption): ?>
                        <option value="<?= e((string)$marketOption) ?>" <?= $market === $marketOption ? 'selected' : '' ?>><?= e((string)$marketOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="from">시작 연도</label>
                <select id="from" name="from">
                    <option value="">전체 기간</option>
                    <?php foreach ($years as $yearOption): ?>
                        <option value="<?= e((string)$yearOption) ?>" <?= $fromYear === $yearOption ? 'selected' : '' ?>><?= e((string)$yearOption) ?>년부터</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="order">정렬</label>
                <select id="order" name="order">
                    <option value="new" <?= $order === 'new' ? 'selected' : '' ?>>최근 달부터</option>
                    <option value="old" <?= $order === 'old' ? 'selected' : '' ?>>오래된 달부터</option>
                </select>
            </div>
            <button type="submit">가격 흐름 조회</button>
        </form>
    </section>

    <section>
        <h2>분석 결과</h2>
        <?php if ($error): ?>
            <p class="errors" role="alert">가격 흐름 자료를 불러오지 못했습니다. DB와 공통 뷰 상태를 확인해주세요.</p>
        <?php elseif ($variety === null): ?>
            <p class="notice">품종 기준 데이터가 아직 없습니다. dbinsert.sql 적용 후 이용해주세요.</p>
        <?php else: ?>
            <p class="result-summary">
                <strong><?= e($variety['fruit_name']) ?> / <?= e($variety['variety_name']) ?></strong>
                · <?= e($variety['unit']) ?> · <?= e($grade) ?>
                · 소비지역 <?= e($market === '' ? '전체' : $market) ?>
                · 주산지 <strong><?= e($variety['area_name']) ?></strong>
                (<?= e($variety['station_type']) ?> <?= e((string)$variety['station_id']) ?>)
                · <?= e((string)count($rows)) ?>행
            </p>
            <?php if (!$rows): ?>
                <p class="notice">
                    조건에 맞는 가격 자료가 없습니다.
                    <?php if ($availableGrades && !in_array($grade, $availableGrades, true)): ?>
                        이 품종에 있는 등급: <?= e(implode(', ', $availableGrades)) ?>.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="table-scroll" tabindex="0" role="region" aria-label="가격 흐름과 주산지 기상 지표">
                    <table>
                        <thead>
                        <tr>
                            <th scope="col" rowspan="2">월</th>
                            <th scope="col" rowspan="2">소비지역</th>
                            <th scope="colgroup" colspan="4" class="group">가격</th>
                            <th scope="colgroup" colspan="4" class="group">주산지 당월</th>
                            <th scope="colgroup" colspan="4" class="group">주산지 직전 3개월</th>
                        </tr>
                        <tr>
                            <th scope="col" class="group">평균가격</th>
                            <th scope="col">3개월 이동평균</th>
                            <th scope="col">전월 대비</th>
                            <th scope="col">전년 동월 대비</th>
                            <th scope="col" class="group">강수량</th>
                            <th scope="col">폭염일</th>
                            <th scope="col">집중호우일</th>
                            <th scope="col">가뭄</th>
                            <th scope="col" class="group">강수량 합</th>
                            <th scope="col">폭염일 합</th>
                            <th scope="col">집중호우일 합</th>
                            <th scope="col">가뭄 최고</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $price = (int) $row['avg_price'];
                            $prevMonth = $row['prev_month_price'] === null ? null : (int) $row['prev_month_price'];
                            $prevYear = $row['prev_year_price'] === null ? null : (int) $row['prev_year_price'];
                            // 2015년 초처럼 직전 3개월 날씨가 다 없으면 합계를 표시하지 않습니다.
                            $hasPrev3 = (int) $row['prev3_months'] === 3;
                            ?>
                            <tr>
                                <td><?= e(substr((string)$row['ym'], 0, 7)) ?></td>
                                <td><?= e((string)$row['market_name']) ?></td>
                                <td class="number group"><?= e(number_format($price)) ?>원</td>
                                <td class="number">
                                    <?= e(number_format((float)$row['moving_avg'])) ?>원
                                    <?php if ((int)$row['moving_months'] < 3): ?>
                                        <span class="muted">(<?= e((string)$row['moving_months']) ?>개월)</span>
                                    <?php endif; ?>
                                </td>
                                <?= rateCell(changeRate($price, $prevMonth)) ?>
                                <?= rateCell(changeRate($price, $prevYear)) ?>
                                <?= numberCell($row['total_rain'], 1, 'mm', 'group') ?>
                                <?= numberCell($row['heatwave_days'], 0, '일') ?>
                                <?= numberCell($row['heavy_rain_days'], 0, '일') ?>
                                <?= droughtCell($row['drought_level']) ?>
                                <?= numberCell($hasPrev3 ? $row['rain_prev3'] : null, 1, 'mm', 'group') ?>
                                <?= numberCell($hasPrev3 ? $row['heatwave_prev3'] : null, 0, '일') ?>
                                <?= numberCell($hasPrev3 ? $row['heavy_rain_prev3'] : null, 0, '일') ?>
                                <?= droughtCell($hasPrev3 ? $row['drought_prev3'] : null) ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section>
        <h2>읽는 법</h2>
        <ul class="notes">
            <li><strong>3개월 이동평균</strong>: 해당 월과 앞의 두 달 가격의 평균입니다. 제철 과일처럼 앞 달 가격이 없으면 있는 달만 평균하고 개월 수를 괄호로 표시합니다.</li>
            <li><strong>전월 대비 · 전년 동월 대비</strong>: 바로 앞 달, 1년 전 같은 달 가격과의 변동률(%)입니다. 비교할 달의 가격이 없으면 비워 둡니다.</li>
            <li><strong>주산지 직전 3개월</strong>: 해당 월보다 1~3개월 앞선 기간의 주산지 기상입니다. 가뭄은 그 기간의 가장 높은 단계입니다.</li>
            <li>가뭄 단계는 SPI3 기준(없음·약함·보통·심함)이며 자료가 없는 달은 비어 있습니다. 폭염일·집중호우일은 일수입니다.</li>
            <li>가격과 기상 지표를 나란히 보여줄 뿐이며, 날씨가 가격 변동의 원인임을 뜻하지는 않습니다.</li>
        </ul>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_close.php'; ?>

<?php
// 작성자: 김예원 — 초기 진입 페이지. 최종 메인 화면과 공통 레이아웃은 이미소 담당.
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');

// ── 요약 데이터 ──
$summaryFruits   = [];
$summaryStations = [];
$statYearMin     = null;
$statYearMax     = null;
try {
    $pdo = db();
    $summaryFruits = $pdo->query(
        "SELECT fruit_name FROM FRUIT ORDER BY fruit_id"
    )->fetchAll(PDO::FETCH_COLUMN);
    $summaryStations = $pdo->query(
        "SELECT area_name FROM PRODUCTION_AREA ORDER BY area_id"
    )->fetchAll(PDO::FETCH_COLUMN);
    $yr = $pdo->query(
        "SELECT MIN(year) AS y_min, MAX(year) AS y_max FROM v_price_weather"
    )->fetch();
    if ($yr && $yr['y_min'] !== null) {
        $statYearMin = (int)$yr['y_min'];
        $statYearMax = (int)$yr['y_max'];
    }
} catch (PDOException $e) { /* DB 미준비 시 무시 */ }

// ── 차트 데이터: 연도별 과일 평균 가격 ──
$chartLabels = [];
$chartDatasets = [];
try {
    $pdo = db();
    $rows = $pdo->query("
        SELECT year, fruit_name, ROUND(AVG(avg_price)) AS avg_price
        FROM v_price_weather
        GROUP BY year, fruit_name
        ORDER BY year, fruit_name
    ")->fetchAll();

    $byFruit = [];
    $years = [];
    foreach ($rows as $row) {
        $yr = (int)$row['year'];
        $fn = $row['fruit_name'];
        if (!in_array($yr, $years, true)) $years[] = $yr;
        $byFruit[$fn][$yr] = (int)$row['avg_price'];
    }
    sort($years);
    $chartLabels = array_map('strval', $years);

    $palette = ['#285d42','#55715f','#9aada0','#7a5500','#842d27'];
    $i = 0;
    foreach ($byFruit as $fruit => $data) {
        $values = [];
        foreach ($years as $yr) {
            $values[] = isset($data[$yr]) ? $data[$yr] : 'null';
        }
        $color = $palette[$i % count($palette)];
        $chartDatasets[] = [
            'label'           => $fruit,
            'data'            => $values,
            'borderColor'     => $color,
            'backgroundColor' => $color . '22',
            'tension'         => 0.3,
            'fill'            => false,
        ];
        $i++;
    }
} catch (PDOException $e) { /* 차트 데이터 없을 시 무시 */ }

$pageTitle  = '이상기후와 과일 가격 · Team01';
$pageName   = 'overview';
$breadcrumb = [
    ['label' => 'Overview'],
];
$depth = 0;
require_once __DIR__ . '/includes/layout_open.php';
?>

<div class="page-header">
    <h1>이상기후와 과일 가격 분석</h1>
    <p class="subtitle">
        기상 데이터와 과일 가격을 한눈에 비교하고, 지역과 기간에 따른 가격 변화 및 이상기후의 연관성을 분석하는 데이터 플랫폼입니다.
    </p>
</div>

<?php if (!empty($summaryFruits) || $statYearMin !== null): ?>
<div class="summary-box">
    <?php if (!empty($summaryFruits)): ?>
    <div class="summary-row">
        <span class="summary-label">분석 과일</span>
        <span class="summary-value"><?= e(implode(' · ', $summaryFruits)) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($summaryStations)): ?>
    <div class="summary-row">
        <span class="summary-label">분석 관측소</span>
        <span class="summary-value"><?= e(implode(' · ', $summaryStations)) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($statYearMin !== null): ?>
    <div class="summary-row">
        <span class="summary-label">분석 연도</span>
        <span class="summary-value"><?= e((string)$statYearMin) ?>년 – <?= e((string)$statYearMax) ?>년</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($chartLabels)): ?>
<div class="chart-section">
    <h2>연도별 과일 평균 가격 추이</h2>
    <div class="chart-wrap">
        <canvas id="priceChart"></canvas>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    var datasets = <?php
        $out = '[';
        foreach ($chartDatasets as $idx => $ds) {
            $out .= ($idx ? ',' : '') . '{';
            $out .= '"label":' . json_encode($ds['label'], JSON_UNESCAPED_UNICODE) . ',';
            $out .= '"data":[' . implode(',', $ds['data']) . '],';
            $out .= '"borderColor":' . json_encode($ds['borderColor']) . ',';
            $out .= '"backgroundColor":' . json_encode($ds['backgroundColor']) . ',';
            $out .= '"tension":' . $ds['tension'] . ',';
            $out .= '"fill":false';
            $out .= '}';
        }
        $out .= ']';
        echo $out;
    ?>;
    new Chart(document.getElementById('priceChart'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: {
                    title: { display: true, text: '평균 가격 (원)' },
                    ticks: {
                        callback: function(v) {
                            return v.toLocaleString('ko-KR') + '원';
                        }
                    }
                }
            }
        }
    });
}());
</script>
<?php endif; ?>

<div class="feature-cards">
    <a href="analysis/grouping.php" class="feature-card">
        <p class="feature-card-title">다중 그룹핑</p>
        <p class="feature-card-sub">Grouping · 연도·과일·지역 조합별 집계</p>
    </a>
    <a href="analysis/rollup.php" class="feature-card">
        <p class="feature-card-title">계층적 소계 집계</p>
        <p class="feature-card-sub">Rollup / Drilldown · 과일·시간 계층별 가격 집계</p>
    </a>
    <a href="analysis/ranking.php" class="feature-card">
        <p class="feature-card-title">가격 순위 분석</p>
        <p class="feature-card-sub">Ranking · 소비지역별 월평균 가격 순위</p>
    </a>
    <a href="analysis/windowing.php" class="feature-card">
        <p class="feature-card-title">기간별 추이</p>
        <p class="feature-card-sub">Windowing · 전년 동월 대비 가격 변동률</p>
    </a>
</div>

<section class="data-section">
    <h2>가격 자료 관리</h2>
    <p class="data-section-desc">월별 과일 가격 데이터를 조회하고 관리하세요.</p>
    <div class="data-cards">
        <a href="crud/list.php" class="data-card">
            <p class="data-card-title">자료 검색</p>
            <p class="data-card-sub">Browse Records</p>
        </a>
        <a href="crud/price_insert.php" class="data-card">
            <p class="data-card-title">가격 등록</p>
            <p class="data-card-sub">Add New Price</p>
        </a>
        <a href="crud/price_edit_list.php" class="data-card">
            <p class="data-card-title">가격 수정</p>
            <p class="data-card-sub">Edit Prices</p>
        </a>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <p class="data-section-notice"><?= e((string)($_SESSION['login_id'] ?? '회원')) ?>님 로그인 중 · <a href="auth/logout.php">로그아웃</a></p>
    <?php else: ?>
        <p class="data-section-notice">가격 데이터 등록 및 수정은 로그인 후 이용할 수 있습니다. <a href="auth/login.php">로그인</a> · <a href="auth/register.php">회원가입</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/layout_close.php'; ?>

<?php
// 작성자: 김예원 — 임시 테이블로만 실행하는 Rollup 회귀 검사.
declare(strict_types=1);
require_once dirname(__DIR__) . '/team01/config/db.php';
require_once dirname(__DIR__) . '/team01/includes/rollup.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
foreach ([['start'=>'2025-13'], ['start'=>'2025-02','end'=>'2025-01'],
    ['unit'=>[]], ['fruit'=>'사과'], ['axis'=>'invalid'], ['year'=>'2025 OR 1=1']] as $invalid) {
    try { rollupInput($invalid); throw new RuntimeException('Invalid input accepted'); }
    catch (InvalidArgumentException $expected) {}
}
$state = rollupInput(['unit'=>'1kg','grade'=>'상품']);
[$sql, $params] = rollupQuery(array_replace($state, ['market'=>"' OR 1=1 --"]));
check(!str_contains($sql, "' OR 1=1 --") && in_array("' OR 1=1 --", $params, true), 'Parameter binding');
parse_str((string) parse_url(rollupUrl($state, ['category'=>'인과류']), PHP_URL_QUERY), $decoded);
check($decoded['category'] === '인과류' && $decoded['unit'] === '1kg', 'URL round trip');
echo "Input and URL checks: OK\n";
if (!in_array('--db', $argv, true)) { exit; }
$pdo = db();
// 같은 이름의 영구 뷰가 있어도 이 연결에서만 임시 테이블로 가립니다.
$pdo->exec('CREATE TEMPORARY TABLE v_price_weather (
    ym DATE, category_name VARCHAR(30), fruit_name VARCHAR(30), variety_name VARCHAR(100),
    unit VARCHAR(30), grade VARCHAR(30), market_name VARCHAR(30),
    avg_price INT, min_price INT, max_price INT)');
try {
    $insert = $pdo->prepare('INSERT INTO v_price_weather VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ([
        ['2024-01-01','인과류','사과','후지','1kg','상품','서울',100,80,120],
        ['2024-02-01','인과류','사과','후지','1kg','상품','서울',200,180,220],
        ['2025-01-01','인과류','배','신고','1kg','상품','서울',600,580,620],
        ['2025-01-01','인과류','배','신고','10개','상품','서울',9000,8000,10000],
        ['2025-01-01','인과류','배','신고','1kg','중품','서울',999,900,1100],
    ] as $row) { $insert->execute($row); }
    $query = function (array $changes = []) use ($pdo, $state): array {
        [$sql, $params] = rollupQuery(array_replace($state, $changes));
        $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    };
    $rows = $query();
    check(count($rows) === 2 && $rows[1]['name'] === null && (float)$rows[1]['mean_price'] === 300.0, 'Weighted total and unit/grade isolation');
    $rows = $query(['category'=>'인과류']);
    check(count($rows) === 3 && (float)$rows[2]['mean_price'] === 300.0, 'Fruit drilldown total');
    $rows = $query(['category'=>'인과류','fruit'=>'사과']);
    check(count($rows) === 2 && $rows[0]['name'] === '후지' && (float)$rows[0]['mean_price'] === 150.0, 'Variety drilldown');
    $rows = $query(['axis'=>'time']);
    check(count($rows) === 3 && $rows[0]['name'] === '2024', 'Year grouping');
    $rows = $query(['axis'=>'time','year'=>'2024']);
    check(count($rows) === 3 && $rows[0]['name'] === '2024-01' && (float)$rows[2]['mean_price'] === 150.0, 'Month drilldown');
    $rows = $query(['start'=>'2024-02','end'=>'2024-02']);
    check((float)$rows[count($rows)-1]['mean_price'] === 200.0, 'Inclusive date boundaries');
    $rows = $query(['market'=>'없는 도시']);
    check(array_sum(array_column($rows, 'row_count')) === 0, 'Empty result');
    echo "MariaDB aggregate checks: OK\n";
} finally {
    $pdo->exec('DROP TEMPORARY TABLE v_price_weather');
}

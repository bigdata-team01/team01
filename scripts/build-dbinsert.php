<?php
// 작성자: 박시원 — 정제 CSV 8종을 검증하고 sql/dbinsert.sql을 생성하는 CLI 도구.
// 사용: php scripts/build-dbinsert.php <CSV 폴더> [출력 파일]
// DB에 접속하지 않으며 CSV 원본을 변경하지 않습니다.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// ── 기준 데이터 ─────────────────────────────────────────────────────────────
// ID는 이 파일에서 고정합니다. PRICE_MONTHLY 등이 같은 번호를 참조합니다.

const CATEGORIES = [1 => '인과류', 2 => '핵과류', 3 => '장과류', 4 => '감귤류'];

// kamis_item_code => [fruit_id, category_id, fruit_name]
const FRUITS = [
    '411' => [1, 1, '사과'],
    '412' => [2, 1, '배'],
    '413' => [3, 2, '복숭아'],
    '414' => [4, 3, '포도'],
    '415' => [5, 4, '감귤'],
];

// ASOS 지점번호 => 주산지명. area_id는 장마 정제본과 같게 지점번호를 사용합니다.
const AREAS = [
    135 => '영동',
    136 => '안동',
    189 => '서귀포',
    203 => '이천(장호원)',
    232 => '천안(입장면)',
    248 => '장수',
    272 => '영주',
    281 => '영천',
];

// "품목코드-품종코드" => [variety_id, variety_name, unit, area_id]
const VARIETIES = [
    '411-5'  => [1, '후지', '10개', 136],
    '411-6'  => [2, '쓰가루', '10개', 272],
    '411-7'  => [3, '홍로', '10개', 248],
    '412-1'  => [4, '신고', '10개', 232],
    '412-4'  => [5, '원황', '10개', 232],
    '413-1'  => [6, '백도', '10개', 203],
    '414-1'  => [7, '캠벨얼리', '1kg', 135],
    '414-2'  => [8, '거봉', '2kg', 232],
    '414-12' => [9, '샤인머스켓', '2kg', 281],
    '415-1'  => [10, '노지', '10개', 189],
    '415-2'  => [11, '시설', '10개', 189],
];

// market_name => [market_id, kamis_region_code]
const MARKETS = [
    '서울' => [1, '1101'],
    '부산' => [2, '2100'],
    '대구' => [3, '2200'],
    '광주' => [4, '2401'],
    '대전' => [5, '2501'],
];

const GRADES = ['상품', '중품', 'S과', 'M과', 'L과'];
const BATCH_SIZE = 500;

// ── 도우미 ───────────────────────────────────────────────────────────────────

function fail(string $message): never
{
    fwrite(STDERR, 'build-dbinsert: ' . $message . PHP_EOL);
    exit(1);
}

/** @return list<array<string,string>> */
function readCsv(string $dir, string $name, array $requiredColumns): array
{
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        fail("파일을 열 수 없습니다: $path");
    }
    $header = fgetcsv($handle, null, ',', '"', '');
    if ($header === false || $header === [null]) {
        fail("$name: 헤더가 없습니다.");
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    $missing = array_diff($requiredColumns, $header);
    if ($missing) {
        fail("$name: 필요한 컬럼이 없습니다: " . implode(', ', $missing));
    }
    $rows = [];
    $line = 1;
    while (($fields = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $line++;
        if ($fields === [null]) {
            continue;
        }
        if (count($fields) !== count($header)) {
            fail("$name:$line 컬럼 수가 헤더와 다릅니다.");
        }
        $rows[] = array_map('trim', array_combine($header, $fields));
    }
    fclose($handle);
    return $rows;
}

function intValue(string $value, string $where, int $min = 0, int $max = PHP_INT_MAX): int
{
    if (!preg_match('/^[0-9]+$/D', $value) || (int) $value < $min || (int) $value > $max) {
        fail("$where: 정수 범위($min~$max)를 벗어났습니다: '$value'");
    }
    return (int) $value;
}

function decimalValue(string $value, string $where): string
{
    if (!preg_match('/^-?[0-9]+(\.[0-9]+)?$/D', $value)) {
        fail("$where: 숫자가 아닙니다: '$value'");
    }
    return $value;
}

/** 'YYYY-MM-DD' 형식과 실제 날짜 여부를 확인하고 [연, 월, 일]을 반환합니다. */
function dateParts(string $value, string $where): array
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m)
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        fail("$where: 날짜가 올바르지 않습니다: '$value'");
    }
    return [(int) $m[1], (int) $m[2], (int) $m[3]];
}

/** 매월 1일이고 year·month 컬럼과 일치하는지 확인합니다. */
function checkMonthRow(array $row, string $where): void
{
    [$y, $m, $d] = dateParts($row['ym'], $where);
    if ($d !== 1 || (int) $row['year'] !== $y || (int) $row['month'] !== $m) {
        fail("$where: ym/year/month가 서로 맞지 않습니다: {$row['ym']}");
    }
}

function sqlString(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
}

/** @param list<list<string>> $tuples 이미 SQL 리터럴로 변환된 값 */
function insertStatements(string $table, array $columns, array $tuples): string
{
    $sql = '';
    foreach (array_chunk($tuples, BATCH_SIZE) as $chunk) {
        $sql .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES\n";
        $sql .= implode(",\n", array_map(static fn(array $t): string => '(' . implode(', ', $t) . ')', $chunk));
        $sql .= ";\n";
    }
    return $sql . "\n";
}

// ── 입력 ─────────────────────────────────────────────────────────────────────

$csvDir = $argv[1] ?? '';
if ($csvDir === '' || !is_dir($csvDir)) {
    fail('사용: php scripts/build-dbinsert.php <CSV 폴더> [출력 파일]');
}
$outPath = $argv[2] ?? dirname(__DIR__) . '/sql/dbinsert.sql';

// ── PRICE_MONTHLY ────────────────────────────────────────────────────────────

$priceTuples = [];
$priceKeys = [];
foreach (readCsv($csvDir, 'price_monthly_clean.csv', [
    'kamis_item_code', 'kamis_kind_code', 'fruit_name', 'variety_name', 'market_name',
    'ym', 'year', 'month', 'grade', 'unit', 'avg_price', 'max_price', 'min_price',
]) as $i => $row) {
    $where = 'price_monthly_clean.csv:' . ($i + 2);
    $varietyKey = $row['kamis_item_code'] . '-' . $row['kamis_kind_code'];
    $variety = VARIETIES[$varietyKey] ?? fail("$where: 기준표에 없는 품종 코드 $varietyKey");
    $fruit = FRUITS[$row['kamis_item_code']];
    // 확인용 컬럼이 기준표와 다르면 매핑 오류이므로 중단합니다.
    if ($row['fruit_name'] !== $fruit[2] || $row['variety_name'] !== $variety[1] || $row['unit'] !== $variety[2]) {
        fail("$where: 품목·품종·단위가 기준표와 다릅니다.");
    }
    $market = MARKETS[$row['market_name']] ?? fail("$where: 기준표에 없는 도시 '{$row['market_name']}'");
    if (!in_array($row['grade'], GRADES, true)) {
        fail("$where: 허용되지 않는 등급 '{$row['grade']}'");
    }
    checkMonthRow($row, $where);
    $avg = intValue($row['avg_price'], $where, 0, 4294967295);
    $max = intValue($row['max_price'], $where, 0, 4294967295);
    $min = intValue($row['min_price'], $where, 0, 4294967295);
    if (!($min <= $avg && $avg <= $max)) {
        fail("$where: 최저 ≤ 평균 ≤ 최고 조건을 어겼습니다.");
    }
    $key = "$variety[0]|$market[0]|{$row['grade']}|{$row['ym']}";
    if (isset($priceKeys[$key])) {
        fail("$where: 품종·도시·등급·월이 중복됩니다.");
    }
    $priceKeys[$key] = true;
    $priceTuples[] = [
        (string) $variety[0], (string) $market[0], sqlString($row['ym']),
        (string) (int) $row['year'], (string) (int) $row['month'], sqlString($row['grade']),
        (string) $avg, (string) $max, (string) $min,
    ];
}

// ── WEATHER_MONTHLY (기온 + 이상기후 4종 병합) ───────────────────────────────

$monthlyColumns = ['station_type', 'station_id', 'ym', 'year', 'month'];

/** @return array<string,array<string,string>> "지점|ym" => 행 */
function monthlyByKey(string $dir, string $name, array $valueColumns): array
{
    global $monthlyColumns;
    $byKey = [];
    foreach (readCsv($dir, $name, array_merge($monthlyColumns, $valueColumns)) as $i => $row) {
        $where = $name . ':' . ($i + 2);
        if ($row['station_type'] !== 'ASOS' || !isset(AREAS[(int) $row['station_id']])) {
            fail("$where: 기준표에 없는 관측소 {$row['station_type']} {$row['station_id']}");
        }
        checkMonthRow($row, $where);
        $key = $row['station_id'] . '|' . $row['ym'];
        if (isset($byKey[$key])) {
            fail("$where: 관측소·월이 중복됩니다.");
        }
        $byKey[$key] = $row;
    }
    return $byKey;
}

$temperature = monthlyByKey($csvDir, 'weather_monthly_clean.csv', ['avg_temp', 'max_temp', 'min_temp']);
$dayCounts = [
    'heatwave_days' => monthlyByKey($csvDir, 'heatwave_monthly_clean.csv', ['heatwave_days']),
    'frost_days' => monthlyByKey($csvDir, 'frost_days_monthly_clean.csv', ['frost_days']),
    'heavy_rain_days' => monthlyByKey($csvDir, 'heavy_rain_days_monthly_clean.csv', ['heavy_rain_days']),
    'rainy_season_days' => monthlyByKey(
        $csvDir,
        'rainy_season_monthly_rain_days_classified.csv',
        ['rainy_season_days']
    ),
];
foreach ($dayCounts as $column => $rows) {
    if (array_diff_key($temperature, $rows) || array_diff_key($rows, $temperature)) {
        fail("$column 자료의 관측소·월 구성이 기온 자료와 다릅니다.");
    }
}

ksort($temperature);
$weatherTuples = [];
foreach ($temperature as $key => $row) {
    $where = "weather_monthly_clean.csv [$key]";
    $avg = decimalValue($row['avg_temp'], $where);
    $max = decimalValue($row['max_temp'], $where);
    $min = decimalValue($row['min_temp'], $where);
    if (!((float) $min <= (float) $avg && (float) $avg <= (float) $max)) {
        fail("$where: 최저 ≤ 평균 ≤ 최고 기온 조건을 어겼습니다.");
    }
    [$y, $m] = dateParts($row['ym'], $where);
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
    $tuple = [
        (string) (int) $row['station_id'], sqlString($row['ym']), (string) $y, (string) $m,
        $avg, $max, $min,
        'NULL', // total_rain: 월 합계강수량 정제본이 없습니다.
    ];
    foreach (['heatwave_days', 'frost_days', 'heavy_rain_days'] as $column) {
        $tuple[] = (string) intValue($dayCounts[$column][$key][$column], "$column [$key]", 0, $daysInMonth);
    }
    $tuple[] = 'NULL'; // max_dry_streak: 정제본은 SPI3 가뭄 단계라 이 컬럼과 의미가 다릅니다.
    $tuple[] = (string) intValue(
        $dayCounts['rainy_season_days'][$key]['rainy_season_days'],
        "rainy_season_days [$key]",
        0,
        $daysInMonth
    );
    $weatherTuples[] = $tuple;
}

// ── RAINY_SEASON ─────────────────────────────────────────────────────────────

$rainyTuples = [];
$rainyKeys = [];
foreach (readCsv($csvDir, 'rainy_season_cleaned_data.csv', [
    'rainy_id', 'area_id', 'year', 'start_date', 'end_date', 'season_length_days', 'rain_days', 'total_rain',
]) as $i => $row) {
    $where = 'rainy_season_cleaned_data.csv:' . ($i + 2);
    $areaId = intValue($row['area_id'], $where);
    if (!isset(AREAS[$areaId])) {
        fail("$where: 기준표에 없는 주산지 $areaId");
    }
    [$startYear] = dateParts($row['start_date'], $where);
    [$endYear] = dateParts($row['end_date'], $where);
    $year = intValue($row['year'], $where);
    $length = intValue($row['season_length_days'], $where, 1, 255);
    $days = (int) (new DateTimeImmutable($row['start_date']))->diff(new DateTimeImmutable($row['end_date']))->format('%r%a') + 1;
    if ($startYear !== $year || $endYear !== $year || $days !== $length) {
        fail("$where: 연도 또는 장마일수가 시작·종료일과 맞지 않습니다.");
    }
    $rainDays = intValue($row['rain_days'], $where, 0, $length);
    $totalRain = decimalValue($row['total_rain'], $where);
    if ((float) $totalRain < 0) {
        fail("$where: 합계강수량이 음수입니다.");
    }
    if (isset($rainyKeys["$areaId|$year"])) {
        fail("$where: 주산지·연도가 중복됩니다.");
    }
    $rainyKeys["$areaId|$year"] = true;
    $rainyTuples[] = [
        (string) intValue($row['rainy_id'], $where, 1), (string) $areaId, (string) $year,
        sqlString($row['start_date']), sqlString($row['end_date']),
        (string) $length, (string) $rainDays, $totalRain,
    ];
}

// ── SQL 조립 ─────────────────────────────────────────────────────────────────

$sql = <<<SQL
-- 작성자: 박시원 — 정제 데이터 통합 초기 입력. scripts/build-dbinsert.php가 생성한 파일입니다.
-- 직접 고치지 말고 생성 스크립트를 수정한 뒤 다시 생성하세요.
-- dbcreate.sql을 적용한 빈 테이블에 한 번만 실행합니다. DB명/계정은 이 파일에 고정하지 않습니다.
-- 예: mysql -u 사용자 -p team01 < dbinsert.sql
-- 다시 넣으려면 dbdrop.sql → dbcreate.sql → dbinsert.sql 순서로 실행하세요.
-- WEATHER_MONTHLY.total_rain, max_dry_streak는 해당 정제본이 없어 NULL입니다.
SET NAMES utf8mb4;
START TRANSACTION;


SQL;

$tuples = [];
foreach (CATEGORIES as $id => $name) {
    $tuples[] = [(string) $id, sqlString($name)];
}
$sql .= insertStatements('FRUIT_CATEGORY', ['category_id', 'category_name'], $tuples);

$tuples = [];
foreach (AREAS as $stationId => $name) {
    $tuples[] = [(string) $stationId, sqlString($name), (string) $stationId, sqlString('ASOS')];
}
$sql .= "-- area_id는 ASOS 지점번호와 같은 값을 사용합니다.\n";
$sql .= insertStatements('PRODUCTION_AREA', ['area_id', 'area_name', 'station_id', 'station_type'], $tuples);

$tuples = [];
foreach (MARKETS as $name => [$id, $code]) {
    $tuples[] = [(string) $id, sqlString($name), sqlString($code)];
}
$sql .= insertStatements('MARKET_REGION', ['market_id', 'market_name', 'kamis_region_code'], $tuples);

$tuples = [];
foreach (FRUITS as $itemCode => [$id, $categoryId, $name]) {
    $tuples[] = [(string) $id, (string) $categoryId, sqlString($name), sqlString((string) $itemCode)];
}
$sql .= insertStatements('FRUIT', ['fruit_id', 'category_id', 'fruit_name', 'kamis_item_code'], $tuples);

$tuples = [];
foreach (VARIETIES as $key => [$id, $name, $unit, $areaId]) {
    [$itemCode, $kindCode] = explode('-', (string) $key);
    $tuples[] = [
        (string) $id, (string) FRUITS[$itemCode][0], (string) $areaId,
        sqlString($name), sqlString($kindCode), sqlString($unit),
    ];
}
$sql .= insertStatements(
    'FRUIT_VARIETY',
    ['variety_id', 'fruit_id', 'area_id', 'variety_name', 'kamis_kind_code', 'unit'],
    $tuples
);

$sql .= insertStatements(
    'WEATHER_MONTHLY',
    ['area_id', 'ym', 'year', 'month', 'avg_temp', 'max_temp', 'min_temp', 'total_rain',
        'heatwave_days', 'frost_days', 'heavy_rain_days', 'max_dry_streak', 'rainy_season_days'],
    $weatherTuples
);

$sql .= insertStatements(
    'RAINY_SEASON',
    ['rainy_id', 'area_id', 'year', 'start_date', 'end_date', 'season_length_days', 'rain_days', 'total_rain'],
    $rainyTuples
);

$sql .= insertStatements(
    'PRICE_MONTHLY',
    ['variety_id', 'market_id', 'ym', 'year', 'month', 'grade', 'avg_price', 'max_price', 'min_price'],
    $priceTuples
);

$sql .= "COMMIT;\n";

if (file_put_contents($outPath, $sql) === false) {
    fail("출력 파일을 쓸 수 없습니다: $outPath");
}

printf(
    "생성 완료: %s\n  FRUIT_CATEGORY %d, PRODUCTION_AREA %d, MARKET_REGION %d, FRUIT %d, FRUIT_VARIETY %d\n"
    . "  WEATHER_MONTHLY %d, RAINY_SEASON %d, PRICE_MONTHLY %d\n",
    $outPath,
    count(CATEGORIES),
    count(AREAS),
    count(MARKETS),
    count(FRUITS),
    count(VARIETIES),
    count($weatherTuples),
    count($rainyTuples),
    count($priceTuples)
);

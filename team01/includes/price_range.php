<?php
// 가격 분석·검색 화면에서 사용하는 실제 데이터 월 범위 조회.
declare(strict_types=1);

/**
 * @return array{min:string,max:string}|null
 */
function priceMonthRange(PDO $pdo, array $filters = []): ?array
{
    $definitions = [
        'fruit_id' => ['f.fruit_id', '=', PDO::PARAM_INT],
        'variety_id' => ['v.variety_id', '=', PDO::PARAM_INT],
        'market_id' => ['p.market_id', '=', PDO::PARAM_INT],
        'category_name' => ['c.category_name', '=', PDO::PARAM_STR],
        'fruit_name' => ['f.fruit_name', '=', PDO::PARAM_STR],
        'variety_name' => ['v.variety_name', '=', PDO::PARAM_STR],
        'unit' => ['v.unit', '=', PDO::PARAM_STR],
        'grade' => ['p.grade', '=', PDO::PARAM_STR],
        'market_name' => ['m.market_name', '=', PDO::PARAM_STR],
        'min_price' => ['p.avg_price', '>=', PDO::PARAM_INT],
        'max_price' => ['p.avg_price', '<=', PDO::PARAM_INT],
    ];
    $where = [];
    $params = [];

    foreach ($filters as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if (!isset($definitions[$key])) {
            throw new InvalidArgumentException('지원하지 않는 가격 기간 필터입니다.');
        }
        [$column, $operator, $type] = $definitions[$key];
        $where[] = "$column $operator ?";
        $params[] = [$value, $type];
    }

    $sql = "
        SELECT DATE_FORMAT(MIN(p.ym), '%Y-%m') AS min_month,
               DATE_FORMAT(MAX(p.ym), '%Y-%m') AS max_month
        FROM PRICE_MONTHLY p
        JOIN FRUIT_VARIETY v ON v.variety_id = p.variety_id
        JOIN FRUIT f ON f.fruit_id = v.fruit_id
        JOIN FRUIT_CATEGORY c ON c.category_id = f.category_id
        JOIN MARKET_REGION m ON m.market_id = p.market_id
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $statement = $pdo->prepare($sql);
    foreach ($params as $index => [$value, $type]) {
        $statement->bindValue($index + 1, $value, $type);
    }
    $statement->execute();
    $row = $statement->fetch();
    if (!$row || $row['min_month'] === null || $row['max_month'] === null) {
        return null;
    }

    return ['min' => (string) $row['min_month'], 'max' => (string) $row['max_month']];
}

function monthInPriceRange(string $month, ?array $range): bool
{
    return $range !== null
        && preg_match('/^[0-9]{4}-(?:0[1-9]|1[0-2])$/D', $month) === 1
        && $month >= $range['min']
        && $month <= $range['max'];
}

function priceRangeMessage(?array $range, bool $filtered): string
{
    if ($range === null) {
        return $filtered
            ? '현재 선택 조건에 해당하는 가격 데이터가 없습니다.'
            : '현재 검색 가능한 가격 데이터가 없습니다.';
    }

    $scope = $filtered ? '현재 선택 조건의 검색 가능 범위' : '현재 검색 가능한 가격 데이터의 범위';
    return $scope . '는 ' . $range['min'] . ' ~ ' . $range['max'] . '입니다.';
}

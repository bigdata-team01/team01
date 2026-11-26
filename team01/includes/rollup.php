<?php
// 작성자: 김예원 — 가격 계층 탐색과 집계 쿼리.
declare(strict_types=1);

function rollupInput(array $input): array
{
    $state = [];
    foreach (['start', 'end', 'unit', 'grade', 'market', 'category', 'fruit', 'axis', 'year'] as $key) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > 100) {
            throw new InvalidArgumentException('조회 조건의 형식이 올바르지 않습니다.');
        }
        $state[$key] = trim($value);
    }
    $state['axis'] = $state['axis'] === '' ? 'fruit' : $state['axis'];
    if (!in_array($state['axis'], ['fruit', 'time'], true)
        || ($state['year'] !== '' && !isDataMonth($state['year'] . '-01'))) {
        throw new InvalidArgumentException('분석 기준 또는 연도가 올바르지 않습니다.');
    }
    foreach (['start', 'end'] as $key) {
        if ($state[$key] !== '' && !isDataMonth($state[$key])) {
            throw new InvalidArgumentException('기간은 2015-01부터 2025-12 사이로 입력하세요.');
        }
    }
    if ($state['start'] !== '' && $state['end'] !== '' && $state['start'] > $state['end']) {
        throw new InvalidArgumentException('시작 월은 종료 월보다 늦을 수 없습니다.');
    }
    if ($state['fruit'] !== '' && $state['category'] === '') {
        throw new InvalidArgumentException('품목을 조회하려면 과일군을 먼저 선택하세요.');
    }
    return $state;
}

function rollupQuery(array $state): array
{
    if ($state['unit'] === '' || $state['grade'] === '') {
        throw new InvalidArgumentException('거래 단위와 등급을 선택하세요.');
    }
    $column = $state['fruit'] !== '' ? 'variety_name'
        : ($state['category'] !== '' ? 'fruit_name' : 'category_name');
    if ($state['axis'] === 'time') {
        $column = $state['year'] === '' ? "DATE_FORMAT(ym, '%Y')" : "DATE_FORMAT(ym, '%Y-%m')";
    }
    $where = [];
    $params = [];
    foreach (['unit' => 'unit', 'grade' => 'grade', 'market' => 'market_name',
        'category' => 'category_name', 'fruit' => 'fruit_name'] as $key => $field) {
        if ($state[$key] !== '') {
            $where[] = "$field = ?";
            $params[] = $state[$key];
        }
    }
    if ($state['start'] !== '') {
        $where[] = 'ym >= ?';
        $params[] = $state['start'] . '-01';
    }
    if ($state['end'] !== '') {
        $where[] = 'ym <= ?';
        $params[] = $state['end'] . '-01';
    }
    if ($state['year'] !== '') {
        $where[] = 'ym BETWEEN ? AND ?';
        $params[] = $state['year'] . '-01-01';
        $params[] = $state['year'] . '-12-01';
    }
    // 빈 이름은 상세 그룹('')으로 유지하여 ROLLUP의 NULL 요약 행과 구별합니다.
    // ORDER BY는 WITH ROLLUP 바깥에서 적용합니다(MariaDB 호환).
    $sql = "SELECT * FROM (
        SELECT COALESCE($column, '') AS name, COUNT(*) AS row_count,
            COUNT(avg_price) AS price_count, AVG(avg_price) AS mean_price,
            MIN(min_price) AS lowest_price, MAX(max_price) AS highest_price
        FROM v_price_weather WHERE " . implode(' AND ', $where) . "
        GROUP BY COALESCE($column, '') WITH ROLLUP
    ) AS summary ORDER BY name IS NULL, name";
    return [$sql, $params];
}

function rollupUrl(array $state, array $changes = []): string
{
    return 'rollup.php?' . http_build_query(array_replace($state, $changes), '', '&', PHP_QUERY_RFC3986);
}

function rollupNumber($value): string
{
    return $value === null ? '—' : number_format((float) $value, 2);
}

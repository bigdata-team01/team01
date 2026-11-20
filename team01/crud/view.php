<?php
// 가격 자료 상세 보기 (작성: 유채은)
// 가격 한 행과, 그 품종 주산지의 같은 달 날씨를 함께 보여줍니다.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/session.php';
require_once __DIR__ . '/crud_layout.php';

$id = $_GET['id'] ?? '';
$backUrl = safe_list_url($_GET['return'] ?? null);
$row = null;
$error = '';

if (is_string($id) && ctype_digit($id)) {
    try {
        $statement = db()->prepare(
            'SELECT p.price_id, p.ym, p.grade, p.avg_price, p.min_price, p.max_price,
                    c.category_name, f.fruit_name, v.variety_name, v.unit, m.market_name,
                    a.area_name, a.station_type, a.station_id,
                    w.avg_temp, w.max_temp, w.min_temp, w.total_rain,
                    w.heatwave_days, w.frost_days, w.heavy_rain_days, w.max_dry_streak, w.rainy_season_days
             FROM PRICE_MONTHLY p
             JOIN FRUIT_VARIETY v ON v.variety_id = p.variety_id
             JOIN FRUIT f ON f.fruit_id = v.fruit_id
             JOIN FRUIT_CATEGORY c ON c.category_id = f.category_id
             JOIN MARKET_REGION m ON m.market_id = p.market_id
             JOIN PRODUCTION_AREA a ON a.area_id = v.area_id
             LEFT JOIN WEATHER_MONTHLY w ON w.area_id = a.area_id AND w.ym = p.ym
             WHERE p.price_id = ?'
        );
        $statement->bindValue(1, (int) $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch() ?: null;
    } catch (PDOException $exception) {
        $error = '자료를 불러오지 못했습니다.';
    }
}

if ($row === null && $error === '') {
    http_response_code(404);
    $error = '해당 자료를 찾을 수 없습니다. 이미 삭제되었을 수 있습니다.';
}

// 기상값이 없으면(NULL) 0과 구분해서 "자료 없음"으로 표시합니다.
function weather_value($value, string $suffix): string
{
    if ($value === null) {
        return '<span class="muted">자료 없음</span>';
    }

    $text = (string) $value;
    if (strpos($text, '.') !== false) {
        $text = rtrim(rtrim($text, '0'), '.'); // 12.50 → 12.5, 3.00 → 3
    }

    return e($text . $suffix);
}

crud_page_start('가격 자료 상세');
?>
        <p><a href="<?= e($backUrl) ?>">← 검색 결과로</a></p>

        <?php if ($error !== ''): ?>
            <p class="message error"><?= e($error) ?></p>
        <?php else: ?>
            <h1><?= e($row['fruit_name']) ?> · <?= e($row['variety_name']) ?></h1>
            <p class="subtitle">
                <?= e(substr($row['ym'], 0, 7)) ?> · <?= e($row['market_name']) ?> · <?= e($row['grade']) ?>
            </p>

            <div class="detail-grid">
                <section class="card">
                    <h2>가격 정보</h2>
                    <dl class="info">
                        <dt>과일군</dt><dd><?= e($row['category_name']) ?></dd>
                        <dt>품목 / 품종</dt><dd><?= e($row['fruit_name']) ?> / <?= e($row['variety_name']) ?></dd>
                        <dt>소비 도시</dt><dd><?= e($row['market_name']) ?></dd>
                        <dt>등급</dt><dd><span class="badge"><?= e($row['grade']) ?></span></dd>
                        <dt>거래 단위</dt><dd><?= e($row['unit']) ?></dd>
                        <dt>평균가</dt><dd><?= e(format_won($row['avg_price'])) ?></dd>
                        <dt>최저가</dt><dd><?= e(format_won($row['min_price'])) ?></dd>
                        <dt>최고가</dt><dd><?= e(format_won($row['max_price'])) ?></dd>
                    </dl>
                </section>

                <section class="card">
                    <h2>같은 달 주산지 날씨</h2>
                    <dl class="info">
                        <dt>주산지</dt>
                        <dd><?= e($row['area_name']) ?>
                            <span class="muted">(<?= e($row['station_type']) ?> <?= e($row['station_id']) ?>)</span></dd>
                        <dt>평균기온</dt><dd><?= weather_value($row['avg_temp'], '℃') ?></dd>
                        <dt>최고기온</dt><dd><?= weather_value($row['max_temp'], '℃') ?></dd>
                        <dt>최저기온</dt><dd><?= weather_value($row['min_temp'], '℃') ?></dd>
                        <dt>강수량</dt><dd><?= weather_value($row['total_rain'], 'mm') ?></dd>
                        <dt>폭염일수</dt><dd><?= weather_value($row['heatwave_days'], '일') ?></dd>
                        <dt>서리일수</dt><dd><?= weather_value($row['frost_days'], '일') ?></dd>
                        <dt>호우일수</dt><dd><?= weather_value($row['heavy_rain_days'], '일') ?></dd>
                        <dt>최장 무강수</dt><dd><?= weather_value($row['max_dry_streak'], '일') ?></dd>
                        <dt>장마일수</dt><dd><?= weather_value($row['rainy_season_days'], '일') ?></dd>
                    </dl>
                </section>
            </div>

            <?php if (is_logged_in()): ?>
                <form method="post" action="delete.php" class="actions">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="return" value="<?= e($backUrl) ?>">
                    <input type="hidden" name="ids[]" value="<?= e($row['price_id']) ?>">
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('이 자료를 삭제할까요? 되돌릴 수 없습니다.');">이 자료 삭제</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
<?php
crud_page_end();

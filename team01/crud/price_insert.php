<?php
// 작성자: 이혜령 — 월별 소매가격 등록. 채은님 로그인 세션과 연동.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/require_price_login.php';

$grades = ['상품','중품','S과','M과','L과'];
$values = array_fill_keys(['variety_id','market_id','ym','grade','avg_price','min_price','max_price'], '');
$errors = [];
$varieties = $markets = [];
$ready = false;
$success = $_SESSION['price_insert_success'] ?? null;
unset($_SESSION['price_insert_success']);
if (!isset($_SESSION['price_insert_csrf'])) {
    $_SESSION['price_insert_csrf'] = bin2hex(random_bytes(32));
}
try {
    $pdo = db();
    $varieties = $pdo->query('SELECT v.variety_id,v.variety_name,v.unit,f.fruit_name FROM FRUIT_VARIETY v JOIN FRUIT f ON f.fruit_id=v.fruit_id ORDER BY f.kamis_item_code,v.kamis_kind_code')->fetchAll();
    $markets = $pdo->query('SELECT market_id,market_name FROM MARKET_REGION ORDER BY kamis_region_code')->fetchAll();
    $ready = count($varieties) > 0 && count($markets) > 0;
} catch (PDOException $e) {
    error_log('price_insert lookup failed: ' . $e->getMessage());
    $errors[] = '등록 목록을 불러올 수 없습니다. 잠시 후 다시 시도해주세요.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['price_insert_csrf'], $token)) {
        http_response_code(403);
        $errors[] = '요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 등록해주세요.';
    }
    if (!$ready) { $errors[] = '품종과 도시 목록이 준비된 후 등록할 수 있습니다.'; }
    if (!in_array($values['variety_id'], array_map('strval',array_column($varieties,'variety_id')), true)) {
        $errors[] = '품종을 선택해주세요.';
    }
    if (!in_array($values['market_id'], array_map('strval',array_column($markets,'market_id')), true)) {
        $errors[] = '소비도시를 선택해주세요.';
    }
    if (!in_array($values['grade'], $grades, true)) { $errors[] = '등급을 선택해주세요.'; }
    if (!preg_match('/^(?:[1-9][0-9]{3})-(?:0[1-9]|1[0-2])$/D', $values['ym'])) {
        $errors[] = '기준 월을 올바르게 입력해주세요.';
    }
    $prices = [];
    foreach (['avg_price'=>'평균 가격','min_price'=>'최저 가격','max_price'=>'최고 가격'] as $key=>$label) {
        if (!preg_match('/^[0-9]{1,10}$/D', $values[$key]) || (float)$values[$key] > 4294967295) {
            $errors[] = $label . '은 0~4,294,967,295 사이의 정수로 입력해주세요.';
        } else { $prices[$key] = (int)$values[$key]; }
    }
    if (count($prices) === 3 && !($prices['min_price'] <= $prices['avg_price'] && $prices['avg_price'] <= $prices['max_price'])) {
        $errors[] = '최저 가격 ≤ 평균 가격 ≤ 최고 가격 순서로 입력해주세요.';
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO PRICE_MONTHLY (variety_id,market_id,ym,year,month,grade,avg_price,max_price,min_price) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([(int)$values['variety_id'],(int)$values['market_id'],$values['ym'].'-01',(int)substr($values['ym'],0,4),(int)substr($values['ym'],5,2),$values['grade'],$prices['avg_price'],$prices['max_price'],$prices['min_price']]);
            $priceId = (int)$pdo->lastInsertId();
            $pdo->commit();
            $_SESSION['price_insert_success'] = $values['ym'].' 가격을 등록했습니다. 등록 번호: '.$priceId;
            header('Location: price_insert.php', true, 303);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $errors[] = '같은 품종·소비도시·등급·월의 가격이 이미 있습니다. 기존 자료를 수정해주세요.';
            } else {
                error_log('price_insert save failed: ' . $e->getMessage());
                $errors[] = '등록하지 못했습니다. 선택 항목을 확인하고 다시 시도해주세요.';
            }
        }
    }
}
$pageTitle  = '월별 가격 등록 · Team01';
$pageName   = 'data-add';
$breadcrumb = [
    ['label' => 'Overview',         'url' => '../index.php'],
    ['label' => 'Data Management'],
    ['label' => 'Add New Price'],
];
$depth = 1;
require_once dirname(__DIR__) . '/includes/layout_open.php';
?>
<link rel="stylesheet" href="../assets/css/price-form.css">
<div>
    <h1>월별 가격 등록</h1>
    <p>품종과 소비도시를 선택하고 해당 월의 소매가격을 입력하세요.</p>
    <?php if ($success): ?><p class="notice" role="status"><?= e((string)$success) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="errors" role="alert"><strong>입력 내용을 확인해주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$ready && !$errors): ?><p class="notice">등록할 품종과 도시 목록이 아직 없습니다. 기준 데이터 등록 후 이용해주세요.</p><?php endif; ?>
    <form method="post" action="price_insert.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['price_insert_csrf']) ?>">
        <fieldset <?= !$ready ? 'disabled' : '' ?>>
            <legend>가격 정보</legend>
            <div class="form-grid">
                <div class="field"><label for="variety_id">품종 · 거래 단위</label>
                    <select id="variety_id" name="variety_id" required><option value="">품종 선택</option>
                    <?php foreach ($varieties as $v): ?><option value="<?= e((string)$v['variety_id']) ?>" <?= $values['variety_id']===(string)$v['variety_id']?'selected':'' ?>><?= e($v['fruit_name'].' / '.$v['variety_name'].' · '.$v['unit']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="market_id">소비도시</label><select id="market_id" name="market_id" required><option value="">도시 선택</option><?php foreach ($markets as $m): ?><option value="<?= e((string)$m['market_id']) ?>" <?= $values['market_id']===(string)$m['market_id']?'selected':'' ?>><?= e($m['market_name']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="ym">기준 월</label><input id="ym" name="ym" type="month" min="1000-01" max="9999-12" value="<?= e($values['ym']) ?>" required></div>
                <div class="field"><label for="grade">등급</label><select id="grade" name="grade" required><option value="">등급 선택</option><?php foreach ($grades as $grade): ?><option <?= $values['grade']===$grade?'selected':'' ?> value="<?= e($grade) ?>"><?= e($grade) ?></option><?php endforeach; ?></select></div>
            </div>
            <p class="hint" id="price-help">가격은 선택한 거래 단위 기준의 원 단위 정수로 입력하세요. 최저·최고는 그달 일평균 가격의 최저·최고입니다.</p>
            <div class="price-grid">
                <?php foreach (['min_price'=>'최저 가격','avg_price'=>'평균 가격','max_price'=>'최고 가격'] as $key=>$label): ?>
                <div class="field"><label for="<?= e($key) ?>"><?= e($label) ?> (원)</label><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="0" max="4294967295" step="1" value="<?= e($values[$key]) ?>" aria-describedby="price-help" required></div>
                <?php endforeach; ?>
            </div>
            <button type="submit">가격 등록</button>
        </fieldset>
    </form>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_close.php'; ?>

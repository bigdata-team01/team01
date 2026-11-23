<?php
// 작성자: 이혜령 — 월별 소매가격 수정. 채은님 로그인 세션과 연동.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/require_price_login.php';

$grades = ['상품','중품','S과','M과','L과'];
$values = array_fill_keys(['variety_id','market_id','ym','grade','avg_price','min_price','max_price'], '');
$errors = [];
$varieties = $markets = [];
$ready = false;
$record = null;
$idInput = $_GET['id'] ?? '';
$id = is_string($idInput) && preg_match('/^[1-9][0-9]{0,9}$/D', $idInput) ? (int)$idInput : 0;
// 수정 화면을 연 이후 다른 사람이 바꾼 값을 덮어쓰지 않도록 원본을 서명합니다.
function priceRevision(array $row): string
{
    return hash_hmac('sha256', json_encode($row, JSON_UNESCAPED_UNICODE), $_SESSION['price_update_csrf']);
}
$revision = '';
$success = $_SESSION['price_update_success'] ?? null;
unset($_SESSION['price_update_success']);
if (!isset($_SESSION['price_update_csrf'])) {
    $_SESSION['price_update_csrf'] = bin2hex(random_bytes(32));
}
try {
    $pdo = db();
    $varieties = $pdo->query('SELECT v.variety_id,v.variety_name,v.unit,f.fruit_name FROM FRUIT_VARIETY v JOIN FRUIT f ON f.fruit_id=v.fruit_id ORDER BY f.kamis_item_code,v.kamis_kind_code')->fetchAll();
    $markets = $pdo->query('SELECT market_id,market_name FROM MARKET_REGION ORDER BY kamis_region_code')->fetchAll();
    $stmt = $pdo->prepare('SELECT * FROM PRICE_MONTHLY WHERE price_id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        http_response_code(404);
        $errors[] = '수정할 가격 자료가 없습니다. 목록에서 다시 선택해주세요.';
    } else {
        foreach ($values as $key => $_) { $values[$key] = (string)$record[$key]; }
        $values['ym'] = substr($values['ym'], 0, 7);
        $revision = priceRevision($record);
        $ready = count($varieties) > 0 && count($markets) > 0;
    }
} catch (PDOException $e) {
    error_log('price_update lookup failed: ' . $e->getMessage());
    $errors[] = '수정 목록을 불러올 수 없습니다. 잠시 후 다시 시도해주세요.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $revision = is_string($_POST['revision'] ?? null) ? $_POST['revision'] : '';
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['price_update_csrf'], $token)) {
        http_response_code(403);
        $errors[] = '요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 수정해주세요.';
    }
    if (!$ready) { $errors[] = '품종과 도시 목록이 준비된 후 수정할 수 있습니다.'; }
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
            $lock = $pdo->prepare('SELECT * FROM PRICE_MONTHLY WHERE price_id = ? FOR UPDATE');
            $lock->execute([$id]);
            $current = $lock->fetch();
            if (!$current || !hash_equals(priceRevision($current), $revision)) {
                $pdo->rollBack();
                http_response_code(409);
                $errors[] = '다른 작업에서 이 자료가 수정되거나 삭제되었습니다. 목록에서 다시 열어 최신 내용을 확인해주세요.';
            } else {
                $stmt = $pdo->prepare('UPDATE PRICE_MONTHLY SET variety_id=?,market_id=?,ym=?,year=?,month=?,grade=?,avg_price=?,max_price=?,min_price=? WHERE price_id=?');
                $stmt->execute([(int)$values['variety_id'],(int)$values['market_id'],$values['ym'].'-01',(int)substr($values['ym'],0,4),(int)substr($values['ym'],5,2),$values['grade'],$prices['avg_price'],$prices['max_price'],$prices['min_price'],$id]);
                $pdo->commit();
                $_SESSION['price_update_success'] = $values['ym'].' 가격을 수정했습니다. 수정 번호: '.$id;
                header('Location: price_update.php?id='.$id, true, 303);
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $errors[] = '같은 품종·소비도시·등급·월의 가격이 이미 있습니다. 다른 자료와 겹치지 않도록 선택 항목을 확인해주세요.';
            } else {
                error_log('price_update save failed: ' . $e->getMessage());
                $errors[] = '수정하지 못했습니다. 선택 항목을 확인하고 다시 시도해주세요.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>월별 가격 수정 · Team01</title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/price-form.css">
</head>
<body>
<main>
    <p class="hint"><?= e((string)($_SESSION['login_id'] ?? '회원')) ?>님 로그인 중 · <a href="../auth/logout.php">로그아웃</a></p>
    <a href="price_edit_list.php">← 수정할 자료 목록</a>
    <h1>월별 가격 수정</h1>
    <p>기존 값을 확인하고 필요한 항목을 바꾼 뒤 저장하세요. 등록 번호: <?= e((string)$id) ?></p>
    <?php if ($success): ?><p class="notice" role="status"><?= e((string)$success) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="errors" role="alert"><strong>입력 내용을 확인해주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$ready && !$errors): ?><p class="notice">수정할 품종과 도시 목록이 아직 없습니다. 기준 데이터 등록 후 이용해주세요.</p><?php endif; ?>
    <form method="post" action="price_update.php?id=<?= e((string)$id) ?>">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['price_update_csrf']) ?>">
        <input type="hidden" name="revision" value="<?= e($revision) ?>">
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
            <button type="submit">변경 내용 저장</button>
        </fieldset>
    </form>
</main>
</body>
</html>

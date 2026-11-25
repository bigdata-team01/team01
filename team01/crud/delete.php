<?php
// 가격 자료 삭제(DELETE) 처리 (작성: 유채은)
// - 로그인한 사용자만, POST 요청으로만, CSRF 토큰이 맞을 때만 삭제합니다.
// - 여러 건은 하나의 트랜잭션으로 묶어 "전부 삭제 또는 전부 취소"로 처리합니다.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/session.php';
require_once __DIR__ . '/crud_layout.php';

const MAX_DELETE = 100;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: list.php');
    exit;
}

require_login();

$returnUrl = safe_list_url($_POST['return'] ?? null);

function finish(string $type, string $text, string $returnUrl): void
{
    set_flash($type, $text);
    header('Location: ' . $returnUrl);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    finish('error', '요청이 만료되었습니다. 페이지를 새로고침한 뒤 다시 시도하세요.', $returnUrl);
}

// 숫자 ID만 골라 중복 제거
$ids = [];
foreach ((array) ($_POST['ids'] ?? []) as $value) {
    if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
        $ids[(int) $value] = true;
    }
}
$ids = array_keys($ids);

if (!$ids) {
    finish('error', '삭제할 자료를 선택하세요.', $returnUrl);
}
if (count($ids) > MAX_DELETE) {
    finish('error', '한 번에 ' . MAX_DELETE . '건까지 삭제할 수 있습니다.', $returnUrl);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare("DELETE FROM PRICE_MONTHLY WHERE price_id IN ($placeholders)");
    foreach ($ids as $index => $id) {
        $statement->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $statement->execute();

    // 요청한 건수와 실제 삭제 건수가 다르면(이미 삭제된 행 등) 전부 되돌립니다.
    if ($statement->rowCount() !== count($ids)) {
        $pdo->rollBack();
        finish('error', '일부 자료가 이미 삭제되었거나 없어서 아무것도 삭제하지 않았습니다. 목록을 새로고침하세요.', $returnUrl);
    }

    $pdo->commit();
    finish('success', count($ids) . '건을 삭제했습니다.', $returnUrl);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    finish('error', '삭제 중 오류가 발생해 아무것도 삭제하지 않았습니다.', $returnUrl);
}

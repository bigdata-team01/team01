<?php
// 작성자: 김예원 — CLI 전용 연결 검사. 프로젝트 테이블을 생성하지 않습니다.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/team01/config/db.php';

try {
    $pdo = db();
    $info = $pdo->query('SELECT DATABASE() AS db_name, VERSION() AS version, @@port AS port, @@character_set_connection AS charset')->fetch();
    $statement = $pdo->prepare('SELECT ? AS message');
    $statement->execute(['한글 연결 확인']);
    if ($statement->fetchColumn() !== '한글 연결 확인') {
        throw new RuntimeException('UTF-8 round-trip failed.');
    }
    echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    echo 'PDO prepared statement / UTF-8: OK', PHP_EOL;
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $statement->execute([$info['db_name']]);
    echo 'Tables/views: ', $statement->fetchColumn(), PHP_EOL;
    echo 'Connection check only; schema readiness is not checked.', PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'DB connection check failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

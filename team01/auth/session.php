<?php
// 로그인 확인·CSRF 토큰 공통 함수 (작성: 유채은)
// 사용: require_once dirname(__DIR__) . '/auth/session.php';  (bootstrap.php 다음에)

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// 로그인하지 않았으면 로그인 페이지로 보냅니다. 한 단계 하위 폴더(crud/ 등)의 페이지에서 사용합니다.
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

// 폼마다 넣는 CSRF 토큰. 세션당 하나를 만들어 재사용합니다.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// 한 번만 보여줄 안내 메시지 (삭제 완료 등)
function set_flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

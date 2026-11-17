<?php
// 회원가입 (작성: 유채은)
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$error = '';
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId  = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($loginId === '' || $password === '') {
        $error = '아이디와 비밀번호를 입력하세요.';
    } elseif (mb_strlen($loginId) > 30) {
        $error = '아이디는 30자 이하로 입력하세요.';
    } elseif ($password !== $confirm) {
        $error = '비밀번호가 일치하지 않습니다.';
    } else {
        try {
            $check = db()->prepare('SELECT 1 FROM APP_USER WHERE login_id = ?');
            $check->execute([$loginId]);

            if ($check->fetch()) {
                $error = '이미 사용 중인 아이디입니다.';
            } else {
                $insert = db()->prepare(
                    'INSERT INTO APP_USER (login_id, password_hash) VALUES (?, ?)'
                );
                $insert->execute([$loginId, password_hash($password, PASSWORD_DEFAULT)]);

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $exception) {
            $error = '회원가입 중 오류가 발생했습니다. 잠시 후 다시 시도하세요.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>회원가입</title>
    <!-- 화면 스타일 (작성: 유채은) -->
    <style>
        * {
            box-sizing: border-box;
        }

        body.auth-page {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #fff7ea 0%, #ffeedb 50%, #fff9ef 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Malgun Gothic", "맑은 고딕", sans-serif;
            color: #2b2b2b;
        }

        .auth-card {
            width: 100%;
            max-width: 380px;
            padding: 40px 32px 32px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(160, 90, 20, 0.13);
        }

        .auth-brand {
            margin: 0 0 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #d9690b;
        }

        .auth-title {
            margin: 0 0 28px;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
        }

        .auth-field {
            margin-bottom: 18px;
        }

        .auth-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #555555;
        }

        .auth-field input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dddddd;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            background: #fafafa;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }

        .auth-field input:focus {
            outline: none;
            border-color: #e8770e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(232, 119, 14, 0.2);
        }

        .auth-button {
            width: 100%;
            margin-top: 8px;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: #e8770e;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s;
        }

        .auth-button:hover {
            background: #cc6406;
        }

        .auth-message {
            margin: 0 0 20px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-message.error {
            background: #fdecea;
            color: #b3261e;
        }

        .auth-message.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .auth-footer {
            margin: 24px 0 0;
            text-align: center;
            font-size: 14px;
            color: #777777;
        }

        .auth-footer a {
            margin-left: 4px;
            color: #e8770e;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .auth-loggedin {
            margin: 0 0 8px;
            text-align: center;
            font-size: 15px;
            line-height: 1.6;
        }
    </style>
</head>
<body class="auth-page">
    <main class="auth-card">
        <p class="auth-brand">이상기후와 과일 가격</p>
        <h1 class="auth-title">회원가입</h1>

        <?php if ($error !== ''): ?>
            <p class="auth-message error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <div class="auth-field">
                <label for="login_id">아이디</label>
                <input type="text" id="login_id" name="login_id" maxlength="30"
                       value="<?= e($loginId) ?>" placeholder="30자 이하" required autofocus>
            </div>
            <div class="auth-field">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="auth-field">
                <label for="password_confirm">비밀번호 확인</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="auth-button">가입하기</button>
        </form>

        <p class="auth-footer">이미 계정이 있나요?<a href="login.php">로그인</a></p>
    </main>
</body>
</html>
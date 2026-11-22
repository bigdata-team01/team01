<?php
// 로그인 (작성: 유채은)
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$error = '';
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId  = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginId === '' || $password === '') {
        $error = '아이디와 비밀번호를 입력하세요.';
    } else {
        try {
            $statement = db()->prepare(
                'SELECT user_id, login_id, password_hash FROM APP_USER WHERE login_id = ?'
            );
            $statement->execute([$loginId]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['login_id'] = $user['login_id'];

                header('Location: ../index.php');
                exit;
            }

            $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        } catch (PDOException $exception) {
            $error = '로그인 중 오류가 발생했습니다. 잠시 후 다시 시도하세요.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>로그인</title>
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
        <h1 class="auth-title">로그인</h1>

        <?php if (isset($_GET['registered'])): ?>
            <p class="auth-message success">회원가입이 완료되었습니다. 로그인하세요.</p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="auth-message error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <p class="auth-loggedin"><strong><?= e($_SESSION['login_id']) ?></strong>님은 이미 로그인되어 있습니다.</p>
            <p class="auth-footer"><a href="../index.php">메인으로</a> · <a href="logout.php">로그아웃</a></p>
        <?php else: ?>
            <form method="post">
                <div class="auth-field">
                    <label for="login_id">아이디</label>
                    <input type="text" id="login_id" name="login_id" maxlength="30"
                           value="<?= e($loginId) ?>" required autofocus>
                </div>
                <div class="auth-field">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="auth-button">로그인</button>
            </form>

            <p class="auth-footer">아직 계정이 없나요?<a href="register.php">회원가입</a></p>
        <?php endif; ?>
    </main>
</body>
</html>
<?php
// 조회·삭제 페이지 공통 머리말/꼬리말과 화면 스타일 (작성: 유채은)
// 최종 공통 레이아웃(이미소 님)이 나오면 이 파일만 바꾸면 됩니다.

function crud_page_start(string $title): void
{
    $loginId = $_SESSION['login_id'] ?? null;
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · 이상기후와 과일 가격</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f5f7f4;
            font-family: -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Malgun Gothic", "맑은 고딕", sans-serif;
            color: #20352d;
            font-size: 15px;
        }

        a { color: #2f6b4f; }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 32px;
            background: #ffffff;
            border-bottom: 1px solid #dce4da;
        }
        .topbar .brand { font-weight: 700; color: #2f6b4f; text-decoration: none; }
        .topbar .user { font-size: 14px; color: #666666; }
        .topbar .user a { margin-left: 10px; font-weight: 600; text-decoration: none; }

        .container { max-width: 1120px; margin: 0 auto; padding: 32px 24px 64px; }

        h1 { margin: 0 0 6px; font-size: 26px; }
        .subtitle { margin: 0 0 24px; color: #777777; font-size: 14px; }

        .card {
            border: 1px solid #dce4da;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(32, 53, 45, 0.06);
            padding: 24px;
            margin-bottom: 20px;
        }
        .card h2 { margin: 0 0 16px; font-size: 18px; }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px 16px;
        }
        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #555555;
        }
        .field input, .field select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dddddd;
            border-radius: 9px;
            background: #fafafa;
            font-size: 14px;
            font-family: inherit;
        }
        .field input:focus, .field select:focus {
            outline: none;
            border-color: #2f6b4f;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(47, 107, 79, 0.18);
        }
        .range { display: flex; align-items: center; gap: 6px; }
        .range span { color: #999999; }

        .actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: #2f6b4f; color: #ffffff; }
        .btn-primary:hover { background: #24533d; }
        .btn-light { background: #ffffff; border-color: #dce4da; color: #3d5a4a; }
        .btn-light:hover { background: #eef3ec; }
        .btn-danger { background: #ffffff; border-color: #f2c4bf; color: #c0392b; }
        .btn-danger:hover { background: #fdecea; }
        .btn-sm { padding: 5px 10px; font-size: 13px; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }

        .result-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .result-head .count { font-size: 14px; color: #666666; }
        .result-head .count strong { color: #20352d; }
        .result-head .tools { display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 11px 10px; border-bottom: 1px solid #e6ece4; text-align: left; white-space: nowrap; }
        th { background: #eef3ec; color: #3d5a4a; font-weight: 700; font-size: 13px; }
        tbody tr:hover { background: #f8faf7; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.check, th.check { width: 36px; text-align: center; }
        td.manage { text-align: right; }
        td.manage .btn { margin-left: 4px; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e3efe6;
            color: #2f6b4f;
            font-size: 12px;
            font-weight: 700;
        }

        .empty { padding: 48px 0; text-align: center; color: #888888; }

        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span {
            min-width: 36px;
            padding: 7px 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            text-decoration: none;
        }
        .pagination a { color: #3d5a4a; background: #ffffff; border: 1px solid #dce4da; }
        .pagination a:hover { background: #eef3ec; }
        .pagination .current { background: #2f6b4f; color: #ffffff; font-weight: 700; }
        .pagination .gap { color: #aaaaaa; }

        .message { margin: 0 0 20px; padding: 12px 16px; border-radius: 10px; font-size: 14px; }
        .message.success { background: #e8f5e9; color: #2e7d32; }
        .message.error { background: #fdecea; color: #b3261e; }
        .message.info { background: #eef3ec; color: #3d5a4a; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        dl.info { display: grid; grid-template-columns: 120px 1fr; gap: 10px 12px; margin: 0; }
        dl.info dt { color: #888888; font-size: 14px; }
        dl.info dd { margin: 0; font-weight: 600; }
        .muted { color: #aaaaaa; font-weight: 400; }

        @media (max-width: 860px) {
            .filters { grid-template-columns: 1fr 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="../index.php">이상기후와 과일 가격</a>
        <div class="user">
            <?php if ($loginId !== null): ?>
                <strong><?= e($loginId) ?></strong>님
                <a href="../auth/logout.php">로그아웃</a>
            <?php else: ?>
                로그인하면 삭제할 수 있어요
                <a href="../auth/login.php">로그인</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">
<?php
}

function crud_page_end(): void
{
    ?>
    </main>
</body>
</html>
<?php
}

// 돌아갈 주소는 검색 목록(list.php)만 허용합니다. (외부 주소로 보내는 것 방지)
function safe_list_url(?string $url): string
{
    if (is_string($url) && preg_match('/^list\.php(\?[A-Za-z0-9_\-=&%.+]*)?$/', $url)) {
        return $url;
    }

    return 'list.php';
}

function format_won($value): string
{
    return number_format((int) $value) . '원';
}
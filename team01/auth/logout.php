<?php
// 로그아웃 (작성: 유채은)
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;

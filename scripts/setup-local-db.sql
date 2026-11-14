-- 작성자: 김예원
-- 로컬 XAMPP 전용: root 등 계정 생성 권한이 있는 관리자 계정으로 실행합니다.
-- 평가 서버에서는 제공된 team01 DB/계정을 사용하므로 실행하지 않습니다.
-- 기존 계정 비밀번호나 기존 데이터를 변경하지 않습니다.
CREATE DATABASE IF NOT EXISTS team01
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'team01'@'localhost' IDENTIFIED BY 'team01';
GRANT ALL PRIVILEGES ON team01.* TO 'team01'@'localhost';

# 새 XAMPP 환경 설치 테스트

작성자: 박선영
테스트일: 2026-11-22

## 테스트 환경

| 항목 | 결과 |
| --- | --- |
| 설치 위치 | `E:\xampp` |
| XAMPP 패키지 | Windows x64 8.3.36 |
| Apache | 2.4.65 |
| PHP | 8.3.36 |
| MariaDB | 10.11.16 |
| PHP 확장 | `pdo_mysql`, `mbstring` 확인 |
| 문자셋 | `utf8mb4` |

공식 설치 파일 `xampp-windows-x64-8.3.36-0-VC16-installer.exe`의 SHA-256은 `DD54667C0C379CC137A92889857BD809C24A619F18EACB09C7376EF914391BBF`와 일치했습니다.

기존 MySQL 8이 3306 포트를 사용하고 있어 XAMPP MariaDB는 `E:\xampp\mysql\bin\my.ini`의 활성 포트 두 곳을 3307로 변경했습니다. 프로젝트 개인 설정 `team01/config/local.php`에도 3307만 지정했으며 이 파일은 Git에서 제외됩니다.

## 수행 결과

| 순서 | 검증 내용 | 결과 |
| --- | --- | --- |
| 1 | 공식 XAMPP 설치 파일 SHA-256 확인 | PASS |
| 2 | `E:\xampp`에 새로 설치 | PASS |
| 3 | Apache 실행 및 80 포트 HTTP 응답 | PASS |
| 4 | MariaDB 3307 실행 및 `mysqladmin ping` | PASS |
| 5 | `team01` DB와 로컬 계정 생성 | PASS |
| 6 | `dbcreate.sql` 실행 | PASS |
| 7 | 테이블 9개, 뷰 1개, 외래키 7개 확인 | PASS |
| 8 | PDO Prepared Statement와 한글 왕복 | PASS |
| 9 | `/team01/` HTTP 200 응답 | PASS |
| 10 | 로그인·가격 INSERT·UPDATE | PASS |

`dbinsert.sql`은 아직 통합되지 않아 최종 데이터 건수와 최종 `dbdump.sql` 복원 검증은 데이터 파일이 준비된 뒤 수행합니다.

## 재현 명령

설치 경로와 포트는 각 PC 환경에 맞게 바꿉니다.

```powershell
$xampp = 'E:\xampp'
$dbPort = 3307
$setupSql = (Resolve-Path .\scripts\setup-local-db.sql).Path.Replace('\', '/')
$createSql = (Resolve-Path .\sql\dbcreate.sql).Path.Replace('\', '/')

& "$xampp\mysql\bin\mysql.exe" --no-defaults --protocol=TCP `
  --host=127.0.0.1 --port=$dbPort --user=root `
  --execute="source $setupSql"

& "$xampp\mysql\bin\mysql.exe" --no-defaults --protocol=TCP `
  --host=127.0.0.1 --port=$dbPort --user=team01 --password=team01 team01 `
  --execute="source $createSql"

& "$xampp\php\php.exe" .\scripts\check-db.php
```

전체 초기화는 운영 데이터가 아닌 별도 테스트 DB에서 다음 순서로 검증합니다.

1. `dbdump.sql`로 현재 상태 백업
2. `dbdrop.sql` 실행 후 테이블·뷰 0개 확인
3. `dbcreate.sql` 실행
4. `dbinsert.sql` 실행
5. `dbdump.sql` 복원 및 테이블별 건수 비교

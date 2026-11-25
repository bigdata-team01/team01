# 새 XAMPP 환경 설치 테스트

작성자: 박선영
최초 테스트일: 2026-11-22

통합 데이터·덤프 재검증일: 2026-11-23

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
| 11 | `dbdrop.sql → dbcreate.sql → dbinsert.sql` 전체 초기화 | PASS |
| 12 | 통합 데이터 테이블별 행 수 검증 | PASS |
| 13 | `v_price_weather` 가격·날씨 연결 5,386행 | PASS |
| 14 | `WEATHER_MONTHLY.total_rain` NULL·음수 0건 | PASS |
| 15 | `dbdump.sql` 생성 및 일반 `team01` 계정으로 별도 DB 복원 | PASS |
| 16 | 원본·복원 DB의 행 수와 핵심 합계 비교 | PASS |
| 17 | Ranking 기본 500행·조건 조회·가격 오름차순/내림차순 | PASS |

## 최종 통합 데이터 검증

| 테이블·뷰 | 행 수 |
| --- | ---: |
| `FRUIT_CATEGORY` | 4 |
| `FRUIT` | 5 |
| `FRUIT_VARIETY` | 11 |
| `PRODUCTION_AREA` | 8 |
| `MARKET_REGION` | 5 |
| `PRICE_MONTHLY` | 5,386 |
| `WEATHER_MONTHLY` | 1,056 |
| `RAINY_SEASON` | 88 |
| `APP_USER` | 0 |
| `v_price_weather` | 5,386 |

- 월별 합계강수량 `total_rain`의 NULL과 음수는 각각 0건입니다.
- SPI3 자료가 없는 98개 월의 `drought_level`은 설계대로 NULL입니다.
- 가격 행 5,386건 모두 같은 달의 주산지 날씨와 연결됐습니다.
- 별도 `team01_dump_verify` DB에 관리자 계정이 아닌 일반 `team01` 계정으로 `dbdump.sql`을 복원한 뒤 원본과 테이블·뷰 수, 모든 행 수, 가격 합계, 월강수량 합계, 장마 강수량 합계가 일치함을 확인했습니다.
- 복원 검증 후 임시 DB는 삭제했으며 실제 `team01` DB는 통합 데이터 상태로 유지했습니다.

## Ranking 통합 검증

- 기본 조회는 HTTP 200과 실제 순위 500행을 반환했습니다.
- `2025-12 / 사과 / 상품` 조건 조회는 소비지역 5행을 반환했습니다.
- 높은 가격 순은 `29,510 → 28,608 → 28,292 → 27,391 → 25,356원`, 낮은 가격 순은 그 반대 순서임을 확인했습니다.
- 로그인·회원가입 화면은 HTTP 200, 비로그인 가격 등록 접근은 로그인 화면으로 HTTP 303 리다이렉트됐습니다.

## 재현 명령

설치 경로와 포트는 각 PC 환경에 맞게 바꿉니다.

```powershell
$xampp = 'E:\xampp'
$dbPort = 3307
$setupSql = (Resolve-Path .\scripts\setup-local-db.sql).Path.Replace('\', '/')
$createSql = (Resolve-Path .\sql\dbcreate.sql).Path.Replace('\', '/')
$insertSql = (Resolve-Path .\sql\dbinsert.sql).Path.Replace('\', '/')

& "$xampp\mysql\bin\mysql.exe" --no-defaults --protocol=TCP `
  --host=127.0.0.1 --port=$dbPort --user=root `
  --execute="source $setupSql"

& "$xampp\mysql\bin\mysql.exe" --no-defaults --protocol=TCP `
  --host=127.0.0.1 --port=$dbPort --user=team01 --password=team01 team01 `
  --execute="source $createSql"

& "$xampp\mysql\bin\mysql.exe" --no-defaults --protocol=TCP `
  --host=127.0.0.1 --port=$dbPort --user=team01 --password=team01 team01 `
  --execute="source $insertSql"

& "$xampp\php\php.exe" .\scripts\check-db.php

& .\scripts\create-dbdump.ps1 -XamppPath $xampp -Port $dbPort
```

전체 초기화는 운영 데이터가 아닌 별도 테스트 DB에서 다음 순서로 검증합니다.

1. `dbdump.sql`로 현재 상태 백업
2. `dbdrop.sql` 실행 후 테이블·뷰 0개 확인
3. `dbcreate.sql` 실행
4. `dbinsert.sql` 실행
5. 별도 빈 DB에 `dbdump.sql` 복원
6. 원본과 복원 DB의 테이블·뷰 수, 행 수, 핵심 합계 비교

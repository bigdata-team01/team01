# 이상기후와 과일 가격 · Team01

KAMIS 과일 소매가격과 기상청 날씨 자료를 이용하는 빅데이터응용 팀 프로젝트입니다.

**현재 상태:** 공통 폴더, PHP 진입 페이지, PDO 연결 설정만 준비되었습니다. `dbcreate.sql` 미수령으로 프로젝트 테이블·뷰·데이터 및 분석 기능은 아직 없습니다. 연결 검사 성공은 기능 구현 완료를 의미하지 않습니다.

## 개발 환경

- Apache + PHP + MariaDB (XAMPP)
- 이 PC에서 확인한 버전: Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16
- PHP 확장: `pdo_mysql`, `mbstring`; DB 문자셋: `utf8mb4`
- 현재 PC의 XAMPP MariaDB: `127.0.0.1:3307` (기존 MySQL은 3306)
- 평가 환경: `htdocs/team01`, DB 이름·사용자·비밀번호 모두 `team01`
- 평가 서버의 정확한 버전·포트는 별도 확인하고 해당 환경에서 설치 테스트합니다.

## 폴더와 담당

```text
weather/                     # 저장소 이름; 로컬 checkout 폴더 이름은 자유
├─ team01/                   # htdocs/team01에 배치할 PHP 앱 전체
│  ├─ index.php              # 임시 진입 페이지
│  ├─ config/                # DB 기본값, 공통 PDO 함수, 개인 설정
│  ├─ includes/              # bootstrap, 추후 공통 레이아웃
│  ├─ analysis/              # 그룹핑, Rollup/Drill down, Ranking, Windowing
│  ├─ crud/                  # 조회·등록·수정·삭제
│  ├─ auth/                  # 로그인·회원가입·세션
│  └─ assets/                # css, js, images
├─ sql/                      # 수령할 과제 DB 스크립트
├─ scripts/                  # CLI 연결 검사, 로컬 DB 준비
└─ docs/                     # 구현 기준과 확인할 사항
```

| 담당 | 업무 |
| --- | --- |
| 김예원 | 공통 폴더·DB 설정, Rollup / Drill down |
| 이미소 | 다중 그룹핑, 최종 메인 페이지·공통 레이아웃 |
| 박선영 | Ranking, dbdrop/dbdump, 새 XAMPP 설치 테스트 |
| 박시원 | Windowing, 데이터 통합·dbinsert |
| 이혜령 | INSERT/UPDATE, 트랜잭션, dbcreate·ERD 정리 |
| 유채은 | SELECT/DELETE, 로그인·세션, PHP 구조 다이어그램 |

현재 화면과 CSS는 개발 준비용이며 최종 공통 UI를 대신하지 않습니다. 각자 구현한 코드에 작성자 이름을 남깁니다.

## 처음 실행하기 (Windows PowerShell)

### 1. 저장소 받기와 웹 경로 연결

팀원이 GitHub 접근 권한을 받은 후 실행합니다. 현재 최초 구성은 로컬 커밋만 하며 아직 업로드하지 않습니다.

```powershell
git clone https://github.com/bigdata-team01/weather.git
cd weather
$appPath = (Resolve-Path .\team01).Path
New-Item -ItemType Junction -Path C:\xampp\htdocs\team01 -Target $appPath
```

`C:\xampp\htdocs\team01`이 이미 있으면 먼저 기존 경로의 내용을 확인하세요. 위 명령은 기존 폴더를 삭제하거나 교체하지 않습니다. 권한 오류가 나면 관리자 PowerShell에서 실행합니다. Junction을 사용하면 checkout에서 수정한 PHP가 즉시 웹에 반영됩니다.

평가 서버에는 Junction 대신 **`team01` 폴더 자체를 `htdocs/team01`로 복사**합니다. 저장소 전체를 웹 경로에 넣지 않습니다.

### 2. XAMPP 실행

XAMPP Control Panel에서 Apache와 MySQL(MariaDB)을 실행할 수 있습니다. 현재 PC에서는 기존 MySQL과의 충돌을 피하도록 MariaDB 설정(`C:\xampp\mysql\bin\my.ini`)이 3307로 변경되어 있습니다. 다른 팀원은 자신의 설정 포트를 사용합니다.

직접 실행할 경우, 이미 실행 중인지 먼저 확인합니다.

```powershell
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -in 80,443,3307 }

Start-Process C:\xampp\apache\bin\httpd.exe -WindowStyle Hidden
Start-Process C:\xampp\mysql\bin\mysqld.exe `
    -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' `
    -WorkingDirectory C:\xampp -WindowStyle Hidden
```

현재 PC에서 Control Panel의 포트 표시 설정 파일은 관리자 권한 제한으로 변경되지 않았습니다. 실제 MariaDB 포트는 `my.ini`의 3307입니다. Windows 부팅 시 자동 실행은 설정하지 않았습니다.

### 3. 개인 DB 설정

공통 기본값은 `team01/config/database.php` 한 곳에 있습니다. 기본값은 `127.0.0.1:3306`, `team01/team01/team01`입니다.

현재 PC처럼 포트가 3307이면 아래 파일을 복사합니다.

```powershell
Copy-Item .\team01\config\local.example.php .\team01\config\local.php
```

`local.php`의 값이 기본값을 덮어씁니다. 기존 `local.php`가 있다면 복사 대신 직접 수정합니다. 이 파일은 Git에서 제외됩니다. 다른 포트·계정·비밀번호도 여기서 설정합니다. 평가 서버에 제출할 때는 개인 `local.php`를 제외하고 기본값을 확인하세요.

### 4. 로컬 DB·계정 생성

XAMPP MariaDB 관리자 계정으로 **로컬에서만** 실행합니다. 이 스크립트는 DB와 계정을 만들고 `team01` DB에만 권한을 부여합니다. 테이블은 만들지 않고 기존 데이터나 계정 비밀번호도 바꾸지 않습니다.

```powershell
$setupSql = (Resolve-Path .\scripts\setup-local-db.sql).Path.Replace('\', '/')
& C:\xampp\mysql\bin\mysql.exe --no-defaults --protocol=TCP `
    --host=127.0.0.1 --port=3307 --user=root --execute="source $setupSql"
```

root 비밀번호가 있는 환경에서는 `--user=root` 뒤에 `-p`를 추가해 입력합니다. 기존 `team01` 계정의 비밀번호가 다른 경우에는 임의로 바꾸지 말고 개인 `local.php`에 맞춥니다. 평가 서버에서는 이미 제공된 계정을 사용하며 이 관리자용 스크립트를 실행하지 않습니다.

### 5. 연결과 웹 확인

```powershell
& C:\xampp\php\php.exe .\scripts\check-db.php
```

DB 이름 `team01`, 포트, 문자셋, Prepared Statement 및 한글 왕복 검사를 출력합니다. `dbcreate.sql`을 아직 적용하지 않았다면 테이블/뷰 수는 0입니다.

브라우저에서 [프로젝트](http://localhost/team01/)를 엽니다. 초기 화면은 DB 없이도 열리므로 DB 검사는 별도로 해야 합니다. DB 관리는 [phpMyAdmin](http://localhost/phpmyadmin/)에서 할 수 있습니다.

## dbcreate.sql을 받은 뒤

1. `sql/`에 담당자의 스크립트를 배치하고 수정 ERD와 일치하는지 확인합니다.
2. 새 DB라면 `dbcreate.sql` → `dbinsert.sql` 순서로 적용합니다.
3. 초기화 테스트는 기존 데이터를 백업한 별도 테스트 DB에서 `dbdrop.sql` → `dbcreate.sql` → `dbinsert.sql` 순으로 진행합니다.
4. `v_price_weather` 뷰까지 준비되면 각 분석 기능을 연결합니다.

예시(파일 수령 이후에만 실행):

```powershell
& C:\xampp\mysql\bin\mysql.exe --no-defaults --protocol=TCP `
    --host=127.0.0.1 --port=3307 --user=team01 -p team01
```

DB 콘솔에서 실제 checkout 경로로 바꿔 실행합니다.

```sql
SOURCE C:/your-checkout/weather/sql/dbcreate.sql;
SOURCE C:/your-checkout/weather/sql/dbinsert.sql;
```

## 공통 PDO 사용

`analysis/example.php` 같은 한 단계 하위 페이지에서:

```php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// 뷰가 생성된 뒤 사용합니다. SQL 값은 바인딩합니다.
$statement = db()->prepare(
    'SELECT ym, avg_price FROM v_price_weather WHERE variety_name = ? AND grade = ?'
);
$statement->execute([$varietyName, $grade]);
$rows = $statement->fetchAll();
```

`bootstrap.php`는 세션을 시작하고 `db()`와 HTML 출력 이스케이프 함수 `e()`를 불러옵니다. 모든 페이지는 출력 전에 초기화하세요. 정렬 컬럼·집계 단계 같은 SQL 식별자는 바인딩할 수 없으므로 허용 목록으로 선택합니다. 웹 화면에는 DB 예외 원문이나 접속 정보를 노출하지 않습니다.

분석 구현 전 [합의된 설계와 미해결 사항](docs/project-context.md)을 읽으세요.

## 로컬 검토와 커밋

```powershell
git status
git diff
git add README.md team01 scripts sql docs .gitignore .gitattributes
git diff --cached
git commit -m "Describe the change"
```

`plan/`, `plan-resources/`, 개인 `local.php`는 커밋하지 않습니다. 최초 구성 커밋은 로컬 검토용이며 푸시는 사용자가 검토한 후 별도로 진행합니다. 원격 저장소 읽기에 인증이 필요한 경우 로그인 후 `git fetch origin`으로 기존 이력을 확인하고 통합합니다. 강제 푸시는 하지 않습니다.

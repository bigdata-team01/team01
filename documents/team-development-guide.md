# Team01 팀원 개발 안내

프로젝트를 처음 내려받아 실행하는 방법, 공통 파일의 역할, 개인 DB 설정, SQL 수령 이후의 작업 순서입니다. 명령어는 Windows PowerShell과 `C:\xampp` 설치를 기준으로 합니다.

## 1. 현재 준비된 범위

- 저장소: https://github.com/bigdata-team01/team01
- 준비 완료: 공통 폴더, 임시 시작 화면, 세션 초기화, PDO 연결 함수, 로컬 DB·계정 준비 스크립트, 연결 검사 도구.
- 아직 없음: `dbcreate.sql`, `dbinsert.sql`, `dbdrop.sql`, `dbdump.sql`, 프로젝트 테이블·뷰·데이터, 분석·CRUD·로그인 기능.
- 작성 시점에는 로컬 검토 단계입니다. 아래 clone 절차는 공통 코드가 GitHub에 업로드된 뒤 사용할 수 있습니다.

예원 PC에서 확인한 버전은 Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16입니다. PHP의 `pdo_mysql`과 `mbstring` 확장을 사용합니다. 평가 서버의 정확한 버전은 별도로 확인하고 설치 테스트해야 합니다.

**저장소 폴더와 웹 앱 폴더는 다릅니다.** 저장소를 `team01`이라는 이름으로 받으면 그 안에 웹 앱용 `team01/`이 한 번 더 있습니다. Apache에 연결하거나 제출할 대상은 안쪽의 `team01/`입니다.

## 2. 폴더와 파일의 역할

```text
프로젝트 루트/
├─ .gitattributes
├─ .gitignore
├─ README.md
├─ documents/
│  └─ team-development-guide.md
├─ scripts/
│  ├─ setup-local-db.sql
│  └─ check-db.php
├─ sql/
│  └─ README.md
└─ team01/                         ← htdocs/team01에 연결할 웹 앱
   ├─ .htaccess
   ├─ index.php
   ├─ config/
   │  ├─ .htaccess
   │  ├─ database.php
   │  ├─ db.php
   │  ├─ local.example.php
   │  └─ local.php                 ← 각자 생성, Git 제외
   ├─ includes/
   │  ├─ .htaccess
   │  └─ bootstrap.php
   ├─ analysis/.gitkeep
   ├─ crud/.gitkeep
   ├─ auth/.gitkeep
   └─ assets/
      ├─ css/common.css
      ├─ js/.gitkeep
      └─ images/.gitkeep
```

### 공통 코드와 화면

| 파일 | 역할과 사용 방법 |
| --- | --- |
| `team01/index.php` | `/team01/`의 임시 시작 화면. 최종 메인 UI는 아직 없습니다. DB를 호출하지 않으므로 화면이 열린다고 DB 연결까지 확인된 것은 아닙니다. |
| `team01/includes/bootstrap.php` | 공통 초기화. `db()` 함수를 로드하고 세션을 시작하며 HTML 출력 보조 함수 `e()`를 제공합니다. HTML 출력 전에 불러옵니다. |
| `team01/config/db.php` | `db(): PDO` 제공. 기본값과 개인 설정을 합쳐 첫 호출 시 연결하며, 같은 요청 안에서는 연결을 재사용합니다. |
| `team01/config/database.php` | 팀 공통 DB 접속 기본값. 평가용 DB/user/password는 `team01`입니다. |
| `team01/config/local.example.php` | 개인 설정 예시. 필요하면 `local.php`로 복사하고 자신의 환경에 맞춥니다. |
| `team01/config/local.php` | 개인 설정. 기본값 중 필요한 항목만 덮어씁니다. Git에 올리지 않습니다. |
| `team01/assets/css/common.css` | 임시 화면의 최소 스타일. 최종 공통 UI 작업 시 조정합니다. |
| `team01/.htaccess` | `index.php`를 기본 페이지로 지정하고 폴더의 파일 목록 노출을 막습니다. |
| `team01/config/.htaccess` | 설정 파일에 대한 HTTP 직접 접근을 차단합니다. PHP 내부의 `require`는 가능합니다. |
| `team01/includes/.htaccess` | 공통 코드 폴더에 대한 HTTP 직접 접근을 차단합니다. |

### 도구·문서·Git 설정

| 파일 | 역할 |
| --- | --- |
| `scripts/setup-local-db.sql` | 로컬 관리자 계정으로 실행하는 DB·계정 생성용 SQL. 프로젝트 테이블은 만들지 않고 기존 계정 비밀번호도 바꾸지 않습니다. |
| `scripts/check-db.php` | 터미널 전용 연결 검사. DB 이름·버전·포트·문자셋, Prepared Statement, 한글 처리, 테이블/뷰 수를 확인합니다. 데이터는 변경하지 않습니다. |
| `sql/README.md` | 받을 SQL 파일의 담당과 초기화 순서 안내. |
| `README.md` | 저장소 개요와 빠른 실행 안내. |
| `documents/team-development-guide.md` | 팀원에게 공유할 상세 안내인 이 문서. Git 공유 대상입니다. |
| `.gitignore` | 개인 설정, 로컬 자료, 로그 등을 Git에서 제외합니다. |
| `.gitattributes` | PHP·SQL·Markdown·CSS 등의 줄바꿈을 통일합니다. |
| 각 폴더의 `.gitkeep` | Git에 빈 폴더를 남기는 빈 파일. 실행 기능은 없으며 실제 파일이 생기면 제거해도 됩니다. |

`docs/`, `plan/`, `plan-resources/`는 로컬 참고 자료로 Git에서 제외되어 팀원이 clone해도 받지 않습니다. **공유 문서는 `documents/`에 작성합니다.**

### 담당별 코드 위치

| 담당 | 작업 내용 | 기본 위치 |
| --- | --- | --- |
| 김예원 | Rollup / Drill down, 공통 DB 설정 | `analysis/`, `config/` |
| 이미소 | 다중 그룹핑, 메인 화면·공통 레이아웃 | `analysis/`, `index.php`, `includes/`, `assets/` |
| 박선영 | Ranking, 삭제·덤프 SQL, 설치 테스트 | `analysis/`, 루트의 `sql/` |
| 박시원 | Windowing, 정제 데이터 통합 | `analysis/`, 루트의 `sql/` |
| 이혜령 | INSERT/UPDATE, 트랜잭션, DB 스키마 | `crud/`, 루트의 `sql/` |
| 유채은 | SELECT/DELETE, 로그인·세션 | `crud/`, `auth/` |

PHP 경로는 `team01/` 아래입니다. 기능별 파일명과 공통 레이아웃 변경은 담당자끼리 맞추고, 작성한 코드에 이름을 주석으로 남깁니다.

## 3. database.php와 local.php

공통 기본값인 `team01/config/database.php`:

```php
<?php
declare(strict_types=1);

return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'team01',
    'username' => 'team01',
    'password' => 'team01',
    'charset' => 'utf8mb4',
];
```

| 항목 | 의미 |
| --- | --- |
| `host` | DB 서버 주소. 로컬은 `127.0.0.1` 사용 |
| `port` | MariaDB가 실제 실행 중인 포트 |
| `database` | 접속할 DB 이름 |
| `username`, `password` | DB 접속 계정과 비밀번호. 웹사이트 로그인 계정과는 별개 |
| `charset` | 연결 문자셋. `utf8mb4` 유지 |

예원 PC는 기존 MySQL이 3306을 사용하므로 XAMPP MariaDB를 3307로 실행합니다. 개인 `local.php`에는 아래 값만 있어도 됩니다.

```php
<?php
declare(strict_types=1);

return [
    'port' => 3307,
];
```

호스트·DB 이름·계정·문자셋은 공통값을 그대로 사용합니다. 개인 비밀번호가 다르면 `password` 항목도 추가합니다. 개인 환경 때문에 공통 `database.php`를 변경하지 마세요.

- 3306과 기본 계정을 사용하는 팀원은 `local.php` 없이 실행할 수 있습니다.
- `local.example.php`는 예시일 뿐 자동으로 읽히지 않습니다.
- `db.php`는 `database.php`를 읽고 `local.php`의 항목만 덮어쓴 뒤 연결합니다.
- PDO는 오류 시 예외를 발생시키고, 결과를 연관 배열로 반환하며, 서버의 Prepared Statement를 사용합니다.
- `local.php` 변경은 접속 대상 설정입니다. MariaDB 서버 자체의 포트나 계정을 바꾸지는 않습니다.
- Git 업데이트는 DB에 자동 반영되지 않으며, 개인 `local.php`도 공유하지 않습니다.

## 4. 처음 받아서 실행하기

### 4-1. 저장소 받기

Git과 XAMPP를 설치합니다. GitHub 저장소 접근 권한을 받고 공통 코드가 업로드된 후, 프로젝트를 둘 폴더에서 실행합니다.

```powershell
git clone https://github.com/bigdata-team01/team01.git
cd team01
```

이후 명령은 특별한 설명이 없으면 `README.md`가 있는 프로젝트 루트에서 실행합니다. 이미 clone했다면 그 폴더에서 작업합니다.

### 4-2. 웹 앱 폴더 연결

```powershell
$appPath = (Resolve-Path .\team01).Path
New-Item -ItemType Junction -Path C:\xampp\htdocs\team01 -Target $appPath
```

Junction은 두 폴더를 연결합니다. checkout 안의 PHP를 수정하면 웹에 바로 반영되어 매번 복사할 필요가 없습니다. `htdocs/team01`이 이미 있으면 내용을 확인하고, 임의로 삭제하지 않습니다. 권한 오류가 나면 관리자 PowerShell에서 실행합니다.

checkout 위치를 옮기면 Junction도 새 위치에 맞춰야 합니다. Junction 대신 안쪽 `team01/`을 `htdocs/team01`로 복사해도 되지만, 이후 변경도 직접 동기화해야 합니다. 저장소 전체를 웹 경로에 넣지 않습니다.

### 4-3. 서버 시작

XAMPP Control Panel에서 **Apache와 MySQL(MariaDB)** 을 시작합니다. 이미 떠 있는 서버를 중복 실행하지 않습니다. 다른 MySQL이 3306을 점유하면 XAMPP MariaDB를 별도 포트로 구성하고 개인 DB 설정도 맞춥니다.

Control Panel 대신 명령으로 시작할 경우:

```powershell
Start-Process C:\xampp\apache\bin\httpd.exe -WindowStyle Hidden
Start-Process C:\xampp\mysql\bin\mysqld.exe `
    -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' `
    -WorkingDirectory C:\xampp -WindowStyle Hidden
```

서버 포트는 `C:\xampp\mysql\bin\my.ini`에 설정됩니다. 예원 PC는 3307이며, 모든 팀원이 3307을 사용할 필요는 없습니다. 설치 경로가 다르면 명령의 경로도 바꿉니다.

### 4-4. 개인 설정

3307 등 기본값과 다른 설정이 필요할 때 복사합니다. 기존 `local.php`가 있으면 복사하지 말고 편집합니다.

```powershell
Copy-Item .\team01\config\local.example.php .\team01\config\local.php
```

복사한 파일의 포트와 계정을 실제 MariaDB 환경에 맞춥니다.

### 4-5. 로컬 DB와 계정 생성

`$dbPort`를 자신의 MariaDB 포트로 바꿉니다. 같은 PowerShell 창의 이후 명령에서도 이 변수를 사용합니다.

```powershell
$dbPort = 3307   # 기본 XAMPP 환경이면 3306
$setupSql = (Resolve-Path .\scripts\setup-local-db.sql).Path.Replace('\', '/')
& C:\xampp\mysql\bin\mysql.exe --no-defaults --protocol=TCP `
    --host=127.0.0.1 --port=$dbPort --user=root `
    --execute="source $setupSql"
```

root 비밀번호가 있으면 `--user=root` 뒤에 `-p`를 추가해 입력합니다. `team01` DB, `team01@localhost` 계정과 해당 DB의 권한을 준비하며 기본 비밀번호는 `team01`입니다. 기존 계정의 비밀번호는 바꾸지 않으므로 기존 값이 다르면 `local.php`에 맞춥니다.

평가 서버에는 DB·계정이 제공되므로 이 로컬 관리자용 스크립트를 실행하지 않습니다.

### 4-6. 연결 검사와 화면 확인

```powershell
& C:\xampp\php\php.exe .\scripts\check-db.php
```

DB 이름이 `team01`인지, 포트가 맞는지, `PDO prepared statement / UTF-8: OK`가 나오는지 확인합니다. 스키마 적용 전에는 `Tables/views: 0`입니다.

브라우저에서 http://localhost/team01/ 을 엽니다. Apache가 80 이외 포트라면 URL에도 포트를 넣습니다. 예: `http://localhost:8080/team01/`. **임시 화면과 DB 연결 검사는 별개입니다.**

### 4-7. 종료와 재시작

Control Panel의 Stop 버튼을 사용하거나 아래 명령을 실행합니다.

```powershell
$dbPort = 3307   # 자신의 MariaDB 포트
& C:\xampp\apache\bin\httpd.exe -k shutdown
& C:\xampp\mysql\bin\mysqladmin.exe --no-defaults --protocol=TCP `
    --host=127.0.0.1 --port=$dbPort --user=root shutdown
```

root 비밀번호가 있으면 `--user=root` 뒤에 `-p`를 추가합니다. 재시작은 종료 완료 후 시작 명령을 다시 실행합니다. 현재 구성은 Windows 부팅 시 자동 실행을 설정하지 않았습니다.

## 5. SQL 파일을 받은 이후

| 파일 | 담당 | 목적 |
| --- | --- | --- |
| `sql/dbcreate.sql` | 이혜령 | 테이블, PK/FK, 인덱스와 분석 공통 뷰 생성 |
| `sql/dbinsert.sql` | 박시원 | 정제한 초기 데이터 입력, FK 매핑 반영 |
| `sql/dbdrop.sql` | 박선영 | 초기화할 때 테이블 등 제거 |
| `sql/dbdump.sql` | 박선영 | 최종 상태 덤프. 복원 시 구조·데이터 포함 범위 확인 |

아직 이 파일들은 없습니다. 실제 파일이 도착한 뒤 아래 절차를 진행합니다. `setup-local-db.sql`은 `dbcreate.sql`의 대체물이 아닙니다.

### 새로 만든 빈 DB에 적용

1. 받은 파일을 루트의 `sql/`에 넣습니다.
2. 실행 대상 DB, DROP/DELETE 포함 여부, 수정 ERD와의 일치 여부를 확인합니다.
3. `dbcreate.sql`부터 적용하고 오류가 없는지 확인합니다.
4. `dbinsert.sql`도 준비되면 적용합니다. create만 받은 상태라면 스키마 확인까지만 합니다.
5. 테이블·뷰·데이터가 준비된 후 개별 기능을 연결합니다.

예를 들어 checkout이 `C:\projects\team01`이라면 다음과 같이 접속합니다.

```powershell
$dbPort = 3307   # 자신의 설정에 맞춤
& C:\xampp\mysql\bin\mysql.exe --no-defaults --protocol=TCP `
    --host=127.0.0.1 --port=$dbPort --user=team01 -p team01
```

비밀번호 입력 후 MariaDB 콘솔에서 실행합니다. **아래 경로는 자신의 실제 checkout 경로로 바꿉니다.**

```sql
SELECT DATABASE();
SOURCE C:/projects/team01/sql/dbcreate.sql;
SHOW FULL TABLES;
-- 위 단계가 성공하고 dbinsert.sql도 받은 뒤 실행
SOURCE C:/projects/team01/sql/dbinsert.sql;
-- 공통 뷰가 생성되었을 때 확인
SELECT * FROM v_price_weather LIMIT 5;
EXIT;
```

SQL 오류가 나오면 뒤 단계로 넘어가지 말고 담당자와 해결합니다. 재실행 가능 여부는 스크립트 내용에 따라 다릅니다. insert를 반복하면 중복 데이터나 제약조건 오류가 생길 수 있습니다.

### 기존 DB를 갱신하거나 초기화

- `git pull`로 SQL 파일을 받아도 로컬 DB에는 자동 적용되지 않습니다. 변경 내용과 적용 순서를 담당자에게 확인합니다.
- 기존 데이터를 보존하는 변경인지 전체 초기화인지 구분합니다.
- 전체 초기화가 필요하면 데이터를 백업한 뒤 `drop → create → insert` 순으로 실행합니다. 기존 데이터는 삭제됩니다.
- 별도 테스트 DB에서 실행할 때는 스크립트의 `USE team01` 같은 고정 DB명도 확인합니다. 접속 DB만 바꾼다고 안전하게 분리되지 않습니다.
- 덤프 복원은 구조·데이터 포함 범위를 확인하고 진행합니다. 같은 데이터를 `dbinsert.sql`로 또 넣지 않습니다.

## 6. 개별 기능 추가 시 공통 코드 사용

`team01/analysis/`나 `team01/crud/`의 페이지에서는 파일 맨 앞에서 초기화합니다.

```php
<?php
// 작성자: 본인 이름
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
```

세션이 시작되므로 페이지 간 공유할 값은 `$_SESSION`을 사용합니다. 다른 기능과 세션 키가 충돌하지 않도록 맞춥니다. 로그인 자체가 구현된 것은 아닙니다.

공통 뷰가 준비된 뒤 조회하는 예시:

```php
// 입력값 검증을 마친 뒤 실행하는 조회 예시
$varietyName = '후지';
$grade = '상품';

$statement = db()->prepare(
    'SELECT ym, avg_price FROM v_price_weather
     WHERE variety_name = ? AND grade = ? ORDER BY ym'
);
$statement->execute([$varietyName, $grade]);
$rows = $statement->fetchAll();

foreach ($rows as $row) {
    echo '<p>' . e((string) $row['ym']) . ': '
        . e((string) $row['avg_price']) . '</p>';
}
```

- 페이지마다 DB 접속 정보를 복사하지 않고 `db()`를 사용합니다.
- 사용자 입력값은 검증하고 Prepared Statement로 바인딩합니다. 컬럼명·정렬 방향은 바인딩할 수 없으므로 허용 목록으로 선택합니다.
- `e()`는 HTML 출력용 이스케이프 함수입니다. SQL 처리나 JavaScript 인코딩용이 아닙니다.
- DB 오류는 예외로 발생합니다. 웹 화면에 접속 정보나 오류 원문을 그대로 출력하지 않습니다.
- CSS·이미지 경로는 페이지 깊이에 맞춥니다. `analysis/` 페이지의 CSS 경로 예: `../assets/css/common.css`.

### 분석 구현 기준

[Notion ERD 수정안](https://app.notion.com/p/d5ca47762fc883afa1f38197be7580b3)을 기준으로 하고 실제 `dbcreate.sql`과 대조합니다.

- 분석 쿼리는 공통 `v_price_weather` 뷰를 사용합니다.
- 주산지는 품목이 아니라 품종의 `FRUIT_VARIETY.area_id`로 연결합니다.
- 가격·날씨 비교는 품종 단위, 품목·과일군 수준은 가격 집계만 합니다.
- `ym`은 매월 1일인 DATE이며 기본 비교는 같은 달입니다.
- 서로 다른 거래 단위를 단순 합산·평균하지 않도록 집계 기준을 맞춥니다.
- 뷰에 필요한 ID나 컬럼이 없으면 스키마 담당과 협의합니다.
- 로그인 테이블은 현재 분석 ERD에 없으므로 담당자끼리 별도로 확정해야 합니다.

## 7. 자주 확인할 문제

| 증상 | 확인할 내용 |
| --- | --- |
| 프로젝트 접속 불가 | Apache 실행, 포트, `htdocs/team01` Junction 대상 |
| 화면은 열리지만 DB 오류 | `check-db.php`로 별도 확인. 임시 메인은 DB를 호출하지 않음 |
| Connection refused | MariaDB 실행 상태, 실제 포트와 `local.php` 포트 일치 여부 |
| Access denied | `team01` 계정, 비밀번호, 접속한 DB 서버가 맞는지 |
| Unknown database | 로컬 DB 생성 여부와 `database` 설정 |
| Table/view does not exist | `dbcreate.sql` 및 뷰 적용 여부 |
| 조회 결과 없음 | `dbinsert.sql` 적용 여부와 조건에 맞는 데이터 존재 여부 |
| 개인 설정이 다른 팀원에게 전달되지 않음 | 정상. `local.php`는 각자 만들며 Git에서 제외 |

## 8. 공유와 제출 전 확인

- 공유 문서는 `documents/`에 둡니다. `docs/`, `plan/`, `plan-resources/`, `local.php`는 Git에서 제외합니다.
- 현재 작업은 로컬 검토 후 업로드하는 방식입니다. 문서 작성이나 커밋만으로 원격 저장소에 업로드되지는 않습니다.
- 평가 서버에는 안쪽 `team01/`을 `htdocs/team01`로 복사하고 개발 PC용 `local.php`는 제외합니다.
- 평가 DB 이름·사용자·비밀번호 `team01` 및 실제 서버 포트를 확인합니다.
- SQL 4종과 전체 PHP를 준비하고 제출 파일만으로 새 XAMPP에서 재현되는지 테스트합니다.
- 현재 뼈대만으로 과제의 CRUD·분석·세션 활용·트랜잭션 등 구현 요구사항을 완료한 것은 아닙니다.

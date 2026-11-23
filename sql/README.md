# DB 스크립트

`dbcreate.sql`과 `dbdrop.sql`이 준비되어 있습니다. 최종 데이터 통합 후 `dbinsert.sql`과 `dbdump.sql`을 함께 검증합니다.

| 예정 파일 | 담당 / 용도 |
| --- | --- |
| `dbcreate.sql` | 이혜령 — 테이블, PK/FK, 인덱스, 공통 뷰 생성 |
| `dbinsert.sql` | 박시원 — 정제 데이터 통합 및 FK 매핑 |
| `dbdrop.sql` | 박선영 — 초기화용 삭제 스크립트 |
| `dbdump.sql` | 박선영 — 최종 DB 덤프 |

`dbdrop.sql`은 데이터가 삭제되는 작업이므로 기존 DB에 무심코 실행하지 않습니다. 최종 데이터가 준비되면 `../scripts/create-dbdump.ps1`로 구조·데이터·뷰를 포함한 `dbdump.sql`을 만들고, 별도 테스트 DB에서 `drop → create → insert`와 덤프 복원을 검증합니다.

`../scripts/setup-local-db.sql`은 로컬 DB와 계정만 생성하는 준비용 스크립트이며 과제 제출용 `dbcreate.sql`을 대체하지 않습니다.

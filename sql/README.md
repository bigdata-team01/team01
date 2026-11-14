# DB 스크립트

아직 `dbcreate.sql`을 전달받지 않았습니다. 이 폴더에는 스키마를 임의로 만들거나 빈 SQL 파일을 완성본처럼 추가하지 않습니다.

| 예정 파일 | 담당 / 용도 |
| --- | --- |
| `dbcreate.sql` | 이혜령 — 테이블, PK/FK, 인덱스, 공통 뷰 생성 |
| `dbinsert.sql` | 박시원 — 정제 데이터 통합 및 FK 매핑 |
| `dbdrop.sql` | 박선영 — 초기화용 삭제 스크립트 |
| `dbdump.sql` | 박선영 — 최종 DB 덤프 |

파일을 받은 후 기존 데이터 삭제 여부를 확인하고 `drop → create → insert` 순서로 검증합니다. `dbdrop.sql`은 데이터가 삭제되는 작업이므로 기존 DB에 무심코 실행하지 않습니다.

`../scripts/setup-local-db.sql`은 로컬 DB와 계정만 생성하는 준비용 스크립트이며 과제 제출용 `dbcreate.sql`을 대체하지 않습니다.

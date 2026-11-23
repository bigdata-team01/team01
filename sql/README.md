# DB 스크립트

`dbcreate.sql`, `dbinsert.sql`, `dbdrop.sql`이 준비되어 있습니다. 최종 `dbdump.sql`은 통합 데이터 확정 후 생성·검증합니다.

| 예정 파일 | 담당 / 용도 |
| --- | --- |
| `dbcreate.sql` | 이혜령 — 테이블, PK/FK, 인덱스, 공통 뷰 생성 |
| `dbinsert.sql` | 박시원 — 정제 데이터 통합 및 FK 매핑 |
| `dbdrop.sql` | 박선영 — 초기화용 삭제 스크립트 |
| `dbdump.sql` | 박선영 — 최종 DB 덤프 |

`dbdrop.sql`은 데이터가 삭제되는 작업이므로 기존 DB에 무심코 실행하지 않습니다. 최종 데이터가 준비되면 `../scripts/create-dbdump.ps1`로 구조·데이터·뷰를 포함한 `dbdump.sql`을 만들고, 별도 테스트 DB에서 `drop → create → insert`와 덤프 복원을 검증합니다.

`../scripts/setup-local-db.sql`은 로컬 DB와 계정만 생성하는 준비용 스크립트이며 과제 제출용 `dbcreate.sql`을 대체하지 않습니다.

## dbinsert.sql

`dbcreate.sql`을 적용한 빈 테이블에 한 번만 실행합니다. 다시 넣으려면 `dbdrop.sql → dbcreate.sql → dbinsert.sql` 순서로 실행합니다.

| 테이블 | 행 수 | 비고 |
| --- | --- | --- |
| `FRUIT_CATEGORY` / `FRUIT` / `FRUIT_VARIETY` | 4 / 5 / 11 | 인과류·핵과류·장과류·감귤류 |
| `PRODUCTION_AREA` | 8 | `area_id`는 ASOS 지점번호와 같은 값 |
| `MARKET_REGION` | 5 | 서울·부산·대구·광주·대전 |
| `PRICE_MONTHLY` | 5,386 | 2015-01 ~ 2025-12, 없는 월은 보간하지 않음 |
| `WEATHER_MONTHLY` | 1,056 | 8개 관측소 × 132개월 |
| `RAINY_SEASON` | 88 | 8개 관측소 × 11년 |

- `WEATHER_MONTHLY.drought_level`은 SPI3 기반 가뭄 단계(0~3)입니다. SPI3 자료가 없는 98개 월은 NULL입니다.
- `frost_days`는 3~5월만 집계한 값이며 그 외 달은 0입니다.
- 이 파일은 `../scripts/build-dbinsert.php`가 정제 CSV 9종을 검증해 생성합니다. 직접 고치지 말고 다시 생성하세요. CSV는 저장소에 포함하지 않습니다.

```powershell
& C:\xampp\php\php.exe .\scripts\build-dbinsert.php <정제 CSV 폴더>
```

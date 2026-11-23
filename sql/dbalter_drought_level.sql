-- 작성: 이혜령 — 기존 DB의 가뭄 컬럼을 SPI3 단계로 변경합니다.
-- 적용할 DB를 선택하고 백업한 뒤 실행하세요. dbdrop.sql은 실행하지 않습니다.
-- 기존 max_dry_streak에 값이 있으면 단계를 추정하지 않고 변경 전 중단합니다.
-- MariaDB DDL은 자동 커밋됩니다. 점검 시간에 실행하고 오류가 나면 중단하세요.
-- 성공 후 재실행 가능. ALTER/CREATE VIEW 권한이 필요합니다.
SET NAMES utf8mb4;
DELIMITER //
BEGIN NOT ATOMIC
    DECLARE old_count INT DEFAULT 0;
    DECLARE new_count INT DEFAULT 0;
    SELECT COUNT(*) INTO old_count FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='WEATHER_MONTHLY' AND COLUMN_NAME='max_dry_streak';
    SELECT COUNT(*) INTO new_count FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='WEATHER_MONTHLY' AND COLUMN_NAME='drought_level';
    IF old_count=1 AND new_count=0 THEN
        IF EXISTS (SELECT 1 FROM WEATHER_MONTHLY WHERE max_dry_streak IS NOT NULL) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='max_dry_streak contains values: preserve them and verify source before migration';
        END IF;
        ALTER TABLE WEATHER_MONTHLY
            DROP CONSTRAINT ck_weather_days,
            CHANGE COLUMN max_dry_streak drought_level TINYINT UNSIGNED NULL
                COMMENT 'SPI3 기반 가뭄 단계 0~3; 미확보는 NULL',
            ADD CONSTRAINT ck_weather_drought CHECK (drought_level BETWEEN 0 AND 3),
            ADD CONSTRAINT ck_weather_days CHECK (
                heatwave_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
                AND frost_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
                AND heavy_rain_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
                AND rainy_season_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
            );
    ELSEIF NOT (old_count=0 AND new_count=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unexpected WEATHER_MONTHLY columns: check selected database and schema';
    END IF;
    CREATE OR REPLACE VIEW v_price_weather AS
    SELECT
        p.ym, p.year, p.month, p.grade,
        p.avg_price, p.max_price, p.min_price,
        c.category_name, f.fruit_name,
        v.variety_name, v.unit,
        m.market_name, a.area_name,
        w.avg_temp, w.max_temp, w.min_temp, w.total_rain,
        w.heatwave_days, w.frost_days, w.heavy_rain_days,
        w.drought_level, w.rainy_season_days
    FROM PRICE_MONTHLY p
    JOIN FRUIT_VARIETY v ON p.variety_id = v.variety_id
    JOIN FRUIT f ON v.fruit_id = f.fruit_id
    JOIN FRUIT_CATEGORY c ON f.category_id = c.category_id
    JOIN MARKET_REGION m ON p.market_id = m.market_id
    JOIN PRODUCTION_AREA a ON v.area_id = a.area_id
    LEFT JOIN WEATHER_MONTHLY w ON w.area_id = a.area_id AND w.ym = p.ym;
END//
DELIMITER ;

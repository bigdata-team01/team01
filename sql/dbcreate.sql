-- 첨부 ERD 수정안 기준. MariaDB 10.2.1 이상 / InnoDB.
-- 평가용 team01 데이터베이스를 미리 생성하고, 해당 DB에 권한이 있는 계정으로 실행하세요.
-- 이 스크립트는 team01을 선택합니다. 다른 DB를 지정해 접속해도 team01에 적용됩니다.
-- 예: mysql -u team01 -p < dbcreate.sql
-- 기존 테이블을 삭제하거나 변경하지 않습니다. 이미 같은 이름의 테이블이 있으면 실행을 중단하세요.
SET NAMES utf8mb4;
USE team01;

CREATE TABLE FRUIT_CATEGORY (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(30) NOT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PRODUCTION_AREA (
    area_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL,
    station_id INT UNSIGNED NOT NULL,
    station_type ENUM('ASOS', 'AWS') NOT NULL,
    PRIMARY KEY (area_id),
    UNIQUE KEY uq_area_station (station_type, station_id),
    CONSTRAINT ck_station_type CHECK (station_type IN ('ASOS', 'AWS'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MARKET_REGION (
    market_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    market_name VARCHAR(30) NOT NULL,
    kamis_region_code VARCHAR(20) NOT NULL,
    PRIMARY KEY (market_id),
    UNIQUE KEY uq_market_code (kamis_region_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE FRUIT (
    fruit_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    fruit_name VARCHAR(30) NOT NULL,
    kamis_item_code VARCHAR(20) NOT NULL,
    PRIMARY KEY (fruit_id),
    UNIQUE KEY uq_fruit_code (kamis_item_code),
    CONSTRAINT fk_fruit_category FOREIGN KEY (category_id)
        REFERENCES FRUIT_CATEGORY (category_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE FRUIT_VARIETY (
    variety_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fruit_id INT UNSIGNED NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    variety_name VARCHAR(100) NOT NULL,
    kamis_kind_code VARCHAR(20) NOT NULL,
    unit VARCHAR(30) NOT NULL COMMENT '수량을 포함한 거래 단위: 10개, 1kg, 2kg 등',
    PRIMARY KEY (variety_id),
    UNIQUE KEY uq_variety_code (fruit_id, kamis_kind_code),
    CONSTRAINT fk_variety_fruit FOREIGN KEY (fruit_id)
        REFERENCES FRUIT (fruit_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_variety_area FOREIGN KEY (area_id)
        REFERENCES PRODUCTION_AREA (area_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PRICE_MONTHLY (
    price_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    variety_id INT UNSIGNED NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    ym DATE NOT NULL COMMENT '해당 월 1일',
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    grade ENUM('상품', '중품', 'S과', 'M과', 'L과') NOT NULL,
    avg_price INT UNSIGNED NOT NULL COMMENT '월평균 가격, 원 단위 반올림',
    max_price INT UNSIGNED NOT NULL COMMENT '월내 일평균 가격의 최댓값, 원 단위 반올림',
    min_price INT UNSIGNED NOT NULL COMMENT '월내 일평균 가격의 최솟값, 원 단위 반올림',
    PRIMARY KEY (price_id),
    UNIQUE KEY uq_price_month (variety_id, market_id, grade, ym),
    KEY idx_price_ym (ym),
    CONSTRAINT fk_price_variety FOREIGN KEY (variety_id)
        REFERENCES FRUIT_VARIETY (variety_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_price_market FOREIGN KEY (market_id)
        REFERENCES MARKET_REGION (market_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_price_month CHECK (
        DAYOFMONTH(ym) = 1 AND month BETWEEN 1 AND 12
        AND year = YEAR(ym) AND month = MONTH(ym)
    ),
    CONSTRAINT ck_price_grade CHECK (grade IN ('상품', '중품', 'S과', 'M과', 'L과')),
    CONSTRAINT ck_price_range CHECK (min_price <= avg_price AND avg_price <= max_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE WEATHER_MONTHLY (
    weather_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    area_id INT UNSIGNED NOT NULL,
    ym DATE NOT NULL COMMENT '해당 월 1일',
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    avg_temp DECIMAL(6,2) NULL,
    max_temp DECIMAL(6,2) NULL,
    min_temp DECIMAL(6,2) NULL,
    total_rain DECIMAL(10,2) NULL,
    heatwave_days TINYINT UNSIGNED NULL,
    frost_days TINYINT UNSIGNED NULL,
    heavy_rain_days TINYINT UNSIGNED NULL,
    drought_level TINYINT UNSIGNED NULL COMMENT 'SPI3 기반 가뭄 단계 0~3; 미확보는 NULL',
    rainy_season_days TINYINT UNSIGNED NULL,
    PRIMARY KEY (weather_id),
    UNIQUE KEY uq_weather_month (area_id, ym),
    KEY idx_weather_ym (ym),
    CONSTRAINT fk_weather_area FOREIGN KEY (area_id)
        REFERENCES PRODUCTION_AREA (area_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_weather_month CHECK (
        DAYOFMONTH(ym) = 1 AND month BETWEEN 1 AND 12
        AND year = YEAR(ym) AND month = MONTH(ym)
    ),
    CONSTRAINT ck_weather_temp CHECK (
        min_temp <= max_temp AND min_temp <= avg_temp AND avg_temp <= max_temp
    ),
    CONSTRAINT ck_weather_rain CHECK (total_rain >= 0),
    CONSTRAINT ck_weather_drought CHECK (drought_level BETWEEN 0 AND 3),
    CONSTRAINT ck_weather_days CHECK (
        heatwave_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
        AND frost_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
        AND heavy_rain_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
        AND rainy_season_days BETWEEN 0 AND DAYOFMONTH(LAST_DAY(ym))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE RAINY_SEASON (
    rainy_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    area_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    season_length_days TINYINT UNSIGNED NOT NULL,
    rain_days TINYINT UNSIGNED NULL,
    total_rain DECIMAL(10,2) NULL,
    PRIMARY KEY (rainy_id),
    UNIQUE KEY uq_rainy_year (area_id, year),
    CONSTRAINT fk_rainy_area FOREIGN KEY (area_id)
        REFERENCES PRODUCTION_AREA (area_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_rainy_dates CHECK (
        end_date >= start_date AND year = YEAR(start_date) AND year = YEAR(end_date)
        AND season_length_days = DATEDIFF(end_date, start_date) + 1
    ),
    CONSTRAINT ck_rainy_days CHECK (rain_days BETWEEN 0 AND season_length_days),
    CONSTRAINT ck_rainy_rain CHECK (total_rain >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 날씨가 아직 없어도 가격은 조회되며 기상 컬럼은 NULL입니다.
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

-- 로그인용 회원 테이블 (작성: 유채은)
CREATE TABLE APP_USER (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'PHP password_hash() 결과',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

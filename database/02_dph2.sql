-- DPH2 schema: hourly agent reporting (HOUR grain).
-- Inferred from Reports/DPH2/*.php. DEPTKEY kept for cleanup/report filters
-- even though dailyDPH_import.php does not populate it.
-- Apply after 00_create_databases.sql: mysql -u root -p < database/02_dph2.sql

USE `DPH2`;

CREATE TABLE IF NOT EXISTS `DAILY` (
  `AGENT` int unsigned NOT NULL,
  `AGENT_NAME` varchar(64) NOT NULL DEFAULT '',
  `CAMPAIGN_ID` varchar(32) NOT NULL DEFAULT '',
  `DEPTKEY` varchar(16) DEFAULT NULL,
  `NUM_SALES` int unsigned NOT NULL DEFAULT 0,
  `TOTAL_AMOUNT` decimal(12,2) NOT NULL DEFAULT 0.00,
  `FINAL_DISPOS` int unsigned NOT NULL DEFAULT 0,
  `TOTAL_HOURS` decimal(12,2) NOT NULL DEFAULT 0.00,
  `TOTAL_CALLS` int unsigned NOT NULL DEFAULT 0,
  `RRPM` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `WRAP` int unsigned NOT NULL DEFAULT 0,
  `TALK` int unsigned NOT NULL DEFAULT 0,
  `WAIT` int unsigned NOT NULL DEFAULT 0,
  `CCs` int unsigned NOT NULL DEFAULT 0,
  `CC_AMT` decimal(12,2) NOT NULL DEFAULT 0.00,
  `AGENT_TYPE` tinyint unsigned NOT NULL DEFAULT 1,
  `PARENT` int unsigned NOT NULL DEFAULT 0,
  `LIST_ID` int unsigned NOT NULL DEFAULT 0,
  `LIST_NAME` varchar(255) NOT NULL DEFAULT '',
  `DATE` date NOT NULL,
  `TYPE` varchar(16) NOT NULL DEFAULT '',
  `XFERS` int unsigned NOT NULL DEFAULT 0,
  `HOUR` tinyint unsigned NOT NULL,
  `DATE_TIME` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_AGENT_CAMPAIGN_ID_LIST_ID_DATE_TYPE_HOUR` (
    `AGENT`, `CAMPAIGN_ID`, `LIST_ID`, `DATE`, `TYPE`, `AGENT_TYPE`, `HOUR`
  ),
  KEY `idx_DATE` (`DATE`),
  KEY `idx_DATE_campaign` (`DATE`, `CAMPAIGN_ID`),
  KEY `idx_date_agent` (`DATE`, `AGENT`),
  KEY `idx_hour` (`DATE`, `HOUR`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `DAILY_INSERT` LIKE `DAILY`;

CREATE TABLE IF NOT EXISTS `ARCHIVE` LIKE `DAILY`;

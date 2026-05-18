-- VFR schema: verifier/fronter closer reporting.
-- Inferred from Reports/VFR/*.php (subset of DPH metrics).
-- Apply after 00_create_databases.sql: mysql -u root -p < database/03_vfr.sql

USE `VFR`;

CREATE TABLE IF NOT EXISTS `DAILY` (
  `AGENT` int unsigned NOT NULL,
  `CAMPAIGN_ID` varchar(32) NOT NULL DEFAULT '',
  `DEPTKEY` varchar(16) NOT NULL DEFAULT '',
  `NUM_SALES` int unsigned NOT NULL DEFAULT 0,
  `TOTAL_AMOUNT` decimal(12,2) NOT NULL DEFAULT 0.00,
  `TOTAL_CALLS` int unsigned NOT NULL DEFAULT 0,
  `CCs` int unsigned NOT NULL DEFAULT 0,
  `CC_AMT` decimal(12,2) NOT NULL DEFAULT 0.00,
  `AGENT_TYPE` tinyint unsigned NOT NULL DEFAULT 1,
  `LIST_ID` int unsigned NOT NULL DEFAULT 0,
  `LIST_NAME` varchar(255) NOT NULL DEFAULT '',
  `DATE` date NOT NULL,
  `TYPE` varchar(16) NOT NULL DEFAULT '',
  `DATE_TIME` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_AGENT_CAMPAIGN_ID_DEPTKEY_DATE_LIST_ID_TYPE` (
    `AGENT`, `CAMPAIGN_ID`, `DEPTKEY`, `DATE`, `LIST_ID`, `TYPE`
  ),
  KEY `idx_DATE` (`DATE`),
  KEY `idx_DATE_campaign` (`DATE`, `CAMPAIGN_ID`),
  KEY `idx_date_agent` (`DATE`, `AGENT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `DAILY_INSERT` LIKE `DAILY`;

CREATE TABLE IF NOT EXISTS `ARCHIVE` LIKE `DAILY`;

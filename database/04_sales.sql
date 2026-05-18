-- Sales schema: imported sales, Roust records, DNC list, campaign reference.
-- Inferred from Sales/*.php and Admin/addDNC.php.
-- Apply after 00_create_databases.sql: mysql -u root -p < database/04_sales.sql

USE `Sales`;

CREATE TABLE IF NOT EXISTS `Sales` (
  `LEAD_ID` int unsigned NOT NULL,
  `START_TIME` datetime DEFAULT NULL,
  `START_DATE` date DEFAULT NULL,
  `PHONE` bigint unsigned DEFAULT NULL,
  `AGENT` int DEFAULT NULL,
  `AGENT_NAME` varchar(64) NOT NULL DEFAULT '',
  `LAST_NAME` varchar(64) NOT NULL DEFAULT '',
  `FIRST_NAME` varchar(64) NOT NULL DEFAULT '',
  `ADDRESS` varchar(255) NOT NULL DEFAULT '',
  `CITY` varchar(64) NOT NULL DEFAULT '',
  `STATE` varchar(16) NOT NULL DEFAULT '',
  `ZIP` varchar(16) NOT NULL DEFAULT '',
  `CAMPAIGN` varchar(64) NOT NULL DEFAULT '',
  `AMOUNT` decimal(12,2) NOT NULL DEFAULT 0.00,
  `VERIFIER` int DEFAULT NULL,
  `DEPTKEY` varchar(8) NOT NULL DEFAULT '',
  `TYPE` varchar(32) NOT NULL DEFAULT '',
  `DISPOSITION` varchar(32) NOT NULL DEFAULT '',
  `CC` varchar(16) NOT NULL DEFAULT '',
  `INVOICE` varchar(64) NOT NULL DEFAULT '',
  `EMAIL` varchar(255) NOT NULL DEFAULT '',
  `OCCUPATION` varchar(128) NOT NULL DEFAULT '',
  `EMPLOYER` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`LEAD_ID`),
  KEY `idx_start_date_campaign` (`START_DATE`, `CAMPAIGN`),
  KEY `idx_start_date` (`START_DATE`),
  KEY `idx_phone` (`PHONE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Roust` (
  `Invoice` int NOT NULL,
  `Amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Note` varchar(64) NOT NULL DEFAULT '',
  `Key` char(1) NOT NULL DEFAULT '',
  `Date` date DEFAULT NULL,
  `Rep` int DEFAULT NULL,
  `Camp` varchar(64) NOT NULL DEFAULT '',
  `Phone` bigint unsigned DEFAULT NULL,
  `Status` varchar(32) NOT NULL DEFAULT '',
  `Submitted` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`Invoice`),
  KEY `idx_submitted` (`Submitted`),
  KEY `idx_date` (`Date`),
  KEY `idx_phone` (`Phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `DNCs` (
  `Number` bigint unsigned NOT NULL,
  `CampType` varchar(32) NOT NULL DEFAULT '',
  `DateAdded` date NOT NULL,
  UNIQUE KEY `idx_number_camptype` (`Number`, `CampType`),
  KEY `idx_date_added` (`DateAdded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CAMPAIGNS` (
  `CODE` varchar(32) NOT NULL,
  `NAMS` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

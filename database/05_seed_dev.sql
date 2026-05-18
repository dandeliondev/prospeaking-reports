-- Optional dev seed aligned with config/mock_data.php.
-- Apply after 01_dph.sql: mysql -u root -p < database/05_seed_dev.sql

USE `DPH`;

INSERT INTO `DAILY` (
  `AGENT`, `AGENT_NAME`, `CAMPAIGN_ID`, `DEPTKEY`,
  `NUM_SALES`, `TOTAL_AMOUNT`, `FINAL_DISPOS`, `TOTAL_HOURS`, `TOTAL_CALLS`,
  `RRPM`, `WRAP`, `TALK`, `WAIT`, `CCs`, `CC_AMT`,
  `AGENT_TYPE`, `PARENT`, `LIST_ID`, `LIST_NAME`, `DATE`, `TYPE`, `XFERS`
) VALUES
  (
    4001, 'Jane Demo', 'BCSUPF', 'CD',
    3, 105.00, 40, 4.50, 120,
    0.0156, 3600, 21600, 5400, 1, 35.00,
    1, 4001, 1038, 'Demo List A', CURDATE(), 'C', 12
  ),
  (
    4002, 'John Sample', 'NCDVPAC', 'CD',
    2, 70.00, 25, 3.00, 80,
    0.0139, 2000, 12000, 3200, 0, 0.00,
    1, 4002, 1039, 'Demo List B', CURDATE(), 'C', 8
  ),
  (
    4003, 'Alex Test', 'CCCPAC', 'CD',
    5, 200.00, 60, 6.00, 200,
    0.0167, 7000, 40000, 10000, 2, 80.00,
    2, 4003, 2001, 'Demo List C', CURDATE(), 'R', 20
  )
ON DUPLICATE KEY UPDATE
  `AGENT_NAME` = VALUES(`AGENT_NAME`),
  `NUM_SALES` = VALUES(`NUM_SALES`),
  `TOTAL_AMOUNT` = VALUES(`TOTAL_AMOUNT`),
  `FINAL_DISPOS` = VALUES(`FINAL_DISPOS`),
  `TOTAL_HOURS` = VALUES(`TOTAL_HOURS`),
  `TOTAL_CALLS` = VALUES(`TOTAL_CALLS`),
  `RRPM` = VALUES(`RRPM`),
  `WRAP` = VALUES(`WRAP`),
  `TALK` = VALUES(`TALK`),
  `WAIT` = VALUES(`WAIT`),
  `CCs` = VALUES(`CCs`),
  `CC_AMT` = VALUES(`CC_AMT`),
  `LIST_NAME` = VALUES(`LIST_NAME`),
  `XFERS` = VALUES(`XFERS`),
  `DATE_TIME` = CURRENT_TIMESTAMP;

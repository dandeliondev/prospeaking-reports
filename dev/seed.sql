-- Local dummy data for ProSpeaking report UI (DPH + minimal Vicidial stubs)

CREATE DATABASE IF NOT EXISTS DPH;
USE DPH;

CREATE TABLE IF NOT EXISTS DAILY (
    AGENT         VARCHAR(20)  NOT NULL,
    AGENT_NAME    VARCHAR(100) NOT NULL DEFAULT '',
    CAMPAIGN_ID   VARCHAR(20)  NOT NULL,
    DEPTKEY       VARCHAR(10)  NOT NULL DEFAULT 'CD',
    NUM_SALES     INT          NOT NULL DEFAULT 0,
    TOTAL_AMOUNT  DECIMAL(12,2) NOT NULL DEFAULT 0,
    FINAL_DISPOS  INT          NOT NULL DEFAULT 0,
    TOTAL_HOURS   DECIMAL(10,4) NOT NULL DEFAULT 0,
    TOTAL_CALLS   INT          NOT NULL DEFAULT 0,
    RRPM          DECIMAL(10,4) NOT NULL DEFAULT 0,
    WRAP          DECIMAL(12,2) NOT NULL DEFAULT 0,
    TALK          DECIMAL(12,2) NOT NULL DEFAULT 0,
    WAIT          DECIMAL(12,2) NOT NULL DEFAULT 0,
    CCs           INT          NOT NULL DEFAULT 0,
    CC_AMT        DECIMAL(12,2) NOT NULL DEFAULT 0,
    AGENT_TYPE    TINYINT      NOT NULL DEFAULT 1,
    PARENT        VARCHAR(20)  NOT NULL DEFAULT '',
    LIST_ID       INT          NOT NULL,
    LIST_NAME     VARCHAR(100) NOT NULL DEFAULT '',
    DATE          DATE         NOT NULL,
    TYPE          CHAR(1)      NOT NULL DEFAULT 'C',
    XFERS         INT          NOT NULL DEFAULT 0,
    DATE_TIME     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (AGENT, CAMPAIGN_ID, DEPTKEY, DATE, LIST_ID, TYPE)
);

CREATE TABLE IF NOT EXISTS ARCHIVE LIKE DAILY;

TRUNCATE TABLE DAILY;

INSERT INTO DAILY (
    AGENT, AGENT_NAME, CAMPAIGN_ID, DEPTKEY,
    NUM_SALES, TOTAL_AMOUNT, FINAL_DISPOS, TOTAL_HOURS, TOTAL_CALLS,
    RRPM, WRAP, TALK, WAIT, CCs, CC_AMT,
    AGENT_TYPE, PARENT, LIST_ID, LIST_NAME, DATE, TYPE, XFERS, DATE_TIME
) VALUES
('4001', 'Jane Demo',   'BCSUPF',  'CD', 3, 105.00, 40, 4.50, 120, 0.15, 3600, 21600, 5400, 1, 35.00, 1, '4001', 1038, 'Demo List A', CURDATE(), 'C', 12, NOW()),
('4002', 'John Sample', 'NCDVPAC', 'CD', 2,  70.00, 25, 3.00,  80, 0.12, 2000, 12000, 3200, 0,  0.00, 1, '4002', 1039, 'Demo List B', CURDATE(), 'C',  8, NOW()),
('4003', 'Alex Test',   'CCCPAC',  'CD', 5, 200.00, 60, 6.00, 200, 0.18, 7000, 40000, 10000, 2, 80.00, 2, '4003', 2001, 'Demo List C', CURDATE(), 'R', 20, NOW()),
('4004', 'Sam Fronter', 'UNVETPAC','CD', 1,  45.00, 15, 2.25,  55, 0.10, 1375,  8250,  2200, 0,  0.00, 1, '4004', 1040, 'Demo List D', CURDATE(), 'C',  5, NOW()),
('4005', 'Pat Closer',  'CPFPAC',  'CD', 4, 160.00, 50, 5.50, 175, 0.16, 5500, 33000,  8250, 1, 55.00, 1, '4005', 1041, 'Demo List E', CURDATE(), 'C', 18, NOW());

-- Empty Vicidial tables so checkSalesNoAmt.php does not error
CREATE DATABASE IF NOT EXISTS vicidial;
USE vicidial;

CREATE TABLE IF NOT EXISTS vicidial_list (
    lead_id              INT PRIMARY KEY,
    status               VARCHAR(20) DEFAULT '',
    phone_number         VARCHAR(20) DEFAULT '',
    vendor_lead_code     VARCHAR(64) DEFAULT '',
    comments             VARCHAR(255) DEFAULT '',
    last_local_call_time DATETIME NULL,
    address3             VARCHAR(64) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS vicidial_agent_log (
    lead_id    INT NOT NULL,
    event_time DATETIME NULL,
    user       VARCHAR(20) DEFAULT '',
    KEY idx_lead (lead_id)
);

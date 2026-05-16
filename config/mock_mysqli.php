<?php

require_once __DIR__ . '/mock_data.php';

class DevMockMysqli
{
    public ?string $connect_error = null;
    private string $database = '';

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function select_db(string $database): bool
    {
        $this->database = $database;
        return true;
    }

    public function query(string $sql)
    {
        $sqlNorm = preg_replace('/\s+/', ' ', trim($sql));

        if (stripos($sqlNorm, 'from vicidial_list') !== false) {
            return new DevMockResult([]);
        }

        if (preg_match('/select\s+max\s*\(\s*DATE_TIME\s*\)/i', $sqlNorm)) {
            return new DevMockResult([['max(DATE_TIME)' => date('Y-m-d H:i:s')]]);
        }

        if (preg_match('/count\s*\(\s*distinct/i', $sqlNorm)) {
            return new DevMockResult([['count' => count(prospeaking_mock_daily_rows())]]);
        }

        if (preg_match('/select\s+distinct\s*\(\s*AGENT_TYPE\s*\)/i', $sqlNorm)) {
            return new DevMockResult([['AGENT_TYPE' => 1], ['AGENT_TYPE' => 2]]);
        }

        if (preg_match('/select\s+distinct\s*\(\s*DEPTKEY\s*\)/i', $sqlNorm)) {
            return new DevMockResult([['DEPTKEY' => 'CD']]);
        }

        if (preg_match('/select\s+distinct\s*\(\s*LIST_ID\s*\)/i', $sqlNorm)) {
            $rows = [];
            foreach (prospeaking_mock_daily_rows() as $r) {
                $rows[] = ['LIST_ID' => $r['LIST_ID'], 'LIST_NAME' => $r['LIST_NAME'], 'AGENT_TYPE' => $r['AGENT_TYPE']];
            }
            return new DevMockResult($rows);
        }

        if (preg_match('/select\s+distinct\s*\(\s*CAMPAIGN_ID\s*\)/i', $sqlNorm)) {
            $rows = [];
            foreach (prospeaking_mock_daily_rows() as $r) {
                $rows[] = ['CAMPAIGN_ID' => $r['CAMPAIGN_ID']];
            }
            return new DevMockResult($rows);
        }

        if (preg_match('/select\s+distinct\s*\(\s*TYPE\s*\)/i', $sqlNorm)) {
            return new DevMockResult([['TYPE' => 'C'], ['TYPE' => 'R']]);
        }

        if (preg_match('/select\s+distinct\s*\(\s*AGENT\s*\)/i', $sqlNorm)) {
            $rows = [];
            foreach (prospeaking_mock_daily_rows() as $r) {
                $rows[] = ['AGENT' => $r['AGENT'], 'AGENT_NAME' => $r['AGENT_NAME']];
            }
            return new DevMockResult($rows);
        }

        if (stripos($sqlNorm, 'GROUP BY') !== false && stripos($sqlNorm, 'SUM(NUM_SALES)') !== false) {
            $groupKey = 'AGENT';
            if (stripos($sqlNorm, 'GROUP BY DATE') !== false) {
                $groupKey = 'DATE';
            } elseif (stripos($sqlNorm, 'GROUP BY CAMPAIGN_ID') !== false) {
                $groupKey = 'CAMPAIGN_ID';
            }

            $reportRows = [];
            foreach (prospeaking_mock_daily_rows() as $row) {
                $reportRows[] = prospeaking_mock_report_row($row, $groupKey);
            }
            return new DevMockResult($reportRows);
        }

        if (stripos($sqlNorm, 'SUM(NUM_SALES)') !== false) {
            $reportRows = array_map(
                static fn(array $row) => prospeaking_mock_report_row($row, 'AGENT'),
                prospeaking_mock_daily_rows()
            );
            $totals = prospeaking_mock_aggregate_totals($reportRows);
            $n = count($reportRows);

            if (stripos($sqlNorm, "/ $n as NUM_SALES") !== false || preg_match('#/ ' . $n . ' as#', $sqlNorm)) {
                $avg = [];
                foreach ($totals as $k => $v) {
                    $avg[$k] = is_numeric($v) ? $v / $n : $v;
                }
                $avg['CONV_RATE'] = $totals['CONV_RATE'];
                return new DevMockResult([$avg]);
            }

            return new DevMockResult([$totals]);
        }

        return new DevMockResult([]);
    }
}

class DevMockResult implements IteratorAggregate
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function fetch_assoc(): ?array
    {
        static $indexes = [];
        $id = spl_object_id($this);
        $i = $indexes[$id] ?? 0;
        if (!isset($this->rows[$i])) {
            return null;
        }
        $indexes[$id] = $i + 1;
        return $this->rows[$i];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }
}

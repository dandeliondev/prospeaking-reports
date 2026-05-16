<?php

/** @return list<array<string, mixed>> */
function prospeaking_mock_daily_rows(): array
{
    return [
        [
            'AGENT' => '4001', 'AGENT_NAME' => 'Jane Demo', 'CAMPAIGN_ID' => 'BCSUPF',
            'DEPTKEY' => 'CD', 'NUM_SALES' => 3, 'TOTAL_AMOUNT' => 105.0, 'FINAL_DISPOS' => 40,
            'TOTAL_HOURS' => 4.5, 'TOTAL_CALLS' => 120, 'XFERS' => 12, 'CCs' => 1, 'CC_AMT' => 35.0,
            'AGENT_TYPE' => 1, 'PARENT' => '4001', 'LIST_ID' => 1038, 'LIST_NAME' => 'Demo List A',
            'TYPE' => 'C', 'WRAP' => 3600, 'TALK' => 21600, 'WAIT' => 5400,
        ],
        [
            'AGENT' => '4002', 'AGENT_NAME' => 'John Sample', 'CAMPAIGN_ID' => 'NCDVPAC',
            'DEPTKEY' => 'CD', 'NUM_SALES' => 2, 'TOTAL_AMOUNT' => 70.0, 'FINAL_DISPOS' => 25,
            'TOTAL_HOURS' => 3.0, 'TOTAL_CALLS' => 80, 'XFERS' => 8, 'CCs' => 0, 'CC_AMT' => 0.0,
            'AGENT_TYPE' => 1, 'PARENT' => '4002', 'LIST_ID' => 1039, 'LIST_NAME' => 'Demo List B',
            'TYPE' => 'C', 'WRAP' => 2000, 'TALK' => 12000, 'WAIT' => 3200,
        ],
        [
            'AGENT' => '4003', 'AGENT_NAME' => 'Alex Test', 'CAMPAIGN_ID' => 'CCCPAC',
            'DEPTKEY' => 'CD', 'NUM_SALES' => 5, 'TOTAL_AMOUNT' => 200.0, 'FINAL_DISPOS' => 60,
            'TOTAL_HOURS' => 6.0, 'TOTAL_CALLS' => 200, 'XFERS' => 20, 'CCs' => 2, 'CC_AMT' => 80.0,
            'AGENT_TYPE' => 2, 'PARENT' => '4003', 'LIST_ID' => 2001, 'LIST_NAME' => 'Demo List C',
            'TYPE' => 'R', 'WRAP' => 7000, 'TALK' => 40000, 'WAIT' => 10000,
        ],
    ];
}

/** @param list<array<string, mixed>> $rows */
function prospeaking_mock_report_row(array $row, string $groupKey): array
{
    $hours = (float) $row['TOTAL_HOURS'];
    $calls = (int) $row['TOTAL_CALLS'];
    $sales = (int) $row['NUM_SALES'];
    $amount = (float) $row['TOTAL_AMOUNT'];
    $dispos = (int) $row['FINAL_DISPOS'];
    $xfers = (int) $row['XFERS'];

    $id = match ($groupKey) {
        'DATE' => date('Y-m-d'),
        'CAMPAIGN_ID' => $row['CAMPAIGN_ID'],
        default => $row['AGENT'],
    };

    return [
        'ID' => $id,
        'AGENT_NAME' => $row['AGENT_NAME'],
        'NUM_SALES' => $sales,
        'TOTAL_AMOUNT' => $amount,
        'XFERS' => $xfers,
        'CONV_RATE' => $dispos > 0 ? ($xfers / $dispos) * 100 : 0,
        'DPH' => $hours > 0 ? $amount / $hours : 0,
        'TOTAL_HOURS' => $hours,
        'TOTAL_CALLS' => $calls,
        'AVG_WRAP' => $calls > 0 ? $row['WRAP'] / $calls : 0,
        'AVG_TALK' => $calls > 0 ? $row['TALK'] / $calls : 0,
        'AVG_WAIT' => $calls > 0 ? $row['WAIT'] / $calls : 0,
        'CCs' => (int) $row['CCs'],
        'CC_AMT' => (float) $row['CC_AMT'],
        'CPH' => $hours > 0 ? (float) $row['CC_AMT'] / $hours : 0,
        'AVG_DONATION' => $sales > 0 ? $amount / $sales : 0,
        'RRPM' => $hours > 0 ? $dispos / $hours / 60 : 0,
        'FINAL_DISPOS' => $dispos,
    ];
}

/** @param list<array<string, mixed>> $reportRows */
function prospeaking_mock_aggregate_totals(array $reportRows): array
{
    $sum = static fn(string $k) => array_sum(array_column($reportRows, $k));
    $hours = $sum('TOTAL_HOURS');
    $sales = $sum('NUM_SALES');
    $amount = $sum('TOTAL_AMOUNT');
    $dispos = $sum('FINAL_DISPOS');

    return [
        'NUM_SALES' => $sales,
        'TOTAL_AMOUNT' => $amount,
        'DPH' => $hours > 0 ? $amount / $hours : 0,
        'XFERS' => $sum('XFERS'),
        'TOTAL_HOURS' => $hours,
        'TOTAL_CALLS' => $sum('TOTAL_CALLS'),
        'CCs' => $sum('CCs'),
        'CC_AMT' => $sum('CC_AMT'),
        'AVG_DONATION' => $sales > 0 ? $amount / $sales : 0,
        'RRPM' => $hours > 0 ? $dispos / $hours / 60 : 0,
        'CONV_RATE' => $dispos > 0 ? ($sum('XFERS') / $dispos) * 100 : 0,
        'FINAL_DISPOS' => $dispos,
    ];
}

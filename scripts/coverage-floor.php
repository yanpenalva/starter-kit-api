<?php

declare(strict_types=1);

/*
 * Enforces the documented coverage floor (default 85% lines) over a PHPUnit
 * Clover report. Usage:
 *
 *   php scripts/coverage-floor.php storage/app/coverage.xml 85
 *
 * Exits 0 when covered lines meet the floor, 1 otherwise.
 */

$report = $argv[1] ?? '';
$floor = (float) ($argv[2] ?? 85);

if ($report === '' || ! is_file($report)) {
    fwrite(STDERR, "Coverage report not found: {$report}\n");
    exit(1);
}

$xml = simplexml_load_file($report);

if ($xml === false) {
    fwrite(STDERR, "Unable to parse coverage report: {$report}\n");
    exit(1);
}

$metrics = $xml->xpath('/coverage/project/metrics');

if ($metrics === [] || $metrics === null) {
    fwrite(STDERR, "No project metrics in coverage report: {$report}\n");
    exit(1);
}

$statements = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "Coverage report contains no statements: {$report}\n");
    exit(1);
}

$percentage = $covered / $statements * 100;

printf("Coverage: %.2f%% (%d/%d lines), floor: %.2f%%\n", $percentage, $covered, $statements, $floor);

if ($percentage < $floor) {
    fwrite(STDERR, "Coverage floor not met.\n");
    exit(1);
}

<?php

declare(strict_types=1);

/*
 * Merges two PHPUnit Clover reports into one: union of hits per file and line.
 *
 *   php scripts/merge-coverage.php a.xml b.xml merged.xml
 *
 * Both reports come from the same revision, so lines, files and structural
 * metrics are kept and only coverage is merged. Statements PHPUnit lists only
 * in <metrics> (executable method start lines have no <line> entry) are added
 * back, so the merged denominator never shrinks.
 */

const METRICS = ['loc', 'ncloc', 'classes', 'methods', 'coveredmethods', 'conditionals', 'coveredconditionals',
    'statements', 'coveredstatements', 'elements', 'coveredelements'];

[$first, $second, $output] = array_pad(array_slice($argv, 1, 3), 3, '');

if ($first === '' || $second === '' || $output === '') {
    fwrite(STDERR, "Usage: php scripts/merge-coverage.php a.xml b.xml merged.xml\n");
    exit(1);
}

function loadReport(string $path): DOMDocument
{
    libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $loaded = is_file($path) && $document->load($path);

    if (! $loaded || $document->getElementsByTagName('project')->length !== 1) {
        fwrite(STDERR, "Invalid coverage report: {$path}\n");
        exit(1);
    }

    return $document;
}

function setMetrics(DOMElement $metrics, array $counts): void
{
    foreach ($counts as $name => $count) {
        $metrics->setAttribute($name, (string) $count);
    }
}

/** Recomputes coverage, methods and elements of a merged file and of its single class. */
function refreshFile(DOMElement $file, iterable $lines, DOMXPath $path): void
{
    $metrics = $path->query('metrics', $file)->item(0);
    $counts = ['methods' => 0, 'coveredmethods' => 0, 'statements' => 0, 'coveredstatements' => 0];
    $methodHit = null;
    foreach ($lines as $line) {
        $hit = (int) $line->getAttribute('count') > 0;
        if ($line->getAttribute('type') === 'method') {
            $counts['methods']++;
            $counts['coveredmethods'] += (int) ($methodHit === true);
            $methodHit = $hit;

            continue;
        }
        $counts['statements']++;
        $counts['coveredstatements'] += (int) $hit;
        $methodHit = $methodHit === null ? null : $methodHit && $hit;
    }
    $counts['coveredmethods'] += (int) ($methodHit === true);
    $residual = max(0, (int) $metrics->getAttribute('statements') - $counts['statements']);
    $counts['statements'] += $residual;
    $counts['coveredstatements'] += min($residual, max(0, (int) $metrics->getAttribute('coveredstatements') - $counts['coveredstatements']));
    $counts['elements'] = $counts['methods'] + $counts['statements'] + (int) $metrics->getAttribute('conditionals');
    $counts['coveredelements'] = $counts['coveredmethods'] + $counts['coveredstatements'] + (int) $metrics->getAttribute('coveredconditionals');
    setMetrics($metrics, $counts);

    $classes = $path->query('class', $file);
    if ($classes->length === 1) {
        setMetrics($path->query('metrics', $classes->item(0))->item(0), $counts);
    }
}

$base = loadReport($first);
$incoming = loadReport($second);
$basePath = new DOMXPath($base);
$incomingPath = new DOMXPath($incoming);
$project = $basePath->query('/coverage/project')->item(0);

$packages = [];
$files = [];
foreach ($basePath->query('package', $project) as $package) {
    $packages[$package->getAttribute('name')] = $package;
    foreach ($basePath->query('file', $package) as $file) {
        $files[$file->getAttribute('name')] = $file;
    }
}

foreach ($incomingPath->query('/coverage/project/package') as $package) {
    $packageName = $package->getAttribute('name');
    $packages[$packageName] ??= $project->appendChild($base->createElement('package'));
    $packages[$packageName]->setAttribute('name', $packageName);

    foreach ($incomingPath->query('file', $package) as $file) {
        $target = $files[$file->getAttribute('name')] ??= $packages[$packageName]->appendChild($base->importNode($file, true));
        $lines = [];
        foreach ($basePath->query('line', $target) as $line) {
            $lines[(int) $line->getAttribute('num')] = $line;
        }
        foreach ($incomingPath->query('line', $file) as $line) {
            $num = (int) $line->getAttribute('num');
            $lines[$num] ??= $base->importNode($line, true);
            $lines[$num]->setAttribute('count', (string) max((int) $lines[$num]->getAttribute('count'), (int) $line->getAttribute('count')));
        }
        ksort($lines);
        $anchor = $basePath->query('metrics', $target)->item(0);
        foreach ($lines as $line) {
            $target->insertBefore($line, $anchor);
        }
        refreshFile($target, $lines, $basePath);
    }
}

foreach ($basePath->query('package/metrics', $project) as $metrics) {
    $totals = ['files' => $basePath->query('file', $metrics->parentNode)->length];
    foreach (METRICS as $name) {
        $totals[$name] = (int) $basePath->evaluate("sum(file/metrics/@{$name})", $metrics->parentNode);
    }
    setMetrics($metrics, $totals);
}

$totals = ['files' => $basePath->query('package/file', $project)->length];
foreach (METRICS as $name) {
    $totals[$name] = (int) $basePath->evaluate("sum(package/file/metrics/@{$name})", $project);
}
setMetrics($basePath->query('metrics', $project)->item(0), $totals);

$project->setAttribute('timestamp', (string) time());
$base->documentElement->setAttribute('generated', (string) time());

if ($base->save($output) === false) {
    fwrite(STDERR, "Unable to write merged report: {$output}\n");
    exit(1);
}

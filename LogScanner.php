<?php

$logPath = '/path to your .logs fles folder/';
$outputFile = $logPath . 'extracted-llogs.docx';

$logFiles = glob($logPath . "*log*", GLOB_BRACE);
$totalFiles = count($logFiles);

if ($totalFiles === 0) {
    die("❌ No log files found in: $logPath" . PHP_EOL);
}

echo "🔍 Scanning $totalFiles Magento log files...\n";

$errorStats = [];

foreach ($logFiles as $file) {
    $handle = fopen($file, "r");
    if ($handle) {
        $lineNumber = 0;
        $currentLogHash = null;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            $matched = false;
            $timestamp = '';
            $logChannel = 'N/A';
            $logType = 'ERROR';
            $logMessage = trim($line);

            // ✅ Match Monolog-style logs
            if (preg_match('/^\[(.*?)\]\s+([\w\d_-]+)\.(CRITICAL|ERROR|WARNING|EXCEPTION|DEBUG|NOTICE|EMERGENCY|ALERT|INFO):\s+(.+)$/i', $line, $matches)) {
                $timestamp = $matches[1];
                $logChannel = $matches[2];
                $logType = strtoupper($matches[3]);
                $logMessage = trim($matches[4]);

                // ✅ Detect error keywords
                $isErrorLike = preg_match('/(exception|error|warning|undefined|invalid|stack trace|failed|cannot|not found|missing)/i', $logMessage);
                $looksLikeLargeJson = (
                    stripos($logMessage, 'product magento data:') !== false ||
                    strlen($logMessage) > 1000
                );

                // ❌ Skip noisy info logs
                if ($logType === 'INFO') {
                    if (stripos($logMessage, 'product magento data:') !== false) {
                        continue;
                    }
                    if (!$isErrorLike) {
                        continue;
                    }
                }

                $matched = true;

                if (preg_match('# in (/app/code/[^\s]+\.php)(?::| on line )(\d+)#', $logMessage, $fileMatch)) {
                    $filePath = $fileMatch[1];
                    $lineNum = $fileMatch[2];
                    $checkableFile = $filePath . "($lineNum)";
                }
            }

            // ✅ Match [error], status 400, JSON error
            if (!$matched && (
                stripos($line, '[error]') !== false ||
                stripos($line, '"status": 400') !== false ||
                stripos($line, '"error"') !== false
            )) {
                $timestamp = date('Y-m-d H:i:s');
                $logMessage = trim($line);
                $matched = true;
            }

            // ✅ Match Magento-style Exception
            if (!$matched && preg_match('/^(.*Exception:.*?) in (\/app\/code\/[^\s]+\.php) on line (\d+)/', $line, $matches)) {
                $timestamp = date('Y-m-d H:i:s');
                $logType = 'EXCEPTION';
                $logChannel = 'N/A';
                $logMessage = trim($matches[1]);
                $checkableFile = $matches[2] . '(' . $matches[3] . ')';
                $matched = true;
            }

            if ($matched) {
                $originalMessage = $logMessage;

                // ✅ Clean message before hashing to deduplicate
                $cleanMessage = strtolower($logMessage);
                $cleanMessage = preg_replace([
                    '/\([^)]+\)/',                           // remove (parentheses)
                    '/\[[^]]+\]/',                           // remove [brackets]
                    '/[A-Fa-f0-9]{12,}/',                    // long hashes / uuids
                    '/\b(evt|cus|tok|pi|pm)_[A-Za-z0-9]+/',  // stripe ids
                    '/\d+/',                                 // numbers
                    '/\s+/',                                 // normalize whitespace
                ], ' ', $cleanMessage);
                $cleanMessage = trim($cleanMessage);

                $messageHash = md5($cleanMessage);
                $currentLogHash = $messageHash;

                // ✅ Skip duplicates (only count once per message)
                if (!isset($errorStats[$messageHash])) {
                    $errorStats[$messageHash] = [
                        'message' => $originalMessage,
                        'type' => $logType,
                        'channel' => $logChannel,
                        'timestamps' => [],
                        'files' => [],
                        'count' => 0,
                        'checkable_files' => [],
                    ];
                }

                $errorStats[$messageHash]['count']++;
                $errorStats[$messageHash]['timestamps'][] = $timestamp;
                $errorStats[$messageHash]['files'][] = basename($file);

                if (!empty($checkableFile)) {
                    $errorStats[$messageHash]['checkable_files'][] = $checkableFile;
                }

                // ✅ Extract inline /app/code/File.php(123)
                if (preg_match_all('#(/app/code/[^\s\)]+\.php)\((\d+)\)#', $logMessage, $inlineMatches, PREG_SET_ORDER)) {
                    foreach ($inlineMatches as $match) {
                        $entry = $match[1] . '(' . $match[2] . ')';
                        $errorStats[$messageHash]['checkable_files'][] = $entry;
                    }
                }
            }

            // ✅ Stack traces (from /app/code/)
            if (!$matched && $currentLogHash) {
                if (preg_match_all('#(/app/code/[^\s\)]+\.php)\((\d+)\)#', $line, $traceMatches, PREG_SET_ORDER)) {
                    foreach ($traceMatches as $traceMatch) {
                        $entry = $traceMatch[1] . '(' . $traceMatch[2] . ')';
                        $errorStats[$currentLogHash]['checkable_files'][] = $entry;
                    }
                }
            }

            if ($currentLogHash && isset($errorStats[$currentLogHash])) {
                $errorStats[$currentLogHash]['checkable_files'] = array_unique($errorStats[$currentLogHash]['checkable_files']);
            }

            unset($checkableFile);
        }

        fclose($handle);
    }
}

// ✅ Generate report
$docContent = "Magento Suspected Error Log Report\n\n";
$docContent .= "Total Log Files Scanned: $totalFiles\n";
$docContent .= "Total Unique Errors Extracted: " . count($errorStats) . "\n\n";

foreach ($errorStats as $hash => $error) {
    $uniqueFiles = array_unique($error['files']);
    $docContent .= "----------------------------------------\n";
    $docContent .= "Count: " . $error['count'] . "\n";
    $docContent .= "Appeared In: " . count($uniqueFiles) . " file(s)\n";
    $docContent .= "Files: " . implode(", ", $uniqueFiles) . "\n";
    $docContent .= "Type: " . $error['type'] . "\n";
    $docContent .= "Channel: " . $error['channel'] . "\n";
    $docContent .= "Last Timestamp: " . end($error['timestamps']) . "\n";
    $docContent .= "Message:\n" . $error['message'] . "\n";
    if (!empty($error['checkable_files'])) {
        $docContent .= "Checkable Files (from /app/code/ only):\n";
        foreach ($error['checkable_files'] as $cf) {
            $docContent .= "  - " . $cf . "\n";
        }
    }
    $docContent .= "----------------------------------------\n\n";
}

file_put_contents($outputFile, $docContent);
echo "✅ Final Magento error log report saved to: $outputFile\n";

<?php
// 2024-09-30, v1.1
// github.com/aaviator42
// fileTransport/fileFetcher.php
// license: AGPLv3

header("Content-Type: text/plain");

// Run this script indefinitely
ini_set('max_execution_time', 0);

// Configurable parameters
$maxFilesToFetch = 50;  		    // Set how many files to fetch before stopping (set to 0 for unlimited)
$maxSimultaneousDownloads = 10;     // Number of files to download simultaneously
$skipIfExists = false;  		    // Toggle to skip downloading files if they already exist in the destination
$waitTimeBetweenDownloads = 50000;  // Wait time between downloads in microseconds (default: 500000 = 500ms)
$sslBypass = false; 			    // Toggle SSL verification bypass (true to bypass, false to verify)

// Read the list of missing files from missingFiles.json
$missingFiles = json_decode(file_get_contents('missingFiles.json'), true);

// Define base URLs
$baseURL = 'https://example.com/my_docs/';
$saveDir = 'my_docs_new/';
$logFile = 'fileFetcherLog.json';

// ----

// Ensure the destination directory exists
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

// Initialize the log
$logData = [
    'success' => [],
    'failure' => [],
    'total' => count($missingFiles),
];

// Function to download multiple files
function downloadFiles($files, $baseURL, $saveDir, $maxSimultaneousDownloads = 5, $maxFilesToFetch = 0, $skipIfExists = true, $waitTimeBetweenDownloads = 500000, $sslBypass = false, &$logData) {
    $multiCurl = curl_multi_init();
    $curlHandles = [];
    $filesDownloaded = 0;

    foreach ($files as $i => $fileName) {
        // Stop if the maximum number of files to fetch is reached
        if ($maxFilesToFetch > 0 && $filesDownloaded >= $maxFilesToFetch) {
            break;
        }

        $url = $baseURL . rawurlencode($fileName);
        $savePath = $saveDir . basename($fileName);

        // Check if the file already exists and skip if toggle is on
        if ($skipIfExists && file_exists($savePath) && filesize($savePath) > 0) {
            $logData['success'][] = $fileName;
            echo "Skipped (already exists): $fileName\n";
            continue;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');

        // Conditionally bypass SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$sslBypass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, !$sslBypass);

        $curlHandles[$fileName] = $ch;
        curl_multi_add_handle($multiCurl, $ch);

        $filesDownloaded++;

        // If we've hit the maximum simultaneous downloads, execute them
        if (count($curlHandles) >= $maxSimultaneousDownloads || $filesDownloaded >= $maxFilesToFetch) {
            executeMultiCurl($multiCurl, $curlHandles, $saveDir, $logData, $waitTimeBetweenDownloads);
        }
    }

    // Execute any remaining downloads
    if (count($curlHandles) > 0) {
        executeMultiCurl($multiCurl, $curlHandles, $saveDir, $logData, $waitTimeBetweenDownloads);
    }

    curl_multi_close($multiCurl);
}

function executeMultiCurl($multiCurl, &$curlHandles, $saveDir, &$logData, $waitTimeBetweenDownloads) {
    do {
        $status = curl_multi_exec($multiCurl, $active);
        curl_multi_select($multiCurl);
    } while ($active && $status == CURLM_OK);

    foreach ($curlHandles as $fileName => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $savePath = $saveDir . basename($fileName);

        if (curl_errno($ch)) {
            // Log curl errors
            $logData['failure'][] = $fileName;
            echo "Curl error for $fileName: " . curl_error($ch) . "\n";
        } elseif ($httpCode != 200) {
            // Log HTTP error
            $logData['failure'][] = $fileName;
            echo "HTTP error for $fileName: $httpCode\n";
        } elseif (strlen($response) > 0) {
            // Save the file if response is valid
            file_put_contents($savePath, $response);
            echo "Downloaded successfully: $fileName\n";
            $logData['success'][] = $fileName;
        } else {
            $logData['failure'][] = $fileName;
            echo "Downloaded file is 0 bytes or empty: $fileName\n";
        }

        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);

        // Sleep between batches of downloads
        usleep($waitTimeBetweenDownloads);
    }

    $curlHandles = []; // Reset curl handles after each batch
}

// Function to log to JSON file
function logToFile($logFile, $logData) {
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}

// Capture start time
$startTime = microtime(true);

// Run the download process
downloadFiles($missingFiles, $baseURL, $saveDir, $maxSimultaneousDownloads, $maxFilesToFetch, $skipIfExists, $waitTimeBetweenDownloads, $sslBypass, $logData);

// Capture end time
$endTime = microtime(true);

// Calculate the elapsed time
$elapsedTime = $endTime - $startTime;

// Print final report
$successCount = count($logData['success']);
$failureCount = count($logData['failure']);
$logData['success_count'] = $successCount;
$logData['failure_count'] = $failureCount;

echo "\nDownload Summary:\n";
echo "Total Files: " . $logData['total'] . "\n";
echo "Total Successes: $successCount\n";
echo "Total Failures: $failureCount\n";

if ($failureCount > 0) {
    echo "Failed Files:\n" . implode("\n", $logData['failure']) . "\n";
}

// Log final details to the log file
logToFile($logFile, $logData);

echo "Log written to $logFile\n";

// Print how long the download took
echo "Time taken to download files: " . round($elapsedTime, 2) . " seconds\n";
echo PHP_EOL;

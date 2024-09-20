<?php
// 2024-09-20, v1.0
// github.com/aaviator42
// fileTransport/fileFetcher.php
// license: AGPLv3

// Configurable parameters
$maxFilesToFetch = 1000; // Set how many files to fetch before stopping (set to 0 for unlimited)
$skipIfExists = true;  // Toggle to skip downloading files if they already exist in the destination

$missingFiles = json_decode(file_get_contents('missingFiles.json'), true); // Files to download

$baseURL = 'https://example.com/my_docs/'; // Base URL to folder on source server
$saveDir = 'my_docs/'; // Folder to save files to on destination server

$simultaneousFiles = 5; // Number of files to download simultaneously
$logFile = 'fileFetcherLog.json'; // Transfer log file

// ----
header("Content-Type: text/plain");

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

$logData = [
    'success' => [],
    'failure' => [],
    'total' => count($missingFiles),
];

function downloadFiles($files, $baseURL, $saveDir, $maxSimultaneousDownloads = 5, $maxFilesToFetch = 0, $skipIfExists = true, &$logData) {
    $multiCurl = curl_multi_init();
    $curlHandles = [];
    $filesDownloaded = 0;

    foreach ($files as $i => $filePath) {
        if ($maxFilesToFetch > 0 && $filesDownloaded >= $maxFilesToFetch) {
            break;
        }

        $url = $baseURL . $filePath;
        $savePath = $saveDir . $filePath;  // Preserve subdirectory structure

        // Create the necessary directories in the destination
        if (!is_dir(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }

        if ($skipIfExists && file_exists($savePath)) {
            $logData['success'][] = $filePath;
            echo "Skipped (already exists): $filePath\n";
            continue;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); // Fail on HTTP errors

        $fp = fopen($savePath, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        $curlHash = spl_object_hash($ch);
        $curlHandles[$curlHash] = [
            'ch' => $ch,
            'fileName' => $filePath,
            'filePointer' => $fp,
            'savePath' => $savePath
        ];

        curl_multi_add_handle($multiCurl, $ch);

        if (($i + 1) % $maxSimultaneousDownloads === 0 || $i === count($files) - 1) {
            $filesDownloaded += executeMultiCurl($multiCurl, $curlHandles, $logData);
        }
    }

    curl_multi_close($multiCurl);
}

function executeMultiCurl($multiCurl, &$curlHandles, &$logData) {
    $filesDownloaded = 0;

    do {
        $status = curl_multi_exec($multiCurl, $active);
        if ($active) {
            curl_multi_select($multiCurl);
        }
    } while ($active && $status == CURLM_OK);

    foreach ($curlHandles as $hash => $data) {
        $ch = $data['ch'];
        $fileName = $data['fileName'];
        $fp = $data['filePointer'];
        $savePath = $data['savePath'];

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP status code

        // Handle success
        if (curl_errno($ch) === 0 && $httpCode == 200) {  
            $logData['success'][] = $fileName;
            echo "Downloaded successfully: $fileName\n";
        } else {  
            // Handle errors: log and delete null/incomplete files
            $logData['failure'][] = $fileName;
            echo "Failed to download: $fileName (HTTP Code: $httpCode)\n";
            
            if (is_resource($fp)) {
                fclose($fp); // Close the file pointer
            }
            
            // Ensure the file does not persist if download failed
            if (file_exists($savePath)) {
                unlink($savePath);  // Delete the incomplete or null file
            }
        }

        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);
        
        if (is_resource($fp)) {
            fclose($fp);
        }

        $filesDownloaded++;
    }

    $curlHandles = [];
    return $filesDownloaded;
}

function logToFile($logFile, $logData) {
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}

downloadFiles($missingFiles, $baseURL, $saveDir, $simultaneousFiles, $maxFilesToFetch, $skipIfExists, $logData);

$successCount = count($logData['success']);
$failureCount = count($logData['failure']);
echo "\nDownload Summary:\n";
echo "Total Files Attempted: " . $logData['total'] . "\n";
echo "Total Successes: $successCount\n";
echo "Total Failures: $failureCount\n";

if ($failureCount > 0) {
    echo "Failed Files:\n" . implode("\n", $logData['failure']) . "\n";
}

logToFile($logFile, $logData);

echo "Log written to $logFile\n";

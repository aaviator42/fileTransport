<?php
// 2024-09-20, v1.0
// github.com/aaviator42
// fileTransport/destinationFiles.php
// license: AGPLv3


// Configurable parameters
$folderPath = 'my_docs/';	// Your folder path
$sourceFiles = 'https://example.com/sourceFiles.php';	// Source JSON url/file
$newOutputJson = 'newFiles.json';			// files that exist on destination but not source
$existingOutputJson = 'existingFiles.json'; // files that exist on both destination and source
$missingOutputJson = 'missingFiles.json';	// files that exist on source but not destination

// ----
header("Content-Type: text/plain");

listFiles($folderPath, $sourceFiles, $newOutputJson, $existingOutputJson, $missingOutputJson);

function listFiles($folderPath, $sourceFiles, $newOutputJson, $existingOutputJson, $missingOutputJson) {
    $originalFiles = json_decode(file_get_contents($sourceFiles), true);

    if (!is_array($originalFiles)) {
        $originalFiles = [];
    }

    $newFiles = array();
    $existingFiles = array();
    $missingFiles = array();

    // Recursive iterator to scan directories and subdirectories
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));
    $currentFiles = array();

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $currentFiles[] = $item->getPathname(); // Track current files with path

            // Check if the file is already in the original list
            $relativePath = str_replace($folderPath, '', $item->getPathname());
            if (in_array($relativePath, $originalFiles)) {
                $existingFiles[] = $relativePath;
            } else {
                $newFiles[] = $relativePath;
            }
        }
    }

    foreach ($originalFiles as $originalFile) {
        if (!in_array($originalFile, $currentFiles)) {
            $missingFiles[] = $originalFile;
        }
    }

    file_put_contents($newOutputJson, json_encode($newFiles, JSON_PRETTY_PRINT));
    file_put_contents($existingOutputJson, json_encode($existingFiles, JSON_PRETTY_PRINT));
    file_put_contents($missingOutputJson, json_encode($missingFiles, JSON_PRETTY_PRINT));

    echo "List of new files written to $newOutputJson\n";
    echo "List of existing files written to $existingOutputJson\n";
    echo "List of missing files written to $missingOutputJson\n";
}

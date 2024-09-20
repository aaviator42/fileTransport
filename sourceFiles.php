<?php
// 2024-09-20, v1.0
// github.com/aaviator42
// fileTransport/sourceFiles.php
// license: AGPLv3


// Configurable parameters
$folderPath = 'my_docs/';  // change this to your folder path

// ----
header("Content-Type: application/json");

function listFiles($folderPath) {
    $files = array();

    // Scan directory for files
    $items = scandir($folderPath);

    // Filter files only, skip directories "." and ".."
    foreach ($items as $item) {
        if ($item !== "." && $item !== ".." && is_file($folderPath . DIRECTORY_SEPARATOR . $item)) {
            // Remove the folder path from the item to only show the file name
            $files[] = str_replace($folderPath . DIRECTORY_SEPARATOR, '', $folderPath . DIRECTORY_SEPARATOR . $item);
        }
    }

    // Print JSON output
    echo json_encode($files, JSON_PRETTY_PRINT) . "\n";}

listFiles($folderPath);

# fileTransport
Transfer files from one server to another.  

`v1.0`: `2024-09-20`  
License: `AGPLv3`

## What is this?
Three PHP scripts that allow you to easily migrate files from one server to another.

## Usage
1. Upload `sourceFiles.php` to your source server, and configure `$folderPath` at the top of the file to point to the folder you want to move.
2. Upload `destinationFiles.php` to your destination server, and similarly configure `$folderPath` and `$sourceFiles`. The latter can point to a .json file or directly to the `sourceFiles.php` script on the source server.
3. Upload `fileFetcher.php` to your destination server, and similarly configure `$baseURL` and `$saveDir`.
4. Open `destinationFiles.php` in your browser. It will create a bunch of .json files.
5. Open `fileFetcher.php` in your browser. It will read the json files and download files from your source folder into your destination folder.

## Notes
1. You obviously need to ensure that your destination folder and its contents are accessible via HTTP.
2. In `fileFetcher.php` you can configure the number of files to download simultaneously, as well as the number files to download in a single script run.
3. Once `fileFetcher.php` runs into the `$maxFilesToFetch` threshold, you can simply reload the script to fetch the next `$maxFilesToFetch` files.

## Requirements
1. [Supported versions of PHP](https://www.php.net/supported-versions.php). At the time of writing, that's PHP `8.1+`. `fileTransport` will almost certainly work on older versions, but we don't test it on those, so be careful, do your own testing.


------
Documentation updated: `2024-09-20`

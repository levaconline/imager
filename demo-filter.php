<?php

/**
 * CLI option example for usage script to applay filter to image using the Imager class.
 * Usage: php demo-filter.php <image_path> <image_destination> <filter>
 */

require 'Imager.php';

if ($argc < 4) {
    die("Usage: php demo-filter.php <image_path> <image_destination> <filter>\n");
}

$imagePath = $argv[1];
if (!file_exists($imagePath)) {
    die("File not found: $imagePath\n");
}

$imageDestination = $argv[2];

$filter = (int)$argv[3];

$class = new Imager();

$class->filterImage($imagePath, $imageDestination, $filter);

print_r($class->getMessages());
echo "\n";

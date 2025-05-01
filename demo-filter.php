<?php

/**
 * CLI option example for usage script to applay filter to image using the Imager class.
 * Usage: php demo-filter.php <image_path> <image_destination> <filter> [args]
 */

require 'Imager.php';

if ($argc < 4) {
    die("Usage: php demo-filter.php <image_path> <image_destination> <filter> [args]\n");
}

$imagePath = $argv[1];
if (!file_exists($imagePath)) {
    die("File not found: $imagePath\n");
}

$imageDestination = $argv[2];
if (!isset($imageDestination)) {
    die("Arg 2: imageDestination is obligatory.\n");
}

if (!isset($argv[3])) {
    die("Arg 3 is obligatory and represent number of filter type.\n");
}
$filter = (int)$argv[3];

$args = $argv[4] ?? null;

$class = new Imager();

$class->filterImage($imagePath, $imageDestination, $filter, $args);

print_r($class->getMessages());
echo "\n";

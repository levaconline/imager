<?php

/**
 * CLI option example for usage script to rotate an image using the Imager class.
 * Usage: php demo-rotate.php <image_path> <image_destination> <angle>
 */

require 'Imager.php';

if ($argc < 4) {
    die("Usage: php demo-rotate.php <image_path> <image_destination> <angle>\n");
}

$imagePath = $argv[1];
if (!file_exists($imagePath)) {
    die("File not found: $imagePath\n");
}

$imageDestination = $argv[2];

$angle = (int)$argv[3];

$class = new Imager();

$class->rotateImage($imagePath, $imageDestination, $angle);

print_r($class->getMessages());
echo "\n";

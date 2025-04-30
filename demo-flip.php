<?php 
/**
 * CLI option example for usage script to flip an image using the Imager class.
 * Usage: php demo-flip.php <image_path> <image_destination> <flip_mode>
 */

require 'Imager.php';

if  ($argc < 4) {
    die("Usage: php demo-flip.php <image_path> <image_destination> <flip_mode>\n");
}

$imagePath = $argv[1];
if (!file_exists($imagePath)) {
    die("File not found: $imagePath\n");
}

$imageDestination = $argv[2];

$flipMod = $argv[3];
if (!in_array($flipMod, [0, 1, 2])) {
    die("Invalid flip mode. Use 0 for 'horizontal', 1 for 'vertical'or 2 for 'both'.\n");
}

$class = new Imager();

$class->flipImage($imagePath, $imageDestination, $flipMod);
<?php

require 'vendor/autoload.php';

use SDAVICCO\QRCode\QRCodeGenerator;

$data = "http://davicco.com";
$generatorPNG = new QRCodeGenerator($data, 300, QRCodeGenerator::EC_LEVEL_M, QRCodeGenerator::FORMAT_PNG);
$imagePNG = $generatorPNG->generate();

header('Content-Type: image/png');
echo $imagePNG;

// Generar SVG (comentar las líneas anteriores y descomentar las siguientes para probar)
// $generatorSVG = new QRCodeGenerator($data, 300, QRCodeGenerator::EC_LEVEL_M, QRCodeGenerator::FORMAT_SVG);
// $imageSVG = $generatorSVG->generate();
// header('Content-Type: image/svg+xml');
// echo $imageSVG;
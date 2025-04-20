# QR Code Generator

A simple PHP library to generate QR codes without external dependencies. Supports PNG and SVG output formats with full Reed-Solomon error correction.

## Installation

Install via Composer:

```bash
composer sdavicco/qr-code-generator
```

## Usage

```php
use SDAVICCO\QRCode\QRCodeGenerator;

$data = "https://example.com";
$generator = new QRCodeGenerator($data, 300, QRCodeGenerator::EC_LEVEL_M, QRCodeGenerator::FORMAT_PNG);
$image = $generator->generate();

// Output PNG
header('Content-Type: image/png');
echo $image;

// For SVG, use:
$generator = new QRCodeGenerator($data, 300, QRCodeGenerator::EC_LEVEL_M, QRCodeGenerator::FORMAT_SVG);
$image = $generator->generate();
header('Content-Type: image/svg+xml');
echo $image;
```

## Requirements
- PHP >= 8.1

## License
MIT License
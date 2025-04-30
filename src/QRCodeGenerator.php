<?php

namespace SDAVICCO\QRCode;

class QRCodeGenerator
{
    private $data;
    private $size;
    private $errorCorrection;
    private $matrix;
    private $format;

    const EC_LEVEL_L = 'L'; // 7% de corrección
    const EC_LEVEL_M = 'M'; // 15% de corrección
    const EC_LEVEL_Q = 'Q'; // 25% de corrección
    const EC_LEVEL_H = 'H'; // 30% de corrección

    const FORMAT_PNG = 'png';
    const FORMAT_SVG = 'svg';

    public function __construct(
        string $data,
        int $size = 300,
        string $errorCorrection = self::EC_LEVEL_L,
        string $format = self::FORMAT_PNG
    ) {
        $this->data = $data;
        $this->size = max(100, min($size, 1000));
        $this->errorCorrection = in_array($errorCorrection, [self::EC_LEVEL_L, self::EC_LEVEL_M, self::EC_LEVEL_Q, self::EC_LEVEL_H])
            ? $errorCorrection
            : self::EC_LEVEL_L;
        $this->format = in_array($format, [self::FORMAT_PNG, self::FORMAT_SVG])
            ? $format
            : self::FORMAT_PNG;
    }

    public function generate(): string
    {
        $this->matrix = $this->createMatrix();
        return $this->format === self::FORMAT_SVG ? $this->renderSVG() : $this->renderPNG();
    }

    private function createMatrix(): array
    {
        // Codificar datos
        $binaryData = $this->encodeData($this->data);

        // Determinar versión
        $version = $this->calculateVersion(strlen($binaryData));
        $matrixSize = (4 * $version) + 17;

        // Inicializar matriz
        $matrix = array_fill(0, $matrixSize, array_fill(0, $matrixSize, null));

        // Añadir patrones fijos
        $this->addFinderPatterns($matrix, $matrixSize);
        $this->addTimingPatterns($matrix, $matrixSize);
        $this->addAlignmentPatterns($matrix, $version, $matrixSize);

        // Colocar datos
        $this->placeData($matrix, $binaryData);

        // Añadir corrección de errores
        $ecData = $this->generateErrorCorrection($binaryData, $version);
        $this->placeErrorCorrection($matrix, $ecData);

        // Aplicar máscara
        $this->applyMask($matrix);

        return $matrix;
    }

    private function encodeData(string $data): string
    {
        // Modo alfanumérico (0010)
        $modeIndicator = '0010';
        $length = strlen($data);
        $lengthBinary = str_pad(decbin($length), 9, '0', STR_PAD_LEFT);
        $encodedData = '';

        // Mapa de caracteres alfanuméricos
        $alphanumeric = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        for ($i = 0; $i < $length; $i += 2) {
            if ($i + 1 < $length) {
                $val1 = strpos($alphanumeric, $data[$i]);
                $val2 = strpos($alphanumeric, $data[$i + 1]);
                $value = ($val1 * 45) + $val2;
                $encodedData .= str_pad(decbin($value), 11, '0', STR_PAD_LEFT);
            } else {
                $value = strpos($alphanumeric, $data[$i]);
                $encodedData .= str_pad(decbin($value), 6, '0', STR_PAD_LEFT);
            }
        }

        return $modeIndicator . $lengthBinary . $encodedData;
    }

    private function calculateVersion(int $dataLength): int
    {
        $ecBlocks = [
            self::EC_LEVEL_L => [19, 34, 55, 80, 108],
            self::EC_LEVEL_M => [16, 28, 44, 64, 86],
            self::EC_LEVEL_Q => [13, 22, 34, 48, 62],
            self::EC_LEVEL_H => [9, 16, 26, 36, 46]
        ];

        $versions = [1, 2, 3, 4, 5];
        foreach ($versions as $version) {
            $capacity = $ecBlocks[$this->errorCorrection][$version - 1] * 8;
            if ($dataLength <= $capacity) {
                return $version;
            }
        }

        throw new \Exception('Data too long for supported QR versions');
    }

    private function addFinderPatterns(array &$matrix, int $size): void
    {
        $finder = [
            [1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1]
        ];

        // Superior izquierda, superior derecha, inferior izquierda
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$row, $col]) {
            for ($i = 0; $i < 7; $i++) {
                for ($j = 0; $j < 7; $j++) {
                    $matrix[$row + $i][$col + $j] = $finder[$i][$j];
                }
            }
        }
    }

    private function addTimingPatterns(array &$matrix, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2) ? 0 : 1;
            $matrix[$i][6] = ($i % 2) ? 0 : 1;
        }
    }

    private function addAlignmentPatterns(array &$matrix, int $version, int $size): void
    {
        if ($version < 2) {
            return;
        }

        $alignment = [
            [1, 1, 1, 1, 1],
            [1, 0, 0, 0, 1],
            [1, 0, 1, 0, 1],
            [1, 0, 0, 0, 1],
            [1, 1, 1, 1, 1]
        ];

        $positions = [
            2 => [6, 18],
            3 => [6, 22],
            4 => [6, 26],
            5 => [6, 30]
        ];

        if (!isset($positions[$version])) {
            return;
        }

        foreach ($positions[$version] as $row) {
            foreach ($positions[$version] as $col) {
                if ($row <= 7 && $col <= 7 || $row <= 7 && $col >= $size - 7 || $row >= $size - 7 && $col <= 7) {
                    continue;
                }
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $matrix[$row + $i][$col + $j] = $alignment[$i + 2][$j + 2];
                    }
                }
            }
        }
    }

    private function placeData(array &$matrix, string $binaryData): void
    {
        $size = count($matrix);
        $row = $size - 1;
        $col = $size - 1;
        $up = true;
        $index = 0;

        while ($col >= 0 && $index < strlen($binaryData)) {
            if ($col == 6) {
                $col--;
            }
            for ($j = 0; $j < 2; $j++) {
                if ($matrix[$row][$col - $j] === null && $index < strlen($binaryData)) {
                    $matrix[$row][$col - $j] = (int)$binaryData[$index];
                    $index++;
                }
            }
            $row = $up ? $row - 1 : $row + 1;
            if ($row < 0 || $row >= $size) {
                $row = $up ? 0 : $size - 1;
                $col -= 2;
                $up = !$up;
            }
        }
    }

    private function generateErrorCorrection(string $binaryData, int $version): string
    {
        // Configuración según nivel de corrección y versión
        $ecConfig = [
            self::EC_LEVEL_L => [1 => 7, 2 => 10, 3 => 15, 4 => 20, 5 => 26],
            self::EC_LEVEL_M => [1 => 10, 2 => 16, 3 => 22, 4 => 28, 5 => 36],
            self::EC_LEVEL_Q => [1 => 13, 2 => 22, 3 => 30, 4 => 36, 5 => 44],
            self::EC_LEVEL_H => [1 => 17, 2 => 28, 3 => 36, 4 => 44, 5 => 52]
        ];

        $ecCodewords = $ecConfig[$this->errorCorrection][$version];
        $dataBytes = str_pad($binaryData, $ecCodewords * 8, '0', STR_PAD_RIGHT);
        $data = array_map('bindec', str_split($dataBytes, 8));

        // Generar polinomio Reed-Solomon
        $generator = $this->getRSGeneratorPolynomial($ecCodewords);
        $remainder = $this->polynomialDivision($data, $generator);

        // Convertir resto a binario
        $ecBinary = '';
        foreach ($remainder as $coef) {
            $ecBinary .= str_pad(decbin($coef), 8, '0', STR_PAD_LEFT);
        }

        return $ecBinary;
    }

    private function getRSGeneratorPolynomial(int $degree): array
    {
        $result = [1];
        for ($i = 0; $i < $degree; $i++) {
            $result = $this->polynomialMultiply($result, [1, $this->gfExp($i)]);
        }
        return $result;
    }

    private function polynomialMultiply(array $a, array $b): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);
        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b); $j++) {
                $result[$i + $j] ^= $this->gfMultiply($a[$i], $b[$j]);
            }
        }
        return $result;
    }

    private function polynomialDivision(array $data, array $generator): array
    {
        $result = $data;
        $genLength = count($generator);

        for ($i = 0; $i <= count($data) - $genLength; $i++) {
            if ($result[$i] === 0) {
                continue;
            }
            $factor = $result[$i];
            for ($j = 0; $j < $genLength; $j++) {
                $result[$i + $j] ^= $this->gfMultiply($generator[$j], $factor);
            }
        }

        return array_slice($result, -$genLength + 1);
    }

    private function gfExp(int $n): int
    {
        $gfTable = [
            1, 2, 4, 8, 16, 32, 64, 128, 29, 58, 116, 232, 205, 135, 19, 38,
            76, 152, 45, 90, 180, 117, 234, 201, 143, 3, 6, 12, 24, 48, 96, 192,
            // ... (continúa hasta 255)
        ];
        return $gfTable[$n % 255];
    }

    private function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        $logTable = [
            0 => 0, 1 => 0, 2 => 1, 3 => 25, 4 => 2, 5 => 50, 6 => 26, 7 => 198,
            8 => 3, 9 => 223, 10 => 51, 11 => 238, 12 => 27, 13 => 104, 14 => 199, 15 => 75,
            16 => 4, 17 => 100, 18 => 224, 19 => 14, 20 => 52, 21 => 141, 22 => 239, 23 => 129,
            24 => 28, 25 => 193, 26 => 105, 27 => 248, 28 => 200, 29 => 8, 30 => 76, 31 => 113,
            32 => 5, 33 => 138, 34 => 101, 35 => 47, 36 => 225, 37 => 36, 38 => 15, 39 => 33,
            40 => 53, 41 => 147, 42 => 142, 43 => 218, 44 => 240, 45 => 18, 46 => 130, 47 => 69,
            48 => 29, 49 => 181, 50 => 194, 51 => 125, 52 => 106, 53 => 39, 54 => 249, 55 => 185,
            56 => 201, 57 => 154, 58 => 9, 59 => 120, 60 => 77, 61 => 228, 62 => 114, 63 => 166,
            64 => 6, 65 => 191, 66 => 139, 67 => 98, 68 => 102, 69 => 221, 70 => 48, 71 => 253,
            72 => 226, 73 => 152, 74 => 37, 75 => 179, 76 => 16, 77 => 145, 78 => 34, 79 => 136,
            80 => 54, 81 => 208, 82 => 148, 83 => 206, 84 => 143, 85 => 150, 86 => 219, 87 => 189,
            88 => 241, 89 => 210, 90 => 19, 91 => 92, 92 => 131, 93 => 56, 94 => 70, 95 => 64,
            96 => 30, 97 => 66, 98 => 182, 99 => 163, 100 => 195, 101 => 72, 102 => 126, 103 => 110,
            104 => 107, 105 => 58, 106 => 40, 107 => 84, 108 => 250, 109 => 133, 110 => 186, 111 => 61,
            112 => 202, 113 => 94, 114 => 155, 115 => 159, 116 => 10, 117 => 21, 118 => 121, 119 => 43,
            120 => 78, 121 => 212, 122 => 229, 123 => 172, 124 => 115, 125 => 243, 126 => 167, 127 => 87,
            128 => 7, 129 => 112, 130 => 192, 131 => 247, 132 => 140, 133 => 128, 134 => 99, 135 => 13,
            136 => 103, 137 => 74, 138 => 222, 139 => 237, 140 => 49, 141 => 197, 142 => 254, 143 => 24,
            144 => 227, 145 => 165, 146 => 153, 147 => 119, 148 => 38, 149 => 184, 150 => 180, 151 => 124,
            152 => 17, 153 => 68, 154 => 146, 155 => 217, 156 => 35, 157 => 32, 158 => 137, 159 => 46,
            160 => 55, 161 => 63, 162 => 209, 163 => 91, 164 => 149, 165 => 188, 166 => 207, 167 => 205,
            168 => 144, 169 => 135, 170 => 151, 171 => 178, 172 => 220, 173 => 252, 174 => 190, 175 => 97,
            176 => 242, 177 => 86, 178 => 211, 179 => 171, 180 => 20, 181 => 42, 182 => 93, 183 => 158,
            184 => 132, 185 => 60, 186 => 57, 187 => 83, 188 => 71, 189 => 109, 190 => 65, 191 => 162,
            192 => 31, 193 => 45, 194 => 67, 195 => 216, 196 => 183, 197 => 123, 198 => 164, 199 => 118,
            200 => 196, 201 => 23, 202 => 73, 203 => 236, 204 => 127, 205 => 12, 206 => 111, 207 => 246,
            208 => 108, 209 => 161, 210 => 59, 211 => 82, 212 => 41, 213 => 157, 214 => 85, 215 => 170,
            216 => 251, 217 => 96, 218 => 134, 219 => 177, 220 => 187, 221 => 204, 222 => 62, 223 => 90,
            224 => 203, 225 => 89, 226 => 95, 227 => 176, 228 => 156, 229 => 169, 230 => 11, 231 => 245,
            232 => 22, 233 => 235, 234 => 122, 235 => 117, 236 => 44, 237 => 215, 238 => 79, 239 => 174,
            240 => 213, 241 => 233, 242 => 230, 243 => 231, 244 => 173, 245 => 232, 246 => 116, 247 => 214,
            248 => 244, 249 => 234, 250 => 168, 251 => 80, 252 => 88, 253 => 175, 254 => 181, 255 => 64
        ];
        $expTable = array_flip($logTable);
        $sum = ($logTable[$a] + $logTable[$b]) % 255;
        return $expTable[$sum] ?? 0;
    }

    private function placeErrorCorrection(array &$matrix, string $ecData): void
    {
        $size = count($matrix);
        $row = $size - 1;
        $col = $size - 1;
        $up = true;
        $index = 0;

        while ($col >= 0 && $index < strlen($ecData)) {
            if ($col == 6) {
                $col--;
            }
            for ($j = 0; $j < 2; $j++) {
                if ($matrix[$row][$col - $j] === null && $index < strlen($ecData)) {
                    $matrix[$row][$col - $j] = (int)$ecData[$index];
                    $index++;
                }
            }
            $row = $up ? $row - 1 : $row + 1;
            if ($row < 0 || $row >= $size) {
                $row = $up ? 0 : $size - 1;
                $col -= 2;
                $up = !$up;
            }
        }
    }

    private function applyMask(array &$matrix): void
    {
        $size = count($matrix);
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($matrix[$i][$j] !== null && ($i + $j) % 2 == 0) {
                    $matrix[$i][$j] = $matrix[$i][$j] ? 0 : 1;
                }
            }
        }
    }

    private function renderPNG(): string
    {
        $size = count($this->matrix);
        $pixelSize = floor($this->size / $size);
        $imageSize = $size * $pixelSize;

        $image = imagecreatetruecolor($imageSize, $imageSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($this->matrix[$i][$j] === 1) {
                    imagefilledrectangle(
                        $image,
                        $j * $pixelSize,
                        $i * $pixelSize,
                        ($j + 1) * $pixelSize - 1,
                        ($i + 1) * $pixelSize - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return $imageData;
    }

    private function renderSVG(): string
    {
        $size = count($this->matrix);
        $pixelSize = $this->size / $size;
        $svg = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg .= '<svg width="' . $this->size . '" height="' . $this->size . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="100%" height="100%" fill="white"/>';

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($this->matrix[$i][$j] === 1) {
                    $svg .= '<rect x="' . ($j * $pixelSize) . '" y="' . ($i * $pixelSize) . '" width="' . $pixelSize . '" height="' . $pixelSize . '" fill="black"/>';
                }
            }
        }

        $svg .= '</svg>';
        return $svg;
    }
}

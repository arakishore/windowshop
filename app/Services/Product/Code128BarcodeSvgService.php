<?php

namespace App\Services\Product;

class Code128BarcodeSvgService
{
    /**
     * @var array<int, string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public function svg(string $text, int $height = 42, int $module = 1): string
    {
        $codes = $this->codes($text);
        $checksum = 104;

        foreach ($codes as $position => $code) {
            $checksum += $code * ($position + 1);
        }

        $codes = [104, ...$codes, $checksum % 103, 106];
        $x = 0;
        $rects = '';

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            $bar = true;

            foreach (str_split($pattern) as $width) {
                $width = (int) $width * $module;
                if ($bar) {
                    $rects .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="'.$height.'"/>';
                }

                $x += $width;
                $bar = ! $bar;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$x.'" height="'.$height.'" viewBox="0 0 '.$x.' '.$height.'" preserveAspectRatio="none" role="img" aria-label="Barcode '.$this->escape($text).'">'.$rects.'</svg>';
    }

    /**
     * @return array<int, int>
     */
    private function codes(string $text): array
    {
        $codes = [];

        foreach (str_split($text) as $char) {
            $ascii = ord($char);
            $codes[] = ($ascii >= 32 && $ascii <= 127) ? $ascii - 32 : 0;
        }

        return $codes;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

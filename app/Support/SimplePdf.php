<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Text PDF for statements and tabular reports.
 * ASCII stays in Helvetica. The Taka sign is drawn from embedded Noto Sans Bengali.
 */
final class SimplePdf
{
    /**
     * @param  list<string>  $lines
     */
    public static function download(string $filename, array $lines): Response
    {
        return response(self::render($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function render(array $lines): string
    {
        $font = EmbeddedFont::noto();
        $visual = [];

        foreach ($lines === [] ? [' '] : $lines as $line) {
            foreach (self::wrap($line, $font, 10, 515) as $row) {
                $visual[] = $row;
            }
        }

        $pages = array_chunk($visual, 50);
        /** @var array<int, int> $used */
        $used = [];
        $contents = [];

        foreach ($pages as $pageLines) {
            $contents[] = self::pageStream($pageLines, $font, $used);
        }

        $objects = [];
        $objects[1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $next = 2;
        $resources = '/Font << /F1 1 0 R';

        if ($used !== []) {
            $toUnicode = $next++;
            $file = $next++;
            $descriptor = $next++;
            $cid = $next++;
            $map = $next++;
            $type0 = $next++;
            $objects[$toUnicode] = self::streamObject(self::toUnicode($used));
            $objects[$file] = self::fontFile($font);
            $objects[$descriptor] = self::descriptor($font, $file);
            $objects[$map] = self::fontFileStream(self::cidToGidMap($used));
            $objects[$cid] = self::cidFont($font, $descriptor, $map, $used);
            $objects[$type0] = "<< /Type /Font /Subtype /Type0 /BaseFont /{$font->name}-Regular /Encoding /Identity-H /DescendantFonts [{$cid} 0 R] /ToUnicode {$toUnicode} 0 R >>";
            $resources .= " /F2 {$type0} 0 R";
        }

        $resources .= ' >>';
        $pageIds = [];

        foreach ($contents as $stream) {
            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = self::streamObject($stream);
            $objects[$pageId] = "<< /Type /Page /Parent PAGES 0 R /MediaBox [0 0 595 842] /Contents {$contentId} 0 R /Resources << {$resources} >> >>";
            $pageIds[] = $pageId;
        }

        $pagesId = $next++;
        $kids = implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[$pagesId] = '<< /Type /Pages /Count '.count($pageIds)." /Kids [{$kids}] >>";

        foreach ($pageIds as $pageId) {
            $objects[$pageId] = str_replace('Parent PAGES 0 R', "Parent {$pagesId} 0 R", $objects[$pageId]);
        }

        $catalog = $next;
        $objects[$catalog] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = $catalog + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer << /Size {$size} /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, int>  $used
     */
    private static function pageStream(array $lines, EmbeddedFont $font, array &$used): string
    {
        $commands = "BT /F1 10 Tf 40 800 Td 14 TL\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands .= "T*\n";
            }

            $commands .= self::showLine($line, $font, $used);
        }

        return $commands."ET\n";
    }

    /**
     * @param  array<int, int>  $used
     */
    private static function showLine(string $line, EmbeddedFont $font, array &$used): string
    {
        $commands = '';
        $mode = '';
        $buffer = '';
        $flush = function () use (&$commands, &$mode, &$buffer, $font, &$used): void {
            if ($buffer === '' || $mode === '') {
                return;
            }

            if ($mode === 'embed') {
                $hex = '';
                $characters = preg_split('//u', $buffer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($characters as $character) {
                    $codepoint = mb_ord($character);
                    $glyph = $font->glyph($codepoint);
                    if ($glyph === null) {
                        continue;
                    }
                    $used[$codepoint] = $glyph;
                    $hex .= sprintf('%04X', $codepoint);
                }
                if ($hex !== '') {
                    $commands .= "/F2 10 Tf <{$hex}> Tj\n";
                }
            } else {
                $commands .= '/F1 10 Tf ('.self::escape($buffer).") Tj\n";
            }

            $buffer = '';
        };

        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $codepoint = mb_ord($character);
            $winAnsi = self::winAnsiByte($codepoint);
            $embed = $winAnsi === null && $codepoint > 126 && $font->glyph($codepoint) !== null;
            $next = $embed ? 'embed' : 'ascii';

            if ($mode !== '' && $next !== $mode) {
                $flush();
            }

            $mode = $next;
            if ($embed) {
                $buffer .= $character;
            } elseif ($winAnsi !== null) {
                $buffer .= chr($winAnsi);
            } else {
                $buffer .= '?';
            }
        }

        $flush();

        return $commands;
    }

    /**
     * @param  array<int, int>  $used
     */
    private static function toUnicode(array $used): string
    {
        $lines = [
            '/CIDInit /ProcSet findresource begin',
            '12 dict begin',
            'begincmap',
            '/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def',
            '/CMapName /Adobe-Identity-UCS def',
            '/CMapType 2 def',
            '1 begincodespacerange',
            '<0000> <FFFF>',
            'endcodespacerange',
            count($used).' beginbfchar',
        ];

        foreach (array_keys($used) as $codepoint) {
            $lines[] = sprintf('<%04X> <%04X>', $codepoint, $codepoint);
        }

        $lines[] = 'endbfchar';
        $lines[] = 'endcmap';
        $lines[] = 'CMapName currentdict /CMap defineresource pop';
        $lines[] = 'end';
        $lines[] = 'end';

        return implode("\n", $lines)."\n";
    }

    private static function fontFile(EmbeddedFont $font): string
    {
        return self::fontFileStream($font->bytes);
    }

    private static function fontFileStream(string $bytes): string
    {
        $compressed = gzcompress($bytes);

        return '<< /Length '.strlen($compressed).' /Filter /FlateDecode /Length1 '.strlen($bytes)." >>\nstream\n".$compressed."\nendstream";
    }

    private static function descriptor(EmbeddedFont $font, int $fileId): string
    {
        $box = implode(' ', [
            $font->scale($font->xMin),
            $font->scale($font->yMin),
            $font->scale($font->xMax),
            $font->scale($font->yMax),
        ]);

        return '<< /Type /FontDescriptor /FontName /'.$font->name.'-Regular'
            .' /Flags 32 /FontBBox ['.$box.']'
            .' /ItalicAngle 0 /Ascent '.$font->scale($font->ascent)
            .' /Descent '.$font->scale($font->descent)
            .' /CapHeight '.$font->scale($font->ascent)
            .' /StemV 80 /FontFile2 '.$fileId.' 0 R >>';
    }

    /**
     * @param  array<int, int>  $used
     */
    private static function cidFont(EmbeddedFont $font, int $descriptorId, int $mapId, array $used): string
    {
        $widths = '';

        foreach ($used as $codepoint => $glyph) {
            $widths .= $codepoint.' ['.$font->scale($font->advance($glyph)).'] ';
        }

        return '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /'.$font->name.'-Regular'
            .' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            .' /FontDescriptor '.$descriptorId.' 0 R /CIDToGIDMap '.$mapId.' 0 R /DW 500 /W ['.trim($widths).'] >>';
    }

    /**
     * @param  array<int, int>  $used
     */
    private static function cidToGidMap(array $used): string
    {
        $map = str_repeat("\0\0", max(array_keys($used)) + 1);

        foreach ($used as $codepoint => $glyph) {
            $map = substr_replace($map, pack('n', $glyph), $codepoint * 2, 2);
        }

        return $map;
    }

    private static function streamObject(string $data): string
    {
        return '<< /Length '.strlen($data)." >>\nstream\n".$data.'endstream';
    }

    private static function escape(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, EmbeddedFont $font, float $size, float $maxWidth): array
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);

        if (trim($text) === '') {
            return [''];
        }

        $tokens = preg_split('/( +)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $lines = [];
        $current = '';
        $width = 0.0;

        foreach ($tokens as $token) {
            if ($token === '' || ($current === '' && trim($token) === '')) {
                continue;
            }

            $tokenWidth = self::textWidth($token, $font, $size);

            if ($current !== '' && $width + $tokenWidth > $maxWidth) {
                $lines[] = trim($token) === '' ? rtrim($current).' ' : $current;
                $current = '';
                $width = 0.0;

                if (trim($token) === '') {
                    continue;
                }

                $tokenWidth = self::textWidth($token, $font, $size);
            }

            if ($tokenWidth > $maxWidth) {
                foreach (self::breakToken($token, $font, $size, $maxWidth) as $piece) {
                    if ($current !== '') {
                        $lines[] = $current;
                    }
                    $current = $piece;
                    $width = self::textWidth($piece, $font, $size);
                }

                continue;
            }

            $current .= $token;
            $width += $tokenWidth;
        }

        if ($current !== '') {
            $lines[] = rtrim($current);
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @return list<string>
     */
    private static function breakToken(string $token, EmbeddedFont $font, float $size, float $maxWidth): array
    {
        $pieces = [];
        $current = '';
        $width = 0.0;
        $characters = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $characterWidth = self::textWidth($character, $font, $size);

            if ($current !== '' && $width + $characterWidth > $maxWidth) {
                $pieces[] = $current;
                $current = $character;
                $width = $characterWidth;

                continue;
            }

            $current .= $character;
            $width += $characterWidth;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces === [] ? [''] : $pieces;
    }

    private static function textWidth(string $text, EmbeddedFont $font, float $size): float
    {
        $width = 0.0;
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $codepoint = mb_ord($character);
            $units = self::helveticaUnits($codepoint);

            if ($units !== null) {
                $width += $units * $size / 1000;

                continue;
            }

            $glyph = $font->glyph($codepoint);
            $width += $glyph === null
                ? (0.5 * $size)
                : ($font->scale($font->advance($glyph)) * $size / 1000);
        }

        return $width;
    }

    private static function winAnsiByte(int $codepoint): ?int
    {
        if ($codepoint >= 32 && $codepoint <= 126) {
            return $codepoint;
        }

        if ($codepoint >= 0xA0 && $codepoint <= 0xFF) {
            return $codepoint;
        }

        return [
            0x20AC => 0x80,
            0x201A => 0x82,
            0x0192 => 0x83,
            0x201E => 0x84,
            0x2026 => 0x85,
            0x2020 => 0x86,
            0x2021 => 0x87,
            0x02C6 => 0x88,
            0x2030 => 0x89,
            0x0160 => 0x8A,
            0x2039 => 0x8B,
            0x0152 => 0x8C,
            0x017D => 0x8E,
            0x2018 => 0x91,
            0x2019 => 0x92,
            0x201C => 0x93,
            0x201D => 0x94,
            0x2022 => 0x95,
            0x2013 => 0x96,
            0x2014 => 0x97,
            0x02DC => 0x98,
            0x2122 => 0x99,
            0x0161 => 0x9A,
            0x203A => 0x9B,
            0x0153 => 0x9C,
            0x017E => 0x9E,
            0x0178 => 0x9F,
        ][$codepoint] ?? null;
    }

    private static function helveticaUnits(int $codepoint): ?int
    {
        if (self::winAnsiByte($codepoint) === null) {
            return null;
        }

        static $widths = [
            32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
            58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015,
            65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 500,
            75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
            85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
            91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556, 96 => 333,
            97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556, 104 => 556, 105 => 222, 106 => 222,
            107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278,
            117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500,
            123 => 334, 124 => 260, 125 => 334, 126 => 584,
            0xB7 => 278,
        ];

        $byte = self::winAnsiByte($codepoint);

        return $widths[$codepoint] ?? $widths[$byte] ?? 556;
    }
}

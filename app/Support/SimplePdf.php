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
        $pages = array_chunk($lines === [] ? [' '] : $lines, 42);
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

            $commands .= self::showLine(mb_substr($line, 0, 110), $font, $used);
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
            $embed = $codepoint > 126 && $font->glyph($codepoint) !== null;
            $next = $embed ? 'embed' : 'ascii';

            if ($mode !== '' && $next !== $mode) {
                $flush();
            }

            $mode = $next;
            $buffer .= $embed ? $character : ($codepoint > 126 ? '?' : $character);
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
}

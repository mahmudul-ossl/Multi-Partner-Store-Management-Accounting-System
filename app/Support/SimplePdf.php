<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Text PDF for statements and tabular reports.
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
        $pages = array_chunk($lines === [] ? [' '] : $lines, 42);
        $objects = [];
        $pageIds = [];
        $next = 3;

        foreach ($pages as $pageLines) {
            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = self::stream($pageLines);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents {$contentId} 0 R /Resources << /Font << /F1 1 0 R >> >> >>";
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds)." /Kids [{$kids}] >>";
        $objects[1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $catalog = count($objects) + 1;
        $objects[$catalog] = '<< /Type /Catalog /Pages 2 0 R >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
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
     */
    private static function stream(array $lines): string
    {
        $commands = "BT /F1 10 Tf 40 800 Td 14 TL\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands .= "T*\n";
            }

            $commands .= '('.self::escape(mb_substr($line, 0, 110)).") Tj\n";
        }

        $commands .= "ET\n";

        return '<< /Length '.strlen($commands)." >>\nstream\n".$commands.'endstream';
    }

    private static function escape(string $line): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii === false ? '' : $ascii);
    }
}

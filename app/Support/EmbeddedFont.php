<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Noto Sans Bengali, used so PDF exports can draw U+09F3 (৳).
 */
final class EmbeddedFont
{
    /** @var array<int, int> */
    private array $cmap;

    private function __construct(
        public readonly string $name,
        public readonly string $bytes,
        public readonly int $unitsPerEm,
        public readonly int $ascent,
        public readonly int $descent,
        public readonly int $xMin,
        public readonly int $yMin,
        public readonly int $xMax,
        public readonly int $yMax,
        private readonly string $hmtx,
        private readonly int $numHMetrics,
        array $cmap,
    ) {
        $this->cmap = $cmap;
    }

    public static function noto(): self
    {
        static $font;

        return $font ??= self::load(
            resource_path('fonts/NotoSansBengali-Regular.ttf'),
            'NotoSansBengali',
        );
    }

    public function glyph(int $codepoint): ?int
    {
        return $this->cmap[$codepoint] ?? null;
    }

    public function advance(int $glyphId): int
    {
        if ($glyphId < 0) {
            return 0;
        }

        $index = min($glyphId, max(0, $this->numHMetrics - 1));

        return self::u16($this->hmtx, $index * 4);
    }

    public function scale(int $value): int
    {
        return (int) round($value * 1000 / $this->unitsPerEm);
    }

    private static function load(string $path, string $name): self
    {
        $bytes = file_get_contents($path);

        if ($bytes === false) {
            throw new RuntimeException('The PDF font file is missing.');
        }

        $tables = self::tables($bytes);
        foreach (['cmap', 'head', 'hhea', 'hmtx'] as $required) {
            if (! isset($tables[$required])) {
                throw new RuntimeException('The PDF font is missing the '.$required.' table.');
            }
        }

        $head = substr($bytes, $tables['head'][0], $tables['head'][1]);
        $hhea = substr($bytes, $tables['hhea'][0], $tables['hhea'][1]);

        return new self(
            $name,
            $bytes,
            self::u16($head, 18),
            self::i16($hhea, 4),
            self::i16($hhea, 6),
            self::i16($head, 36),
            self::i16($head, 38),
            self::i16($head, 40),
            self::i16($head, 42),
            substr($bytes, $tables['hmtx'][0], $tables['hmtx'][1]),
            self::u16($hhea, 34),
            self::cmap(substr($bytes, $tables['cmap'][0], $tables['cmap'][1])),
        );
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private static function tables(string $bytes): array
    {
        $count = self::u16($bytes, 4);
        $tables = [];

        for ($index = 0; $index < $count; $index++) {
            $position = 12 + ($index * 16);
            $tag = substr($bytes, $position, 4);
            $tables[$tag] = [self::u32($bytes, $position + 8), self::u32($bytes, $position + 12)];
        }

        return $tables;
    }

    /**
     * @return array<int, int>
     */
    private static function cmap(string $table): array
    {
        $records = self::u16($table, 2);
        $chosen = null;
        $format = 0;

        for ($index = 0; $index < $records; $index++) {
            $platform = self::u16($table, 4 + ($index * 8));
            $encoding = self::u16($table, 6 + ($index * 8));
            $offset = self::u32($table, 8 + ($index * 8));
            $candidate = self::u16($table, $offset);
            $unicode = ($platform === 0 && ($candidate === 4 || $candidate === 12))
                || ($platform === 3 && $encoding === 1 && $candidate === 4)
                || ($platform === 3 && $encoding === 10 && $candidate === 12);

            if ($unicode && ($chosen === null || $candidate === 12)) {
                $chosen = $offset;
                $format = $candidate;
            }
        }

        if ($chosen === null) {
            throw new RuntimeException('The PDF font has no Unicode character map.');
        }

        return $format === 12 ? self::cmap12($table, $chosen) : self::cmap4($table, $chosen);
    }

    /**
     * @return array<int, int>
     */
    private static function cmap4(string $table, int $offset): array
    {
        $segments = intdiv(self::u16($table, $offset + 6), 2);
        $end = $offset + 14;
        $start = $end + (2 * $segments) + 2;
        $delta = $start + (2 * $segments);
        $range = $delta + (2 * $segments);
        $map = [];

        for ($index = 0; $index < $segments; $index++) {
            $endCode = self::u16($table, $end + (2 * $index));
            $startCode = self::u16($table, $start + (2 * $index));
            $idDelta = self::i16($table, $delta + (2 * $index));
            $idRangeOffset = self::u16($table, $range + (2 * $index));

            for ($code = $startCode; $code <= $endCode && $code !== 0xFFFF; $code++) {
                if ($idRangeOffset === 0) {
                    $glyph = ($code + $idDelta) & 0xFFFF;
                } else {
                    $position = $range + (2 * $index) + $idRangeOffset + (2 * ($code - $startCode));
                    $glyph = self::u16($table, $position);
                    if ($glyph !== 0) {
                        $glyph = ($glyph + $idDelta) & 0xFFFF;
                    }
                }

                if ($glyph !== 0) {
                    $map[$code] = $glyph;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private static function cmap12(string $table, int $offset): array
    {
        $groups = self::u32($table, $offset + 12);
        $map = [];

        for ($index = 0; $index < $groups; $index++) {
            $position = $offset + 16 + ($index * 12);
            $start = self::u32($table, $position);
            $end = self::u32($table, $position + 4);
            $glyph = self::u32($table, $position + 8);

            for ($code = $start; $code <= $end; $code++) {
                $map[$code] = $glyph + ($code - $start);
            }
        }

        return $map;
    }

    private static function u16(string $bytes, int $offset): int
    {
        $unpacked = unpack('nvalue', substr($bytes, $offset, 2));

        return (int) ($unpacked['value'] ?? 0);
    }

    private static function u32(string $bytes, int $offset): int
    {
        $unpacked = unpack('Nvalue', substr($bytes, $offset, 4));

        return (int) ($unpacked['value'] ?? 0);
    }

    private static function i16(string $bytes, int $offset): int
    {
        $value = self::u16($bytes, $offset);

        return $value > 32767 ? $value - 65536 : $value;
    }
}

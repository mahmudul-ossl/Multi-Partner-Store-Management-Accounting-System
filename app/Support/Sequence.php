<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class Sequence
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, string $column, string $prefix): string
    {
        $latest = $model::query()
            ->where($column, 'like', $prefix.'-%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $number = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $number = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%05d', $prefix, $number);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use RuntimeException;

trait RejectsDeletion
{
    public static function bootRejectsDeletion(): void
    {
        static::deleting(function (self $model): void {
            throw new RuntimeException($model->deletionMessage());
        });
    }

    public function deletionMessage(): string
    {
        return class_basename(static::class).' records cannot be deleted.';
    }
}

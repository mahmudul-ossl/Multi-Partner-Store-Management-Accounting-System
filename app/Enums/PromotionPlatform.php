<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Google = 'google';
    case TikTok = 'tiktok';
    case Offline = 'offline';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Google => 'Google',
            self::TikTok => 'TikTok',
            self::Offline => 'Offline',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $platform): array => ['value' => $platform->value, 'label' => $platform->label()],
            self::cases(),
        );
    }
}

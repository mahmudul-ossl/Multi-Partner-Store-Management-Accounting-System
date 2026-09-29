<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database alerts for low stock and amounts still due.
 */
class OperationalAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $subjectId,
        public string $title,
        public string $message,
        public string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'subject_id' => $this->subjectId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}

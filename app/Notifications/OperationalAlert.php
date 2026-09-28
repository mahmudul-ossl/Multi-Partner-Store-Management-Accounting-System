<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Stored shape for low stock and payment-due alerts.
 * Delivery goes through DeliverOperationalAlert so the balance is read when the job runs.
 */
class OperationalAlert extends Notification
{
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

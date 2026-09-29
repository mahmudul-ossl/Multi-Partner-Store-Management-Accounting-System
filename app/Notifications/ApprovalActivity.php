<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public ?int $approvalRequestId = null,
        public string $kind = 'approval_required',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->requestIsOpen() ? ['mail', 'database'] : [];
    }

    /**
     * Channels are chosen when the notification is queued. This runs again when the worker delivers it.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->requestIsOpen();
    }

    private function requestIsOpen(): bool
    {
        if ($this->kind !== 'approval_required' || $this->approvalRequestId === null) {
            return true;
        }

        $request = ApprovalRequest::query()->find($this->approvalRequestId);

        return $request instanceof ApprovalRequest
            && $request->status instanceof ApprovalStatus
            && $request->status->isOpen();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Review Request', $this->url);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'approval_request_id' => $this->approvalRequestId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}

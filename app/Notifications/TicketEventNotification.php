<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class TicketEventNotification extends Notification
{
    use Queueable;

    public $id;

    public function __construct(public string $event, public string $ticketNumber, public string $message, public string $url, public string $eventKey, public int $recipientId)
    {
        $this->id = Uuid::uuid5(Uuid::NAMESPACE_URL, $eventKey.':'.$recipientId)->toString();
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable->email) {
            return ['database'];
        }

        $emailEnabled = DB::table('system_settings')->where('key', 'email_notifications')->value('value') === '1';

        return $emailEnabled && config('mail.default') && config('mail.default') !== 'log' ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['event' => $this->event, 'ticket_number' => $this->ticketNumber, 'message' => $this->message, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $institutionName = DB::table('system_settings')->where('key', 'university_name')->value('value') ?: 'City of Ilagan College';

        return (new MailMessage)->subject($this->ticketNumber.' — '.$institutionName.' MIS Helpdesk update')->greeting('Hello '.$notifiable->first_name.',')->line($this->message)->action('Open ticket', $this->url)->line('Please sign in to '.$institutionName.' MIS Helpdesk to view the full update.');
    }
}

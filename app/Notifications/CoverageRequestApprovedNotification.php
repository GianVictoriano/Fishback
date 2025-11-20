<?php

namespace App\Notifications;

use App\Models\Contribution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoverageRequestApprovedNotification extends Notification
{
    use Queueable;

    protected $contribution;

    /**
     * Create a new notification instance.
     */
    public function __construct(Contribution $contribution)
    {
        $this->contribution = $contribution;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $eventDate = $this->contribution->event_date 
            ? $this->contribution->event_date->format('F j, Y g:i A') 
            : 'N/A';

        return (new MailMessage)
            ->subject('Coverage Request Approved - ' . $this->contribution->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Great news! Your coverage request has been approved.')
            ->line('**Event Details:**')
            ->line('**Title:** ' . $this->contribution->title)
            ->line('**Date & Time:** ' . $eventDate)
            ->line('**Location:** ' . ($this->contribution->event_location ?? 'N/A'))
            ->line('**Number of Journalists:** ' . ($this->contribution->num_journalists ?? 1))
            ->line('Our team will be covering this event. You will be notified once the coverage is published.')
            ->line('Thank you for your contribution to our publication!')
            ->salutation('Best regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contribution_id' => $this->contribution->id,
            'title' => $this->contribution->title,
            'status' => $this->contribution->status,
        ];
    }
}

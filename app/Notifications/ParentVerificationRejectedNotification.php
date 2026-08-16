<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParentVerificationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationMailer = (string) config('mail.verification_mailer', config('mail.default'));

        return (new MailMessage)
            ->mailer($verificationMailer)
            ->from(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            )
            ->subject('Update on Your Guardian Verification')
            ->view('emails.moderation-status', [
                'title' => 'Guardian Verification Update',
                'subtitle' => 'Your submission needs corrections',
                'greetingName' => $notifiable->first_name ?? 'Guardian',
                'intro' => 'Your guardian verification was not approved at this time.',
                'details' => [
                    'Reason: ' . $this->reason,
                    'Please correct the issue and submit an updated verification request.',
                ],
                'footerNote' => 'If you need help, contact support before re-submitting your documents.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'parent_verification_rejected',
            'title' => 'Guardian verification rejected',
            'message' => 'Your guardian verification was not approved. Check your email for details.',
            'reason' => $this->reason,
        ];
    }
}

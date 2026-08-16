<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParentVerificationSubmittedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationMailer = (string) config('mail.verification_mailer', config('mail.default'));

        return (new MailMessage)
            ->mailer($verificationMailer)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Guardian Verification Submitted')
            ->view('emails.moderation-status', [
                'title' => 'Verification Submitted',
                'subtitle' => 'Pending admin review',
                'greetingName' => $notifiable->first_name ?? 'Guardian',
                'intro' => 'Your Guardian verification has been submitted successfully.',
                'details' => [
                    'Our administrators will review your identity documents before activating your Guardian account.',
                    'You will receive a notification once your application has been reviewed.',
                ],
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'parent_verification_submitted',
            'title' => 'Verification submitted',
            'message' => 'Your Guardian verification is pending admin review.',
        ];
    }
}

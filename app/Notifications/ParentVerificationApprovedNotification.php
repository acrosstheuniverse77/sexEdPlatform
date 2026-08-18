<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ParentVerificationApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approvalUrl = $this->approvalUrl($notifiable);
        $verificationMailer = (string) config('mail.verification_mailer', config('mail.default'));

        return (new MailMessage)
            ->mailer($verificationMailer)
            ->from(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            )
            ->subject('Your Guardian Verification Has Been Approved')
            ->view('emails.moderation-status', [
                'title' => 'Guardian Verification Approved',
                'subtitle' => 'You can now continue your guardian setup',
                'greetingName' => $notifiable->first_name ?? 'Guardian',
                'intro' => 'Your guardian verification has been approved. You can now continue to your account and complete any remaining setup steps.',
                'details' => [
                    'Use the secure button below to continue to the platform.',
                    'After profile completion, you can create and manage child accounts.',
                ],
                'actionUrl' => $approvalUrl,
                'actionText' => 'Continue to Platform',
                'expiryText' => 'For security, this approval link expires in 7 days.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'parent_verification_approved',
            'title' => 'Guardian verification approved',
            'message' => 'Your guardian account has been approved. Complete your profile to continue.',
            'approval_url' => $this->approvalUrl($notifiable),
        ];
    }

    private function approvalUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'parent.verification.approval-link',
            now()->addDays(7),
            [
                'id' => $notifiable->id,
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}

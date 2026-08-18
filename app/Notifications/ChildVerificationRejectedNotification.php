<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChildVerificationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $child, private readonly string $reason)
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
            ->subject('Child Account Verification Update')
            ->view('emails.moderation-status', [
                'title' => 'Child Verification Update',
                'subtitle' => 'Your submission needs corrections',
                'greetingName' => $notifiable->first_name ?? 'Guardian',
                'intro' => 'Your child account verification was not approved for ' . $this->child->full_name . '.',
                'details' => [
                    'Reason: ' . $this->reason,
                    'Please update the information and re-submit when ready.',
                ],
                'actionUrl' => route('parent.children.index'),
                'actionText' => 'Review Child Applications',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'child_verification_rejected',
            'title' => 'Child account rejected',
            'message' => 'Child verification was not approved. Check your email for details.',
            'child_user_id' => $this->child->id,
            'reason' => $this->reason,
        ];
    }
}

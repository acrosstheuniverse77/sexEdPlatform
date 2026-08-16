<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChildVerificationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $child)
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
            ->subject('Child Account Verification Approved')
            ->view('emails.moderation-status', [
                'title' => 'Child Account Approved',
                'subtitle' => 'Guardian controls are now active',
                'greetingName' => $notifiable->first_name ?? 'Guardian',
                'intro' => 'Your child account verification has been approved for ' . $this->child->full_name . '.',
                'details' => [
                    'You can now monitor progress, quiz activity, and learning history.',
                    'Open your guardian dashboard to manage your dependent account.',
                ],
                'actionUrl' => route('parent.children.index'),
                'actionText' => 'Go to My Dependents',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'child_verification_approved',
            'title' => 'Child account approved',
            'message' => $this->child->full_name . ' is now approved and active.',
            'child_user_id' => $this->child->id,
        ];
    }
}

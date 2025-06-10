<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class CustomVerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify', // This must match the email verification route name
            Carbon::now()->addMinutes(60), // Link expiration time
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(Lang::get('auth.verify_email.subject'))
            ->greeting(Lang::get('auth.verify_email.greeting'))
            ->line(Lang::get('auth.verify_email.line1'))
            ->line(Lang::get('auth.verify_email.line2'))
            ->action(Lang::get('auth.verify_email.action'), $this->verificationUrl($notifiable))
            ->line(Lang::get('auth.verify_email.line3'))
            ->line(Lang::get('auth.verify_email.line4'))
            ->line(Lang::get('auth.verify_email.line5'))
            ->salutation(Lang::get('auth.verify_email.salutation', ['app_name' => config('app.name')]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}

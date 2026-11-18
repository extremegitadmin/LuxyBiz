<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);   // uses the admin.password.reset route from AppServiceProvider
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Reset your password · ' . config('app.name'))
            ->view('emails.reset-password', [
                'name' => $notifiable->name,
                'url' => $url,
                'minutes' => $minutes,
            ]);
    }
}
<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\URL;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
       // Tạo URL trỏ đến front-end
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000'); // Thêm FRONTEND_URL vào .env
        $temporarySignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );

        // Lấy các query params từ temporarySignedUrl
        $parsedUrl = parse_url($temporarySignedUrl);
        parse_str($parsedUrl['query'], $queryParams);

        // Tạo URL cho front-end
        $verifyUrl = $frontendUrl . '/verify-email/' . $notifiable->getKey() . '/' . sha1($notifiable->getEmailForVerification()) . '?' . http_build_query($queryParams);

        return (new MailMessage)
            ->subject('Xác thực Email')
            ->line('Nhấn nút dưới đây để xác thực email của bạn.')
            ->action('Xác thực Email', $verifyUrl)
            ->line('Nếu bạn không đăng ký, vui lòng bỏ qua email này.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

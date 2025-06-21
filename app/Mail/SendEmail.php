<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $contentLine;

    public $viewTemplate;

    /**
     * Create a new message instance.
     */
    public function __construct( $subject, $message, $viewTemplate)
    {
        $this->subjectLine = $subject;
        $this->contentLine = $message;
        $this->viewTemplate = $viewTemplate; // truyền tên view từ controller
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Send Email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        if (!View::exists('emails.' . $this->viewTemplate)) {
            logger('View not found: emails.' . $this->viewTemplate);
            abort(500, 'Email template not found'); // Hoặc throw Exception
        }
        return new Content(
            view: 'emails.' . $this->viewTemplate,
            with: [
                'contentLine' => $this->contentLine,
                'subjectLine' => $this->subjectLine,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

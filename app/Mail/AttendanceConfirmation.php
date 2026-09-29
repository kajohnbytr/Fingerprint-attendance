<?php

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $subjectCode,
        public string $subjectName,
        public CarbonImmutable $attendedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Attendance confirmed: {$this->subjectCode}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attendance-confirmation',
        );
    }
}

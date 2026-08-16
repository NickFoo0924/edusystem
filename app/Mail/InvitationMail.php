<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation email carrying the tokenised registration link.
 *
 * MAIL_MAILER=log in this environment, so sending writes the rendered message
 * to storage/logs/laravel.log instead of contacting any mail server. The
 * administrator can also copy the link straight off the invitations screen,
 * which is how the flow is demonstrated.
 */
class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        // markdown, not view: the <x-mail::...> components in the template are
        // only registered for markdown mailables.
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'url' => route('register.invited', ['token' => $this->invitation->token]),
                'role' => $this->invitation->role,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}

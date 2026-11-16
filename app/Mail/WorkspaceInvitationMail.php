<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class WorkspaceInvitationMail extends Mailable
{
    public function __construct(public array $invitation, public string $acceptUrl) {}

    public function build(): self
    {
        return $this->subject('Lời mời tham gia workspace')->view('mail.workspace-invitation');
    }
}

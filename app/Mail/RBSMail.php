<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RBSMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $rbsMessage;
    public $alert;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $rbsMessage, $alert)
    {
        $this->subject = $subject;
        $this->rbsMessage = $rbsMessage;
        $this->alert = $alert;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)->view('emails.rbs-mail');
    }
}

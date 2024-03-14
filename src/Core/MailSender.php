<?php

namespace App\Core;

use Mailgun\Mailgun;

class MailSender {
    private $mailgun;
    private $sendAddress;
    private $domain;

    public function __construct($mailgunClient) {
        $domain = $_ENV['MAILGUN_DOMAIN'];
        $sendAddress = $_ENV['MAILGUN_SENDER'];

        $this->mailgun = $mailgunClient;
        $this->domain = $domain;
        $this->sendAddress = $sendAddress;
    }

    public function sendAuthEmail($email, $link) {
        $this->mailgun->messages()->send($this->domain, [
            'from'    => $this->sendAddress,
            'to'      => $email,
            'subject' => 'Authorization Link',
            'text'    => 'Use this link to authenticate: ' . $link
        ]);
    }
}

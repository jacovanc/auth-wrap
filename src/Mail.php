<?php

namespace Mail;

use Mailgun\Mailgun;

class MailSender {
    private $mailgun;
    private $domain;
    private $sendAddress;

    public function __construct() {
        $apiKey = getenv('MAILGUN_API_KEY');
        $domain = getenv('MAILGUN_DOMAIN');
        $sendAddress = getenv('MAILGUN_SENDER');

        $this->mailgun = Mailgun::create($apiKey);
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

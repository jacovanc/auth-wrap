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

    public function sendAuthEmail($email, $link): bool {
        try {
            $this->mailgun->messages()->send($this->domain, [
                'from'    => $this->sendAddress,
                'to'      => $email,
                'subject' => 'Authorization Link',
                'text'    => 'Use this link to authenticate: ' . $link
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending email: ' . $e->getMessage());
            return false;
        }
    }
}

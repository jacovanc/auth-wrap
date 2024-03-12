<?php

namespace App;

use App\Core\AuthChecker;

class App {

    protected $container;

    public function __construct($container) {
        $this->container = $container;
    }

    public function run() {
        $authChecker = new AuthChecker();
        $mailSender = $this->container->make('MailSender');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
            $email = $_POST['email'];
            $subdomain = $_SERVER['HTTP_HOST']; // Or extract subdomain logic

            if ($authChecker->isAllowed($email, $subdomain)) {
                $link = $authChecker->generateLink($email, $subdomain);
                $mailSender->sendAuthEmail($email, $link);
                echo 'Access link sent to your email.';
            } else {
                echo 'Access denied.';
            }
        } else {
            // Show the email input form
            echo "Enter your email to access this staging site:";
            echo '<form method="post">Email: <input type="email" name="email"><input type="submit" value="Send Auth Link"></form>';
        }
    }
}
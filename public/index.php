<?php
require_once __DIR__ . '/vendor/autoload.php';

use Auth\AuthChecker;
use Mail\MailSender;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$authChecker = new AuthChecker();
$mailSender = new MailSender();

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
    echo '<form method="post">Email: <input type="email" name="email"><input type="submit" value="Send Auth Link"></form>';
}

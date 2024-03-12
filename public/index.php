<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\AuthChecker;
use App\Core\MailSender;
use Dotenv\Dotenv;
use Mailgun\Mailgun;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env');
$dotenv->load();

# Create the dependencies
$mailgunClient = Mailgun::create(getenv('MAILGUN_API_KEY'));
$mailSender = new MailSender($mailgunClient);

$authChecker = new AuthChecker();

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

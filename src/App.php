<?php

namespace App;

use App\Core\AuthChecker;

class App {

    protected $container;

    private $authChecker;
    private $mailSender;
    private $headerService;

    public function __construct($container) {
        $this->container = $container;

        $this->mailSender = $this->container->make('MailSender');
        $this->authChecker = $this->container->make('AuthChecker');
        $this->headerService = $this->container->make('HeaderService');

        session_start();
    }

    public function run() {
        $redirect = $_SERVER['HTTP_HOST'] ?? null;
        if(!$redirect) {
            throw new \Exception('Invalid request. No Redirect URL specified.');
        }
        
        if($this->isUserAuthenticated()) {
            $this->redirect($redirect);
        } else if(isset($_GET['auth_token'])) { # The token from the email link
            $authToken = $_GET['auth_token'];
            $this->handleEmailLinkClicked($authToken);
        } else if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
            $email = $_POST['email'];
            $subdomain = $_SERVER['HTTP_HOST']; // Or extract subdomain logic
            $this->handleEmailSubmit($email, $subdomain);
        } else {
            $this->showSubmissionForm();
        }
    }

    private function isUserAuthenticated() {
        return isset($_SESSION['authenticated']);
    }

    private function showSubmissionForm() {
        // Show the email input form
        echo "Enter your email to access this staging site:";
        echo '<form method="post">Email: <input type="email" name="email"><input type="submit" value="Send Auth Link"></form>';
    }
    
    private function handleEmailSubmit($email, $domain) {
        if ($this->authChecker->isAllowed($email, $domain)) {
            $link = $this->authChecker->generateLink($email, $domain);
            $this->mailSender->sendAuthEmail($email, $link);
            echo 'Access link sent to your email.';
        } else {
            echo 'Access denied.';
        }
    }

    private function handleEmailLinkClicked($token) {
        // When the user is redirected from the email link
        if ($this->authChecker->validateToken($token)) {
            $_SESSION['authenticated'] = true; // Or some other form of session management

            // Check the original redirect from the database
            $redirect = $this->authChecker->getRedirectFromToken($token);

            $this->redirect($redirect);
        } else {
            echo 'Invalid or expired token.';
        }
    }

    private function redirect($url) {
        $this->headerService->send("Location: https://$url");
    }
}
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
        
        $uri = $_SERVER['REQUEST_URI'];
        $uri = explode('?', $uri)[0];

        # Basic routing
        if($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->loginRoute();
        } else if($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->emailSubmitRoute();
        } else if($uri === '/validate') {
            $this->validateRoute();
        } else if ($uri === '/confirm-email') {
            $this->confirmEmailRoute();
        } else {
            echo '404 Not Found';
        }
    }

    # Shows a login form
    public function loginRoute() {
        unset($_SESSION['authenticated']);
        $redirect = $_SERVER['HTTP_HOST'];
        $this->showSubmissionForm($redirect);
    }

    # Handles the email submission form
    public function emailSubmitRoute() {
        $email = $_POST['email'];
        $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_HOST']; 
        $this->handleEmailSubmit($email, $redirect);
    }

    # Checks if the user is logged in. Returns 200 if true, 401 if false.
    # Used in nginx configuration to determine if the user is allowed to access the site.
    # Also sets the auth cookie if not set
    public function validateRoute() {
        # Do we want to redirect here to the login page? Or leave it pure, and let the nginx config handle the redirect?
        
        if($this->isUserAuthenticated()) {
            $this->headerService->send('HTTP/1.1 200 OK');
        } else {
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
        }
    }

    # The route that the user is redirected to from the email link
    # The token is validated and the user is redirected to the original URL
    public function confirmEmailRoute() {
        if(!isset($_GET['auth_token'])) {
            echo 'Invalid request.';
        } else {
            $authToken = $_GET['auth_token'];
            $this->handleEmailLinkClicked($authToken);
        }
    }

    # Private methods
    private function isUserAuthenticated() {
        return isset($_SESSION['authenticated']);
    }

    private function showSubmissionForm($redirect = null) {
        // Show the email input form
        echo "Enter your email to access this staging site:";
        echo "<form method='post'>";
        echo "<input type='email' name='email'>";
        if($redirect) {
            echo "<input type='hidden' name='redirect' value='$redirect'>";
        }
        echo "<input type='submit' value='Submit'>";
    }
    
    private function handleEmailSubmit($email, $redirect) {
        // Extract the subdomain and domain from the redirect URL (e.g. subdomain.example.com/endpoint -> subdomain.example.com)
        $domain = explode('/', $redirect)[0];
        if ($this->authChecker->isAllowed($email, $domain)) {
            $link = $this->authChecker->generateLink($email, $redirect);
            $this->mailSender->sendAuthEmail($email, $link);
            echo 'Access link sent to your email.';
        } else {
            echo 'Access denied.';
        }
    }

    private function handleEmailLinkClicked($token) {
        // When the user is redirected from the email link
        if ($this->authChecker->validateToken($token)) {
            $_SESSION['authenticated'] = true;

            // Check the original redirect from the database
            $redirect = $this->authChecker->getRedirectFromToken($token);

            $this->redirect($redirect);
        } else {
            echo 'Invalid or expired token.';
        }
    }

    private function redirect($url) {
        $protocol = 'https://';

        // If localhost, use http
        if (strpos($url, 'localhost') !== false) {
            $protocol = 'http://';
        }

        $this->headerService->send("Location: " . $protocol . $url);
    }
}
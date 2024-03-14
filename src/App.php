<?php

namespace App;

use App\Core\Log;

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
        $redirect = $_GET['redirect'] ?? null;
            Log::info('Login route called with redirect: ' . $redirect ?? 'No redirect specified');
        
        if(!$redirect) {
            echo "No redirect specified";
            Log::error('Invalid request. No Redirect URL specified. This is needed to know which subdomain to check permissions for.');
            throw new \Exception('Invalid request. No Redirect URL specified. This is needed to know which subdomain to check permissions for.');
        }
        unset($_SESSION['authenticated']);
        $this->showSubmissionForm($redirect);
    }

    # Handles the email submission form
    public function emailSubmitRoute() {
        $email = $_POST['email'];
        $redirect = $_POST['redirect'] ?? null;
        Log::info('Email submit route called with email: ' . $email . ' and redirect: ' . $redirect);
        $this->handleEmailSubmit($email, $redirect);
    }

    # Checks if the user is logged in. Returns 200 if true, 401 if false.
    # Used in nginx configuration to determine if the user is allowed to access the site.
    public function validateRoute() {
        Log::info('Validatiing user access for original uri: ' . $_SERVER['HTTP_X_ORIGINAL_URI'] ?? 'No original URI');
        if(!$this->isUserAuthenticated() || !isset($_SERVER['HTTP_X_ORIGINAL_URI'])) {
            Log::info('User not authenticated or no original URI. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }
        if(isset($_SESSION['email']) && isset($_SERVER['HTTP_X_ORIGINAL_URI'])) {
            Log::info('User authenticated and original URI set. Checking permissions.');
            $origin = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;
            // Extract the subdomain.domain from the original URI
            $domain = explode('/', $origin)[0];
            Log::info('Extracted domain: ' . $domain);
            if($this->authChecker->isAllowed($_SESSION['email'], $domain)) {
                Log::info('User is allowed. Sending 200 OK.');
                $this->headerService->send('HTTP/1.1 200 OK');
            } else {
                Log::info('User is not allowed. Sending 401 Unauthorized.');
                $this->headerService->send('HTTP/1.1 401 Unauthorized');
            }
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
        echo "<input type='hidden' name='redirect' value='$redirect'>";
        echo "<input type='submit' value='Submit'>";
    }
    
    private function handleEmailSubmit($email, $redirect) {
        // Extract the subdomain and domain from the redirect URL (e.g. subdomain.example.com/endpoint -> subdomain.example.com)
        $domain = explode('/', $redirect)[0];
        Log::info('Extracted domain: ' . $domain);
        if ($this->authChecker->isAllowed($email, $domain)) {
            Log::info('User is allowed. Sending access link to email.');
            $link = $this->authChecker->generateLink($email, $redirect);
            $this->mailSender->sendAuthEmail($email, $link);
            echo 'Access link sent to your email.';
        } else {
            Log::info('User is not allowed. Access Denied.');
            echo 'Access denied.';
        }
    }

    private function handleEmailLinkClicked($token) {
        // When the user is redirected from the email link
        if ($this->authChecker->validateToken($token)) {
            // Check the original redirect from the database
            $tokenData = $this->authChecker->getDataFromToken($token);
            $redirect = $tokenData['redirect'];

            $_SESSION['authenticated'] = true;
            $_SESSION['email'] = $tokenData['email'];

            # Redirect the user back to the original URL. The nginx config for that site should then hit the validate endpoint in a new request.
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
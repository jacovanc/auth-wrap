<?php

namespace App;

use App\Core\Log;

class App {
    protected $container;

    private $authChecker;
    private $mailSender;
    private $headerService;
    private $rateLimit;

    public function __construct($container) {
        $this->container = $container;

        $this->mailSender = $this->container->make('MailSender');
        $this->authChecker = $this->container->make('AuthChecker');
        $this->headerService = $this->container->make('HeaderService');
        $this->rateLimit = $this->container->make('RateLimit');

        $sessionDuration = 3600 * 32; // 1 Day + 8 hours so it doesn't expire during the working day no matter when it was set
        ini_set('session.gc_maxlifetime', $sessionDuration);
        
        # Ensure cookies are set on the top level domain, so that the auth service works for all sub-domain sites. They access the same cookies.
        // Function to get the main domain by removing the subdomain
        function getMainDomain($host) {
            $parts = explode('.', $host);
            // Remove the first part (subdomain) and join the remaining parts
            array_shift($parts);
            return implode('.', $parts);
        }
        
        // Determine the domain based on the current request
        $domain = ($_ENV['APP_ENV'] === 'local') ? 'localhost' : getMainDomain($_SERVER['HTTP_HOST']);
        
        // Prefix with a dot to include all subdomains
        if ($domain !== 'localhost') {
            $domain = '.' . $domain;
        }

        Log::info('HTTP_HOST: ' . $_SERVER['HTTP_HOST'] ?? 'No HTTP_HOST specified');
        Log::info('Setting session cookie params: domain = ' . $domain . ', lifetime = ' . $sessionDuration . ', secure = true, httponly = true, samesite = Lax');

        session_set_cookie_params([
            'domain' => $domain,
            'lifetime' => $sessionDuration,
            'secure' => true, 
            'httponly' => true, 
            'samesite' => 'Lax'
        ]);
        session_name('auth-wrap-session');
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
        if(isset($_SESSION['authenticated'])) {
            unset($_SESSION['authenticated']);
        }
        $this->showSubmissionForm($redirect);
    }

    # Handles the email submission form
    public function emailSubmitRoute() {
        # Check for rate limit to avoid email spam
        $ip = $_SERVER['REMOTE_ADDR'];
        $email = $_POST['email'];
        $redirect = $_POST['redirect'] ?? null;
        
        $isRateLimited = $this->rateLimit->isRateLimited($ip, 'email_submit');
        if($isRateLimited) {
            $error = "Too many requests. Please try again later.";
            $this->showSubmissionForm($redirect, $error);
            return;
        }

        Log::info('Email submit route called with email: ' . $email . ' and redirect: ' . $redirect);
        $this->handleEmailSubmit($email, $redirect);
    }

    # Checks if the user is logged in. Returns 200 if true, 401 if false.
    # Used in nginx configuration to determine if the user is allowed to access the site.
    public function validateRoute() {
        Log::info('Validating user access for original URL: ' . $_SERVER['HTTP_X_ORIGINAL_URL'] ?? 'No original URL');

        Log::info('Important values: ');
        Log::info('Session ID: ' . session_id());
        Log::info('HTTP_X_ORIGINAL_URL: ' . $_SERVER['HTTP_X_ORIGINAL_URL'] ?? 'No original URL specified');
        Log::info('Email in session: ' . $_SESSION['email'] ?? 'No email in session');
        Log::info('Authenticated: ' . $_SESSION['authenticated'] ?? 'Not authenticated');

        if(!$this->isUserAuthenticated()) {
            Log::info('User not authenticated. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }
        if(!isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
            Log::info('No original URI. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }
        if(!isset($_SESSION['email'])) {
            Log::info('No email in session. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }

        Log::info('Checking permissions.');
        $origin = $_SERVER['HTTP_X_ORIGINAL_URL'] ?? null;
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

    # The route that the user is redirected to from the email link
    # The token is validated and the user is redirected to the original URL
    public function confirmEmailRoute() {
        Log::info('Confirm email route called.');
        if(!isset($_GET['auth_token'])) {
            Log::info('Invalid request. No auth token specified.');
            echo 'Invalid request.';
        } else {
            $authToken = $_GET['auth_token'];
            Log::info('Auth token specified. Handling email link clicked. Auth token: ' . $authToken ?? 'No auth token specified.');
            $this->handleEmailLinkClicked($authToken);
        }
    }

    # Private methods
    private function isUserAuthenticated() {
        return isset($_SESSION['authenticated']);
    }

    private function showSubmissionForm($redirect, $error = null, $success = null) {
        // Generate and store CSRF token in session if not already present
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        include __DIR__ . '/../views/login.php';
    }
    
    private function handleEmailSubmit($email, $redirect) {
        // Check if CSRF token is valid
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_token']) {
            $this->showSubmissionForm($redirect, 'Form expired. Please try again.');
            return;
        }
        // Unset the CSRF token to prevent re-use
        unset($_SESSION['csrf_token']);

        // Check if honeypot field is filled (it should be empty)
        if (!empty($_POST['hp'])) {
            // Possibly a bot
            echo 'Access denied.';
            return;
        }
        
        // Extract the subdomain and domain from the redirect URL (e.g. subdomain.example.com/endpoint -> subdomain.example.com)
        $domain = explode('/', $redirect)[0];
        Log::info('Extracted domain: ' . $domain);
        if ($this->authChecker->isAllowed($email, $domain)) {
            Log::info('User is allowed. Sending access link to email.');
            $link = $this->authChecker->generateLink($email, $redirect);
            $this->mailSender->sendAuthEmail($email, $link);
            $this->showSubmissionForm($redirect, null, "Access link sent to your email.");
            return;
        }

        Log::info('User is not allowed. Access Denied.');

        $error = "You don't have access to this site. Please contact the administrator for access.";
        $this->showSubmissionForm($redirect, $error);
        return;
    }

    private function handleEmailLinkClicked($token) {
        // When the user is redirected from the email link
        if ($this->authChecker->validateToken($token)) {
            Log::info('Token is valid.');

            // Check the original redirect from the database
            $tokenData = $this->authChecker->getDataFromToken($token);
            $redirect = $tokenData['redirect'];

            Log::info('Redirect determined from token: ' . $redirect ?? 'No redirect specified.');

            Log::info('Setting session variables: authenticated = true, email = ' . $tokenData['email']);
            Log::info('Session ID: ' . session_id());
            $_SESSION['authenticated'] = true;
            $_SESSION['email'] = $tokenData['email'];

            // Invalidate the token
            $deleted = $this->authChecker->invalidateToken($token);
            if(!$deleted) {
                Log::info('Token could not be invalidated.');
            }

            Log::info('Redirecting to original URL: ' . $redirect);
            # Redirect the user back to the original URL. The nginx config for that site should then hit the validate endpoint in a new request.
            $this->redirect($redirect);
        } else {
            Log::info('Invalid or expired token.');
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
<?php declare(strict_types=1); 

namespace App;

use App\Core\Log;

class App {
    protected $container;

    private $authChecker;
    private $mailSender;
    private $headerService;
    private $rateLimit;
    private $sessionService;

    public function __construct($container) {
        $this->container = $container;

        $this->mailSender = $this->container->make('MailSender');
        $this->authChecker = $this->container->make('AuthChecker');
        $this->headerService = $this->container->make('HeaderService');
        $this->rateLimit = $this->container->make('RateLimit');
        $this->sessionService = $this->container->make('SessionService');
    }

    public function run() {       
        # Start the session
        $this->sessionService->startSession();

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
            $message = "Invalid request. No Redirect URL specified. This is needed to know which subdomain to check permissions for.";
            throw new \Exception($message);
        }
        $this->sessionService->removeAuthenticated();

        $this->showSubmissionForm($redirect);
    }

    # Handles the email submission form
    public function emailSubmitRoute() {
        # Check for rate limit to avoid email spam
        $ip = $_SERVER['REMOTE_ADDR'];
        $email = $_POST['email'] ?? null ?? null;
        $redirect = $_POST['redirect'] ?? null;

        // Check rate limits for IP

        // Check rate limits for IP
        $isRateLimited = $this->rateLimit->isRateLimited($ip, 'email_submit');
        if($isRateLimited) {
            $error = "Too many requests. Please try again later.";
            $this->showSubmissionForm($redirect, $error);
            return;
        }

        // Check if CSRF token is valid
        if(!$this->sessionService->validateCsrfToken($_POST['csrf_token'])) {
            $this->showSubmissionForm($redirect, 'Form expired. Please try again.');
            return;
        }

        // Check if honeypot field is filled (it should be empty)
        if (!empty($_POST['hp'])) {
            $this->showSubmissionForm($redirect, 'Access denied.');
            return;
        }

        // Basic validation
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);

        $redirect = Helper::addSchema($redirect);
        $redirect = filter_var($redirect, FILTER_VALIDATE_URL); // This will later also be checked against a whitelist of allowed domains inside the handleEmailSubmit function, which prevents open redirect exploits

        if(!$email || !$redirect) {
            $error = "Invalid request. Please try again.";
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

        if(!$this->sessionService->isAuthenticated()) {
            Log::info('User not authenticated. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }
        
        if(!isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
            Log::info('No original URI. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }

        $email = $this->sessionService->get('email');
        if(!isset($email)) {
            Log::info('No email in session. Sending 401 Unauthorized.');
            $this->headerService->send('HTTP/1.1 401 Unauthorized');
            return;
        }

        Log::info('Checking permissions.');
        $origin = $_SERVER['HTTP_X_ORIGINAL_URL'] ?? null;
        // Extract the subdomain.domain from the original URI
        $domain = explode('/', $origin)[0];
        Log::info('Extracted domain: ' . $domain);
        if($this->authChecker->isAllowed($email, $domain)) {
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

    private function showSubmissionForm($redirect, $error = null, $success = null) {
        $this->sessionService->setCsrfToken();

        include __DIR__ . '/../views/login.php';
    }
    
    private function handleEmailSubmit($email, $redirect) {       
        // Extract the subdomain and domain from the redirect URL (e.g. https://subdomain.example.com/endpoint -> subdomain.example.com)
        $components = parse_url($redirect);
        $domain = $components['host'] ?? null;

        Log::info('Extracted domain: ' . $domain);

        // Check if the user is allowed to access the subdomain (this also inherently checks if the domain is whitelisted)
        if ($this->authChecker->isAllowed($email, $domain)) {
            Log::info('User is allowed. Sending access link to email.');
            $link = $this->authChecker->generateLink($email, $redirect);
            $success = $this->mailSender->sendAuthEmail($email, $link);
            if(!$success) {
                $this->showSubmissionForm($redirect, 'Error sending email. Please try again.');
                return;
            }
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
        if (!$this->authChecker->validateToken($token)) {
            Log::info('Invalid or expired token.');
            echo 'Invalid or expired token.';
        }

        Log::info('Token is valid.');

        // Check the original redirect from the database
        $tokenData = $this->authChecker->getDataFromToken($token);
        $redirect = $tokenData['redirect'];

        Log::info('Redirect determined from token: ' . $redirect ?? 'No redirect specified.');

        Log::info('Setting session variables: authenticated = true, email = ' . $tokenData['email']);
        Log::info('Session ID: ' . session_id());
        $this->sessionService->setAuthenticated($tokenData['email']);

        // Invalidate the token
        $deleted = $this->authChecker->invalidateToken($token);
        if(!$deleted) {
            Log::info('Token could not be invalidated.');
        }

        Log::info('Redirecting to original URL: ' . $redirect);
        # Redirect the user back to the original URL. The nginx config for that site should then hit the validate endpoint in a new request.
        $this->redirect($redirect);
    }

    private function redirect($url) {
        $this->headerService->send("Location: " . $url);
    }
}
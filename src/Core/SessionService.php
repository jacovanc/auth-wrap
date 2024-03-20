<?php

namespace App\Core;

class SessionService {

    public function startSession() {
        $sessionDuration = 3600 * 32; // 1 Day + 8 hours so it doesn't expire during the working day no matter when it was set
        ini_set('session.gc_maxlifetime', $sessionDuration);
        
        Log::info('Session duration is set to ' . ini_get('session.gc_maxlifetime'));

        # Ensure cookies are set on the top level domain, so that the auth service works for all sub-domain sites. They access the same cookies.
        $hostParts = explode('.', $_SERVER['HTTP_HOST']);
        # Remove the first part (subdomain) and join the remaining parts
        array_shift($hostParts); 
        $topLevelDomain = implode('.', $hostParts);
        
        $topLevelDomain = ($_ENV['APP_ENV'] === 'local') ? 'localhost' : $topLevelDomain;
        
        # Prefix with a dot to include all subdomains, unless localhost
        if ($topLevelDomain !== 'localhost') {
            $topLevelDomain = '.' . $topLevelDomain;
        }

        Log::info('HTTP_HOST: ' . $_SERVER['HTTP_HOST'] ?? 'No HTTP_HOST specified');
        Log::info('Setting session cookie params: domain = ' . $topLevelDomain . ', lifetime = ' . $sessionDuration . ', secure = true, httponly = true, samesite = Lax');

        session_set_cookie_params([
            'domain' => $topLevelDomain,
            'lifetime' => $sessionDuration,
            'secure' => true, 
            'httponly' => true, 
            'samesite' => 'Lax'
        ]);
        session_name('auth-wrap-session');
        session_start();
    }

    public function isAuthenticated() {
        return isset($_SESSION['authenticated']);
    }

    public function setAuthenticated($email) {
        $_SESSION['authenticated'] = true;
        $_SESSION['email'] = $email;
    }

    public function removeAuthenticated() {
        if(isset($_SESSION['authenticated'])) {
            unset($_SESSION['authenticated']);
        }
    }

    public function setCsrfToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function validateCsrfToken($token) {
        Log::info('Validating CSRF token');
        if (!isset($token) || $token != $_SESSION['csrf_token']) {
            Log::info('CSRF token invalid.');
            return false;
        }
        Log::info('CSRF token valid.');
        unset($_SESSION['csrf_token']); // Unset the CSRF token to prevent re-use
        return true;
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public function remove($key) {
        unset($_SESSION[$key]);
    }

}
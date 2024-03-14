<?php


namespace App\Core;

use PDO;

class AuthChecker {
    private $permissions;
    private $db;

    public function __construct($pdo) {
        $permissionsFile = $_ENV['PERMISSIONS_FILE'];
        $path = __DIR__ . '/../../' . $permissionsFile;

        if (!file_exists($path)) {
            throw new \Exception('Permissions file not found.');
        }
        $this->permissions = require $path;

        // Initialize SQLite DB connection
        $this->initializeDatabase($pdo);
    }

    private function initializeDatabase($pdo) {
        $this->db = $pdo;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
                            id INTEGER PRIMARY KEY,
                            email TEXT NOT NULL,
                            redirect TEXT NOT NULL,
                            token TEXT NOT NULL,
                            expires INTEGER NOT NULL
                         )");
    }

    # User is allowed to access the subdomain
    public function isAllowed($email, $subdomain) {
        return in_array($email, $this->permissions[$subdomain] ?? []);
    }

    # Generate an auth link for an unauthenticated user
    # Includes a token that expires in 1 hour
    public function generateLink($email, $redirect) {
        $token = $this->generateToken($email, $redirect);
        $expires = time() + 3600; // 1 hour expiration
        $this->storeTokenData($email, $redirect, $token, $expires);

        return $_ENV['APP_DOMAIN'] . '/confirm-email?auth_token=' . $token;
    }

    # Generate a unique token
    private function generateToken($email, $subdomain) {
        return hash('sha256', $email . '|' . $subdomain . '|' . uniqid('', true));
    }

    # Store the token data in the database
    private function storeTokenData($email, $redirect, $token, $expires) {
        try {
            $stmt = $this->db->prepare("INSERT INTO auth_tokens (email, redirect, token, expires) VALUES (:email, :redirect, :token, :expires)");
            $stmt->execute([':email' => $email, ':redirect' => $redirect, ':token' => $token, ':expires' => $expires]);
        } catch (\PDOException $e) {
            // You can log this error or handle it as required
            echo 'Database error: ' . $e->getMessage();
        }
    }

    public function getDataFromToken($token) {
        $stmt = $this->db->prepare("SELECT redirect FROM auth_tokens WHERE token = :token");
        $stmt->execute([':token' => $token]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $data = [
            'redirect' => $res['redirect'] ?? null,
            'email' => $res['email'] ?? null,
        ];
        return $data;
    }

    # Validate the token and check if it's expired
    public function validateToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM auth_tokens WHERE token = :token AND expires > :time");
        $stmt->execute([':token' => $token, ':time' => time()]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        return $res;
    }
}

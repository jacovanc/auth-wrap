<?php
# Container for dependency injection

namespace App;

use Mailgun\Mailgun;

class Container {
    protected $bindings = [];

    public function bind($key, $value) {
        $this->bindings[$key] = $value;
    }

    public function make($key) {
        return $this->bindings[$key]();
    }

    public function has($key) {
        return isset($this->bindings[$key]);
    }

    # This does not necessarily set up all dependencies, but it sets up the ones that are not environment-specific
    public static function setUpDependencies(Container $container) {
        // Setup dependency injection container
        $container->bind('SessionService', function() {
            return new \App\Services\SessionService();
        });
        $container->bind('Mailgun', function() {
            return Mailgun::create($_ENV['MAILGUN_API_KEY'], $_ENV['MAILGUN_API_BASE_URL'] ?? null);
        });
        $container->bind('MailSender', function() use ($container) {
            return new \App\Core\MailSender(
                $container->make('Mailgun')
            );
        });
        $container->bind('RateLimit', function() use ($container) {
            return new \App\Core\RateLimit(
                $container->make('PDO')
            );
        });
        $container->bind('AuthChecker', function() use ($container) {
            return new \App\Core\AuthChecker(
                $container->make('PDO')
            );
        });
        $container->bind('HeaderService', function() {
            return new \App\Services\HeaderService();
        });
        return $container;
    }
}
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

    public static function setUpDependencies(Container $container) {
        // Setup dependency injection container
        $container->bind('Mailgun', function() {
            return Mailgun::create(getenv('MAILGUN_API_KEY'));
        });
        $container->bind('MailSender', function() use ($container) {
            return new \App\Core\MailSender(
                $container->make('Mailgun')
            );
        });
        return $container;
    }
}
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Container;
use App\App;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env');
$dotenv->load();

# Setup dependency injection container
$container = new Container;

# Intentionally only bind the database dependency here, so that it can never be accidentally used in a test
$container->bind('PDO', function() {
    return new \PDO('sqlite:database.db');
});

# Set up the non-environment-specific dependencies
Container::setUpDependencies($container);

# Include entry point for the application
$app = new App($container);
$app->run();
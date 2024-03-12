<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Container;
use App\App;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env');
$dotenv->load();

// Setup dependency injection container
$container = new Container;
Container::setUpDependencies($container);

// Include entry point for the application
$app = new App($container);
$app->run();
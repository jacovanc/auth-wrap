<?php

use PHPUnit\Framework\TestCase;

use Dotenv\Dotenv;
use App\Container;
use App\App;

# Simulate the auth flow and assert the expected behavior
class IntegrationTest extends TestCase
{
    private $container;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env-test');
        $dotenv->load();
        
        // Setup dependency injection container
        $this->container = new Container;
        Container::setUpDependencies($this->container);

        // Override the Mailgun dependency with a mock
        $this->container->bind('Mailgun', function() {
            return $this->createMock(\Mailgun\Mailgun::class);
        });
    }

    # We need to refactor this so we can mock out dependencies
    public function testLoadAuth()
    {
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HOST'] = 'your-subdomain.localhost';
    
            ob_start(); // Start output buffering
            $app = new App($this->container); // Execute the application
            $app->run();
            $output = ob_get_clean(); // Get the output
    
            // Assert the expected output
            $this->assertStringContainsString('Enter your email', $output);
        }  catch (\Exception $e) {
            $output = ob_end_clean(); // Ensure buffer is closed in case of exception
            echo $output;
            throw $e; // Re-throw the exception
        }
    }
    
   public function testSubmitEmailSuccess()
    {
       try {
            $_POST['email'] = 'user1@example.com';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HOST'] = 'subdomain1.example.com';

            ob_start(); // Start output buffering
            $app = new App($this->container); // Execute the application
            $app->run();
            $output = ob_get_clean();
            
            $this->assertStringContainsString('Access link sent to your email.', $output);
        } catch (\Exception $e) {
            $output = ob_end_clean(); // Ensure buffer is closed in case of exception
            echo $output;
            throw $e; // Re-throw the exception
        }
    }

    public function testSubmitEmailNoAccess()
    {
        try {
            $_POST['email'] = 'noaccess@example.com';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HOST'] = 'subdomain1.example.com';

            ob_start(); // Start output buffering
            $app = new App($this->container); // Execute the application
            $app->run();
            $output = ob_get_clean();
            
            $this->assertStringContainsString('Access denied', $output);
        } catch (\Exception $e) {
            $output = ob_end_clean(); // Ensure buffer is closed in case of exception
            echo $output;
            throw $e; // Re-throw the exception
        }
    }

    protected function tearDown(): void
    {
    }
  
}

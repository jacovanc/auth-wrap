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
        // Set the database dependency to an in-memory SQLite database
        $this->container->bind('PDO', function() {
            $dbPath = __DIR__ . '/../test_database.db';
            return new \PDO('sqlite:' . $dbPath);
        });
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
            $_SERVER['REQUEST_URI'] = '/login';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_GET['redirect'] = 'your-subdomain.localhost';
            $_SERVER['HTTP_X_ORIGINAL_URL'] = 'your-subdomain.localhost';
    
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
            $_SERVER['REQUEST_URI'] = '/login';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST['email'] = 'user1@example.com';
            $_POST['redirect'] = 'subdomain1.example.com';
            $_SERVER['HTTP_X_ORIGINAL_URL'] = 'subdomain1.example.com';

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? null;

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
            $_SERVER['REQUEST_URI'] = '/login';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST['email'] = 'noaccess@example.com';
            $_POST['redirect'] = 'subdomain1.example.com';
            $_SERVER['HTTP_X_ORIGINAL_URI'] = 'subdomain1.example.com';

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? null;

            ob_start(); // Start output buffering
            $app = new App($this->container); // Execute the application
            $app->run();
            $output = ob_get_clean();
            
            $this->assertStringContainsString('You don\'t have access to this site.', $output);
        } catch (\Exception $e) {
            $output = ob_end_clean(); // Ensure buffer is closed in case of exception
            echo $output;
            throw $e; // Re-throw the exception
        }
    }

    public function testEmailLinkClicked() {
        # Override the headerService so we can test redirects
        $mockHeaderService = $this->createMock(\App\Services\HeaderService::class);
        # Expect the send method to be called with a Location header
        $mockHeaderService->expects($this->once())
                            ->method('send')
                            ->with($this->stringContains('Location:'));
                            
        $this->container->bind('HeaderService', function() use ($mockHeaderService) {
            return $mockHeaderService;
        });
        
        $authChecker = $this->container->make('AuthChecker');
        // Make the private method public
        $method = new ReflectionMethod($authChecker, 'generateLink');
        $method->setAccessible(true);
        
        $_SERVER['REQUEST_URI'] = '/confirm-email';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_ORIGINAL_URI'] = 'subdomain1.example.com';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';

        // Generate a real token
        $emailLink = $method->invoke($authChecker, 'user1@example.com', 'subdomain1.example.com');

        // Extract the token 
        $token = explode('?auth_token=', $emailLink)[1];
        $_GET['auth_token'] = $token;
       
        // Assert that the $emailLink is valid
        $this->assertStringContainsString('http://localhost:8000/confirm-email?auth_token=', $emailLink);

        $app = new App($this->container); // Execute the application
        $app->run();
    }
}

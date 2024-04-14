<?php declare(strict_types=1); 

use PHPUnit\Framework\TestCase;
use App\Core\AuthChecker;
use Dotenv\Dotenv;

class AuthCheckerTest extends TestCase
{
    private $authChecker;

    protected function setUp(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env-test');
        $dotenv->load();

        $pdo = new PDO('sqlite::memory:');
        $this->authChecker = new AuthChecker($pdo);
    }

    public function testAuthCheckerWithValidEmail()
    {
        $email = 'user1@example.com';
        $subdomain = 'subdomain1.example.com';

        $result = $this->authChecker->isAllowed($email, $subdomain);

        $email = 'user1@example.com';
        $subdomain = 'subdomain1.example.com';

        $result = $this->authChecker->isAllowed($email, $subdomain);

        $this->assertTrue($result);
    }

    public function testAuthCheckerWithInvalidEmail()
    {
        $email = 'invaliduser@example.com';
        $subdomain = 'subdomain1.example.com';

        $result = $this->authChecker->isAllowed($email, $subdomain);

        $this->assertFalse($result);

        $email = 'invaliduser@example.com';
        $subdomain = 'subdomain2.example.com';

        $result = $this->authChecker->isAllowed($email, $subdomain);

        $this->assertFalse($result);

        $email = 'invaliduser@example.com';
        $subdomain = 'subdomain3.example.com'; // Not in the permissions

        $result = $this->authChecker->isAllowed($email, $subdomain);

        $this->assertFalse($result);
    }
}
# Auth-Wrap: Staging Site Access Control

Auth-Wrap is an authorization tool designed to provide a simple yet effective staging site access control mechanism. It operates by adding an authentication layer on top of your web server, specifically designed to work with nginx. This tool facilitates easy management of staging site access without requiring changes to the application code of the sites themselves.

## Overview

Auth-Wrap integrates with your existing server setup to enforce access control. The service, hosted on a domain, displays a login form before granting access to the requested webpage. Authentication is performed via email validation. Users receive a link in their email, and upon clicking, they are authenticated and redirected to their intended webpage. Access permissions are managed through a configuration file, allowing you to specify which domains are accessible to which email addresses.

### Key Features

- **Email-Based Authentication:** Users are authenticated by sending a validation link to their email.
- **Domain-Level Permissions:** Configure access permissions based on email addresses for specific domains.
- **Server-Level Configuration:** No need to modify application code; configuration is done at the nginx server level.

## How It Works

Auth-Wrap must be hosted on the same top-level domain as the protected sites. For instance, a site hosted at `example.my-domain.com` should have Auth-Wrap hosted at `auth-service.my-domain.com`. This setup leverages top domain level cookies for authentication.

### Authentication Flow

1. **Cookie-Based Session Management:** Upon authentication with Auth-Wrap, a top-level domain cookie is set for the session.
2. **Nginx Integration:** Nginx is configured to forward cookies to Auth-Wrap for authentication verification. Auth-Wrap returns a 200 response for authenticated sessions and a 401 for unauthenticated ones.
3. **Redirection:** Users are redirected to the intended domain after authentication, or to the Auth-Wrap login page in case of failure.

## Setup Instructions

### Prerequisites

- A server running nginx.
- Composer for managing PHP dependencies.

### Installation
1. **Install Composer Dependencies:**
```bash
composer install
```
2. **Configure Permissions**
- Copy config/permissions-example.php to a new file.
- Set up domain-based email permissions as required.
3. **Run the Service**
- Host the service as you would do any PHP project. This includes pointing a domain to it (make sure the top level domain is the same as any of the websites you want to use it with)
- Update Nginx configurations for the applicable websites to point to the Auth-Wrap host. 

## Nginx Configuration Example
```nginx
http {
    server {
        listen 80;

        location / {
            auth_request /auth; # Define the auth request location (internal to nginx, see further down)
            error_page 401 = @error401; # Define the error page - where the login page will be (internal to nginx, see further down)

            # Your existing config here.
        }

        location = /auth {
            internal;
            proxy_pass https://auth-wrap.example.co.uk/validate; # Define the endpoint for the auth check. Make sure the domain is set to what you are hosting auth-wrap on
            proxy_ssl_server_name on;
            proxy_pass_request_body off;
            proxy_set_header Cookie $http_cookie;
            proxy_set_header Content-Length "";
            proxy_set_header X-Original-URL $host$request_uri; # Allows the auth endpoint to know the intended destination for permission checks
        }

        location @error401 {
            return 302 https://auth-wrap.example.co.uk/login?redirect=$host$request_uri; # The endpoint for the login page. Make sure the domain is set to what you are hosting auth-wrap on
        }
    }
}
```

## Serve locally
For testing purposes
```
php -S localhost:8000 -t public public/index.php
```

## Run Tests
Execute the following command to run tests
```
vendor/bin/phpunit
```

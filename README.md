# auth-wrap
A basic authorization process developed for simple staging site(s) access.

The idea is to create an auth flow that sits on top of a server. The nginx configuration for each website can point to auth-wrap (which ideally will be able to be hosted on a different server too) for authentication.
auth-wrap will then show a login form before showing the requested webpage.
Authentication will be done based on email address - sending a validation link to the user's email if they have permission to access the site. Upon clicking the link, they are authed and redirected to the intended webpage.

This is being created for the purpose of creating a simple staging-site authentication process. We want to show a login screen as a layer on top of the website that does not require editing any of the application code for the sites themselves. Instead the configuration is done at the server level via nginx. 

## Setup
 - Install composer dependencies
```
composer install
```
 - Copy the config/permissions-example.php file and setup domain based email permissions as needed.
 - Run however you please.
 - Point nginx configurations for the applicable websites to wherever auth-wrap is hosted.
```nginx
http {
    server {
        listen 80;

        location / {
                auth_request /auth;
                error_page 401 = @error401;
                
                try_files $uri $uri/ /index.html;
            }
            
            location = /auth {
                internal;
                proxy_pass https://auth-wrap.jacovan.co.uk/validate;
                proxy_ssl_server_name on;
                proxy_pass_request_body off;
                proxy_set_header Content-Length "";
                proxy_set_header X-Original-URI $request_uri;
            }
        
            location @error401 {
                return 302 https://auth-wrap.jacovan.co.uk/login?redirect=$host;
            }
    }
}
```

## Serve locally
```
php -S localhost:8000 -t public public/index.php
```

## Run Tests
```
vendor/bin/phpunit
```

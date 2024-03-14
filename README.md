# auth-wrap
A basic authorization process developed for simple staging site(s) access.

The idea is to create an auth flow that sits on top of a server. The nginx configuration for each website can point to auth-wrap for authentication.
Auth-wrap will then show a login form before showing the requested webpage.
Authentication will be done based on email address - sending a validation link to the user's email if they have permission to access the site. Upon clicking the link, they are authed and redirected to the intended webpage.
Permissions are currently managed in a config file, defining who has access to which domains based on their email.

This is being created for the purpose of creating a simple staging-site authentication process. We want to show a login screen as a layer on top of the website that does not require editing any of the application code for the sites themselves. Instead the configuration is done at the server level via nginx. 

## How it works
Currently, the auth-wrap service must be hosted on the same domain as the sites that it is protecting. For example, a site example.my-domain.com would need to point to auth-wrap hosted under auth-service.my-domain.com. 
This is because it currently uses top domain level cookies. When authenticated with auth-wrap, a top domain level cookie is set for the session.
When redirected (or manually navigated) to the intended domain (e.g. example.my-domain.com), the session cookie will be sent as well (as they are on the same top level domain). 
Nginx is then configured to forward these cookies to auth-wrap and check for valid authentication. 200 response means the user is authenticated, 401 means they are not. Nginx then shows the intended page for 200, or redirects to the auth-wrap login page if not.

I don't believe it matters if auth-wrap is hosted on a different server (not tested though). Only the top level domain should matter.

## Future improvements
Ideally I'd like to get this working even when auth-wrap is hosted on a different domain. This presents some challenges though:
- We can no longer use top level domain cookies. How do we ensure that auth-wrap knows who to validate when the request is coming from the intended domain via an nginx sub request? We can't just forward the top level domain cookies.
- We would probably need to use a bearer token that is passed via the login redirect as a url query parameter. Nginx would then strip this out on the first request and store that as a cookie, and forward that new cookie to all auth-wrap validation checks.
- However this introduces a new issue - the bearer token is passed insecurely as a query param. So we would then need to implement a one-time token, which is then used by nginx to fetch a proper bearer token directly from nginx to auth-wrap directly, avoiding passing it via the client. The one-time token is instead passed to the client, but is invalidated immediately upon nginx exchanging it for a bearer token, so that's okay.

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
                auth_request /auth; # Define the internal location for auth checks
                error_page 401 = @error401; # Define what happens on 401 error
                
                # Whatever config you have here for the resource.
            }
            
            # Define the service to call for auth validation
            location = /auth {
                internal;
                proxy_pass https://auth-wrap.example.co.uk/validate;
                proxy_ssl_server_name on; # This was needed to get SSL checks to pass. Not entirely sure why.
                proxy_pass_request_body off;
                proxy_set_header Cookie $http_cookie;  # Forward the cookies received from the client. This ensures the session ID from the auth service is used when validating.
                proxy_set_header Content-Length "";
                proxy_set_header X-Original-URL $host$request_uri; # Pass the intended destination, used by the auth service for validation and redirection.
            }
        
            location @error401 {
                return 302 https://auth-wrap.example.co.uk/login?redirect=$host$request_uri;
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

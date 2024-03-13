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

        location /protected/ {
            # Check for a session cookie (this is only here if the auth service is hosted under the same domain)
            # If it's there, then they are authenticated
            # If it's not there (empty string), they are either un-authenticated, or it's not hosted on the same domain. Either way, we ask the auth service
            if ($cookie_session_cookie = "") {
                auth_request /auth;
            }

            # This defines what happens when the auth service returns a 401 error. In this case, it redirects to the login page (see further down the definition of @error401)
            # This might not be needed if the auth service handles its own redirect, rather than simply stating if the user is authenticated. But need to test to be sure. We should probably leave it as a fallback either way.
            error_page 401 = @error401; 

            # If we get to this point, then the auth_request was successful, and the resource is loaded.
        }

        # This defines where the auth request should go, and how the headers are configured, etc
        # This endpoint will either redirect to the login page (if not authenticated), or simply tell the server that the user is already authenticated - it should let them view the page.
        # Alternatively it could return a 401 instead of redirecting, and let nginx redirect as explained above.
        location = /auth {
            internal;
            proxy_pass http://auth-service-host/validate;
            proxy_pass_request_body off;
            proxy_set_header Content-Length "";
            proxy_set_header X-Original-URI $request_uri; # This is the originally requested URL so we can redirect after authentication (if the user wasn't already authenticated, otherwise the response just comes straight back here anwyay)
        }

        # This is the definition of the redirect for the 401 responses. Again, this might be redirected from the auth service directly instead.
        # This includes the redirect as a get param
        location @error401 {
            return 302 http://auth-service-host/login?redirect=$scheme://$host$request_uri;
        }
    }
}

```

## Run Tests
```
vendor/bin/phpunit
```

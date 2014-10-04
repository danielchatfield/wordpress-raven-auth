WordPress Raven Auth
====================

A raven authentication plugin for WordPress. This plugin owes a lot to
WPRavenAuth but attempts to address some of the shortcomings of that plugin -
namely:

 - Should be installable without git cloning (I.E no submodules)
 - Should come bundled with the KEYS (because I see no reason why it shouldn't)
 - Should be secure out of the box (I.E not require a user to change a setting
   before being secure)
 - Should not have any authentication bypasses ;)
 - Should not be vulnerable to cross-site token replay attacks
 - Should not prevent ordinary users from logging in
 - Should work on a multisite installation


## Structure of project

I have loosely split the project into a fairly generic PHP library and then a 
WordPress specific implementation. This would make it trivial to port the plugin 
to another CMS (or use in a standalone PHP application).

## WordPress API

The wordpress API ([`RavenAuthPlugin`](classes/raven-auth-plugin.php)) subclasses
[`RavenAuthClient`](classes/raven-auth-client.php) (see High level API below) and 
hooks it into the WordPress users system.

## High level generic PHP API

The low level API (specified below) offers full flexibility and closely follows 
the raven spec (keeps the names of parameters the same as those in the spec).
This makes it quite cumbersome to use so there is a higher level API that is 
more user friendly (clear what options mean from name).

### `RavenAuthClient`

```php
$client = new RavenAuthClient($raven_environment);
```

`$raven_environment` is an optional string value that can take two values:

 - 'demo': This causes the library to use the demo (test) raven api
 - 'live': This causes the library to use the live raven api

If `$raven_environment` is not passed then the environment will be determined by 
the constant `RAVEN_ENVIRONMENT` (see below).

#### `RavenAuthClient->authenticate()`

```php
// all arguments are optional
$client->authenticate(
    $site_name,              /* The site name that Raven displays to the user */
    $login_message,          /* The login message that Raven displays to the user */
    $require_password_entry, /* If true (defaults to false) then raven will require
                                user to enter password even if they have an active
                                session. */
    $allow_alumni,           /* If true (defaults to false) then it will accept people 
                                that don't meet Cambridge's definition of "current" */
    $redirect_to             /* Where to redirect to afterwards - if not set then it 
                                defaults to the current URL */
);
```

Calling `$client->authenticate()` will result in one of the following:

 - if there is a current session that satisifies the requirements (being 
   a current user, physically entering password) then it will return the 
   crsid, otherwise;
 - if the current request doesn't contain a response from raven then it will 
   redirect to raven, otherwise
 - if the response from raven is valid then it will set the session and redirect 
   to `$redirect_to`, otherwise
 - the response is not valid - one of these exceptions will be thrown:
   - `RavenAuthException()`
   - `RavenAuthUnknownKIDException()` - this means that the response from raven
     was signed with a private key that is not present in the library - this 
     could happen if the raven server was compromised and they had to start using 
     a new key.
   - `RavenAuthBadResponseException()` - the response was malformed and couldn't 
     be parsed.
   - `RavenAuthResponseVerificationException()` - the response failed verification, 
     this could mean that the timestamp in the token was too old, or that the user 
     is not a current member of the university and `allow_alumni` is not enabled, or 
     that the signature was incorrect.

When calling `authenticate` you should catch `RavenAuthExceptions` and use the 
`getMessage()` method to display a helpful error message to the user. e.g. in WordPress 
you can do the following:

```php
try {
    $client->authenticate()
} catch (RavenAuthException $e) {
    wp_die($e->getMessage());
}
```


#### `RavenAuthClient->getSession($full_session = false)`

Returns null if there is no active session.

If `$full_session` is true it will return the following:

```php
return array(
    'crsid' => $crsid,                      // the CRSID of the user
    'current' => $current,                  // true if the user is a current member of 
                                            // the university
    'password_entered' => $password_entered // true if the user actually entered their 
                                            // password into raven
 );

```

Otherwise if it false then it will just return the crsid as a string.


## Low Level generic PHP API

The low level API consists of [`RavenAuthRequest`](classes/raven-auth-request.php) 
and [`RavenAuthResponse`](classes/raven-auth-response.php) (which both inherit 
from [`RavenAuthResource`](classes/raven-auth-resource.php)) - these are 
essentially implementations of the request to and response from raven - including 
the neccessary methods for parsing and verifying the response.

### RavenAuthRequest

```php
new RavenAuthRequest($parameters = array(), $raven_service = null);
```

`$parameters` is an optional array of raven parameters as specified [here](blob/b431bf50fef32aa201ad1f4a1579c8ce14268832/classes/raven-auth-request.php#L18-L50).

`$raven_service` is an optional instance of a class that implements the 
`RavenAuthServiceInterface` - this is useful for using the demo raven server.

```php
$request = new RavenAuthRequest();

$request->setParameter('msg', 'This is a message');

echo $request->getRavenURL();
// https://demo.raven.cam.ac.uk/auth/authenticate.html?ver=3&date=20141003131322z&msg=This%20is%20a%20message&url=https%3A%2F%2Fexample.com
```

### RavenAuthResponse

```php
new RavenAuthResponse($response = null, $raven_service = null);
```

`$response` is the response from raven.

`$raven_service` is an optional instance of a class that implements the 
`RavenAuthServiceInterface` - this is useful for using the demo raven server.

To check that a response is valid you must call the following methods.

 - `verifyStatus()`
 - `verifySignature()`
 - `verifyIssue()`
 - `verifyURL($trusted_hosts = null, $trust_all_hosts = null)` - You must either pass an
   array of trused hosts (see section below) or pass `true` for $trust_all_hosts (YOU MUST 
   UNDERSTAND THE SECURITY IMPLICATIONS OF DOING THIS - READ THE SECTION BELOW).
 - `verifyAuthOrSSO()`

The above methods all throw exceptions on failure.

If you set 'iact' in the request then you need `verifyAuth()` as well (returns `true` 
or `false`).

To only allow current university members then you need `verifyPtags()` (returns `true` 
or `false`.


## Library configuration constants

### RAVEN_ENVIRONMENT

If set to 'demo' then the default `RavenAuthServiceInterface` that will be used 
will be the demo one.

Alternatively you can pass an instance of the `RavenAuthDemoService` when 
instantiating the `RavenAuthRequest` like so:

```php
$parameters = array(
    "desc" => "Readme example site"
);
$raven_service = new RavenAuthDemoService();
$request = new RavenAuthRequest($parameters, $raven_service);

echo $request->getRavenURL();
// https://demo.raven.cam.ac.uk/auth/authenticate.html?ver=3&date=20141003131322z&desc=Readme%20example%20site&url=https%3A%2F%2Fexample.com
``` 

### RAVEN_TRUST_ALL_HOSTS

First, some background: when a website redirects to raven it includes the URL for 
raven to redirect back to. Raven uses this URL as part of the token that it signs 
to prevent tokens that were issued for one site being used on another. However, 
on many hosting environments it is not possible to reliably determine the host 
for the website since the server itself doesn't know it and just uses the `HOST` 
http header. By spoofing this header an attacker with an access token for one 
website can replay it onto another. At its lowest level (`RavenAuthResponse`), the 
library will let you take care of this however you want (but it will make sure 
that you take care of it) - you can pass a string (or array of strings) that 
contains a "trusted host" that the request host is matched against or you can 
signify that you (the application that is consuming the library) take full 
responsibility for verifying this by passing in a flag to the `verifyURL` method 
or setting `RAVEN_TRUST_ALL_HOSTS` to `TRUE`. This would be appropriate on a 
server that only responds to requests from the correct host (although you should 
be careful as [some servers](https://github.com/mitsuhiko/werkzeug/issues/609) 
will blindly parse other headers like `X-Forwarded-Host`).
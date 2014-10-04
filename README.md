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


## Configuration constants

#### RAVEN_ENVIRONMENT

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

#### RAVEN_TRUST_ALL_HOSTS


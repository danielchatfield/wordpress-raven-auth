<?php

/*
	This file contains all of the Exceptions that can be thrown by Raven,
	they are not in individual files in the interest of simplicity.
*/

class RavenAuthException extends Exception {

}

class RavenAuthUnknownKIDException extends RavenAuthException {
	var $message = 'The key ID specified does not match any key, this might mean '.
                   'that raven has started to use a new key following a compromise.';
}

class RavenAuthUnknownRequestParameterException extends RavenAuthException {

    public function __construct() {
        $args = func_get_args();
        $param = array_shift($args);

        $this->message = "The paramater '$param' is not recognised";

        call_user_func_array(array($this, 'parent::__construct'), $args);
    }
}

class RavenAuthBadResponseException extends RavenException {

}

class RavenAuthResponseVerificationException extends RavenException {

}
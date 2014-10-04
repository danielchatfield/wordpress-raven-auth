<?php

/*
    Wrapper around RavenAuthRequest and RavenAuthResponse that is more
    user friendly (at the cost of some flexibility).
*/

class RavenAuthClient {
    var $raven_service = null;
    var $response = null;
    var $request = null;

    var $crsid;
    var $token;
    var $redirect_to;

    /*
        Raven environment should be either "live", "demo" or null to use the server default.
    */
    public function __constuct($raven_environment = null) {
        if (!is_null($raven_environment)) {
            switch($raven_environment) {
                case 'live':
                    $this->raven_service = new RavenAuthLiveService();
                    break;
                case 'demo':
                    $this->raven_service = new RavenAuthDemoService();
                    break;
                default:
                    throw new RavenAuthException('Unknown raven environment');
            }
        }
    }

    /*
        Will either:
         - redirect to raven
         - redirect to $redirect_to url if successful raven response
         - raise an Exception if current request contains an unsuccessful 
           raven response


        If $require_password_entry is true then raven will require the user to input 
        their password even if they have an active session.

        If $allow_alumni is false then students and staff that do not match 
        the University's definition of "current" will not be allowed.
    */
    public function authenticate($site_name = null, $login_message = null, $require_password_entry = false, $allow_alumni = false, $redirect_to = null) {

        // check if we have a session
        try {
            if ($session = $this->getSession(true)) {
                if (
                    ($allow_alumni || $session['current']) && 
                    (!$require_password_entry || $session['password_entered']
                ) {
                    // session is valid for this request (either the user 
                    // is current or we are allowing non-current users) 
                    // and either we aren't forcing password entry or the 
                    // password was entered
                    return $session['crsid'];
                }
            }
        } catch (RavenAuthException $e) {
            // this will occur if the session has been borked - if this 
            // happens then we should just remove the session and start 
            // again.
            $this->logout();
        }

        // no valid session - lets continue

        // check for raven response
        if (array_key_exists('WLS-Response', $_GET)) {
            $this->crsid = $this->processResponse($_GET['WLS-Response'], $require_password_entry, $allow_alumni);
            $this->setSession();
            $this->redirect();

            // this only gets executed if redirect() has been overloaded and no longer redirects
            return $this->crsid;
        }

        // no raven response - need to redirect

        $parameters = array();

        if (!is_null($site_name)) {
            $parameters['desc'] = $site_name;
        }

        if (!is_null($login_message)) {
            $parameters['msg'] = $login_message;
        }

        if ($force_relogin) {
            $parameters['iact'] = 'yes';
        }

        if (is_null($redirect_to)) {
            $parameters['params'] = $this->getCSRFToken();
        } else {
            $parameters['params'] = $this->getCSRFToken() . '-' . $redirect_to;
        }

        $this->request = new RavenAuthRequest($parameters, $this->raven_service);

        $this->_redirect($this->request->getRavenURL());
    }

    public function processResponse($response = null, $require_password_entry = false, $allow_alumni = false) {
        if (is_null($response)) {
            if (array_key_exists('WLS-Response', $_GET)) {
                $response = $_GET['WLS-Response'];
            } else {
                return false;
            }
        }

        $this->response = new RavenAuthResponse($response, $this->raven_service);

        // check signature
        if (!$this->response->verifySignature()) {
            throw new RavenAuthResponseVerificationException(
                'Signature verification failed');
        }

        // verify issue
        if (!$this->response->verifyIssue()) {
            throw new RavenAuthResponseVerificationException(
                'This login token has expired');
        }

        // verify url
        if (!$this->response->verifyURL(null, true)) {
            throw new RavenAuthResponseVerificationException(
                'The request URL is either untrusted or doesn\'t match the token');
        }

        if ($require_password_entry && !$this->response->verifyAuth()) {
            throw new RavenAuthResponseVerificationException(
                'Password entry was requested but raven is not reporting that it happened.');
        }

        if (!$allow_alumni && !$this->response->verifyPtags('current')) {
            throw new RavenAuthResponseVerificationException(
                'Only current staff and students are allowed');
        }

        // check CSRF token

        $params = $this->response->getParams();

        $length = strpos($params, '-');
        if ($length === false) {
            $length = strlen($params);
            $this->redirect_to = null;
        } else {
            $this->redirect_to = substr($params, $length + 1);
        }

        $this->token = substr($params, 0, $length);

        if ($this->token !== $this->getCSRFToken()) {
            throw new RavenAuthResponseVerificationException(
                'The CSRF token did not match');
        }

        // All good lets return

        return $this->response->getPrincipal();
    }

    public function setSession() {
        session_start();

        $_SESSION['raven_crsid'] = $this->crsid;
        $_SESSION['raven_ptags'] = $this->response->getPtags();
        $_SESSION['raven_password_entered'] = $this->response->verifyAuth() ||
            (array_key_exists('raven_password_entered', $_SESSION) && $_SESSION['raven_password_entered']);
    }


    /*
        Returns either null to indicate no session or if $full is 
        false then returns the crsid or if it is true returns this:

        array(
            'crsid'  =>  $crsid,
            'current' => $is_current,
            'password_entered'  => $password_entered
        );

        'current' is true if raven has indicated that the user meets 
        the university definition of "current staff or student".

        'password_entered' is true when the user physically entered 
        their password into raven;
    */
    public function getSession($full = false) {
        session_start();

        if (!array_key_exists('raven_crsid', $_SESSION)) {
            return null;
        }

        if (!$full) {
            return $_SESSION['raven_crsid'];
        }

        if (!array_key_exists('raven_ptags', $_SESSION)) {
            throw RavenAuthException('CRSID is set in session but ptags are not');
        }

        $password_entered = false;

        if (array_key_exists('raven_password_entered', $_SESSION)) {
            $password_entered = $_SESSION['raven_password_entered'];
        }

        return array(
            'crsid' => $_SESSION['raven_crsid'],
            'current' => $_SESSION['raven_ptags'] === 'current',
            'password_entered' => $password_entered
        );
    }

    public function redirect() {
        if (is_null($this->redirect_to or empty($this->redirect_to))) {
            return;
        } else {
            $this->_redirect($this->redirect_to);
        }
    }

    public function _redirect($url, $status = 302) {
        header('Location: '. $url, true, $status);
        exit();
    }

    public function logout() {
        foreach (array('raven_crsid', 'raven_csrf_token', 'raven_ptags', 'raven_password_entered') as $key) {
            if (array_key_exists($key, $_SESSION)) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function getCSRFToken() {
        session_start();

        if (!array_key_exists('raven_csrf_token', $_SESSION)) {
            $_SESSION['raven_csrf_token'] = $this->generateCSRFToken();
        }

        return $_SESSION['raven_csrf_token'];
    }

    // This should have enough entropy to be more secure than most people's passwords
    public function generateCSRFToken($length = 20) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $token;
    }
}
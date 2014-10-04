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


        If $force_relogin is true then raven will require the user to input 
        their password even if they have an active session.

        If $allow_alumni is false then students and staff that do not match 
        the University's definition of "current" will not be allowed.
    */
    public function authenticate($site_name = null, $login_message = null, $force_relogin = false, $allow_alumni = false, $redirect_to = null) {
        // check for raven response
        if (array_key_exists('WLS-Response', $_GET)) {
            $this->crsid = $this->processResponse($_GET['WLS-Response'], $force_relogin, $allow_alumni);
            $this->setSession();
            $this->redirect();
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

    public function processResponse($response = null, $force_relogin = false, $allow_alumni = false) {
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

        if ($force_relogin && !$this->response->verifyAuth()) {
            throw new RavenAuthResponseVerificationException(
                'Relogin was requested but raven is not reporting that it happened.');
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
        foreach (array('raven_crsid', 'raven_auth_csrf_token') as $key) {
            if (array_key_exists($key, $_SESSION)) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function getCSRFToken() {
        session_start();

        if (!array_key_exists('raven_auth_csrf_token', $_SESSION)) {
            $_SESSION['raven_auth_csrf_token'] = $this->generateCSRFToken();
        }

        return $_SESSION['raven_auth_csrf_token'];
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
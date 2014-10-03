<?php

/*
  This class represents the response from raven.
*/

class RavenAuthResponse extends RavenAuthResource {

    protected $protocol_version = 3;
    protected $raw_response;
    protected $signature_digest;
    protected $fields = array();


    /*
      Instantiates the response object as specified in 
      http://raven.cam.ac.uk/project/waa2wls-protocol.txt

      Field     Value
      --------- ---------------------------------------------------------------

      ver       The version of the WLS protocol in use.

      status    A three digit status code indicating the status of the 
                authentication request.
                    200: Successful authentication
                    410: The user cancelled the authentication request
                    510: No mutually acceptable authentication types available
                    520: Unsupported protocol version
                    530: General request parameter error
                    540: Interaction would be required
                    560: WAA not authorised
                    570: Authentication declined

      msg       A text message further describing the status of the
                authentication request, suitable for display to end-user.

      issue     The date and time that this authentication response was created.

      id        An identifier for this response. 'id', combined with 'issue' 
                provides a unique identifier for this response.

      url       The value of 'url' supplied in the 'authentication request' and 
                used to form the 'authentication response'.

      principal If present, indicates the authenticated identity

      ptags     A potentially empty sequence of text tokens separated by ',' 
                indicating attributes or properties of the identified principal.

                    current: Only authenticate current staff/students
                    [blank]: Impose no restriction on who can be authenticated

      auth      This indicates which authentication type was used if user 
                interaction was required.

                    pwd: An authentication using username and password

      sso       Specifies what authentication type was used to establish the 
                current raven session from which this request was automatically 
                authenticated. One and only one of auth and sso must be set.

                    pwd: An authentication using username and password

      life      Indicates the remaining life (in seconds) of the session on the
                raven server. I disagree with the suggestion that this should 
                be used as an upper limit to the lifetime of any session 
                established as a result of this request.

      params    A verbatim copy of the params part of the request.    

      kid       A string which identifies the RSA key which will be used to 
                form a signature supplied with the response. Can be ommitted 
                if status code is not 200.

      sig       A public-key signature of the response data constructed from 
                the entire parameter value except 'kid' and 'signature' using 
                the private key identified by 'kid', the SHA-1 hash algorithm 
                and the 'RSASSA-PKCS1-v1_5' scheme and the resulting signature 
                encoded using the base64 scheme except that the characters '+',
                '/', and '=' are replaced by '-', '.' and '_' respectively.

    */
    public function __construct($response = null, $raven_service = null) {

        parent::__construct($raven_service);

        if (!is_null($response)) {
            $this->raw_response = $response;
        }
    }

    public function parseResponse($response = null) {
        if (is_null($response)) {
            $response = $this->raw_response;
        }

        $fields = explode('!', $response);

        if ($fields[0] != 3) {
            throw RavenAuthBadResponseException(
                    'The version in the response from raven does not match the '.
                    'expected raven version number.');
        }

        $expected_fields = 14;
        $actual_fields = count($parts);

        if ($actual_fields !== $expected_fields) {
            throw RavenAuthBadResponseException(
                    "Incorrect number of fields in raven response, expected ".
                    "{$expected_fields} but got {$actual_fields}");
        }

        $keys = array(
            "ver",
            "status",
            "msg",
            "issue",
            "id",
            "url",
            "principal",   
            "ptags",
            "auth",
            "sso",
            "life",
            "params",
            "kid",
            "sig"
        );

        $this->fields = array_combine($keys, $fields);

        $this->signature_digest = rawurldecode(implode('!', array_slice($this->fields, 0, -2)));
    }

    public function decodeSignature() {
        $sig = preg_replace(
            array('/-/','/\./','/_/'),
            array('+'  ,'/'   ,'='  ),
            $this->fields['sig']
        );

        return base64_decode($result)
    }

    public function verifySignature() {
        $kid = $this->fields['kid'];
        $key = openssl_pkey_get_public($this->getRavenService()->getCertificate($kid));
        $result = openssl_verify($this->signature_digest, $this->decodeSignature())

        if ($result === 1) {
            return TRUE;
        }

        if ($result === 0) {
            throw RavenAuthResponseVerificationException(
                'The raven response signature was incorrect');
        }

        throw RavenAuthResponseVerificationException(
            'An error occurred whilst checking the raven response signature');
    }

    public function verifyIssue($seconds = 45) {
        if(time() - strtotime($this->fields['issue']) > $seconds) {
             throw RavenAuthResponseVerificationException(
                'Too many seconds have elapsed since this token was signed');
        }
    }


    /*
        In many environments it is not possible to reliably ascertain what the website 
        hostname is as the server is configured to reply to any host. It is therefore 
        neccesary to configure some "trusted hosts" which will be the only hosts that 
        will be allowed.

        If this is not set (null or empty array) then either the constant 
        RAVEN_TRUST_ALL_HOSTS or $trust_all_hosts must be set to true.

        SECURITY WARNING: Only set RAVEN_TRUST_ALL_HOSTS to true if your server is 
                          configured to reject requests with other hosts or you 
                          have implemented some other XSS mitigation e.g. passing 
                          a token via params.
    */
    public function verifyURL($trusted_hosts = null, $trust_all_hosts = null) {
        if (is_null($trust_all_hosts)) {
            if (defined('RAVEN_TRUST_ALL_HOSTS')) {
                $trust_all_hosts = RAVEN_TRUST_ALL_HOSTS;
            } else {
                $trust_all_hosts = false;
            }
        }

        $request_uri = $this->getRequestURI();

        if (!$trust_all_hosts) {
            if (is_null($trusted_hosts)) {
                throw RavenAuthResponseVerificationException(
                    'No trusted hosts set and "trust all hosts" is off');
            }

            if (!$this->verifyHost($request_uri, $trusted_hosts)) {
                throw RavenAuthResponseVerificationException(
                    'The host for this request is not trusted.');
            }
        }

        // all is well - we can check it now

        if ($request_uri !== $this->fields['url']) {
            throw RavenAuthResponseVerificationException(
                    'The request URL does not match the one in the token');
        }
        
        return true;
    }

    public function verifyHost($request_uri, $trusted_hosts) {
        if (!is_array($trusted_hosts)) {
            $trusted_hosts = array($trusted_hosts);
        }

        $request_host = parse_url($request_uri, PHP_URL_HOST);

        foreach ($trusted_hosts as $trusted_host) {
            $trusted_host = parse_url($trusted_host, PHP_URL_HOST);
            if ($trusted_host === $request_host) {
                return true;
            }
        }

        return false;
    }

    public function verifyAuth($type = "pwd") {
        return $this->fields['auth'] === $type;
    }

    public function verifySSO($type = "pwd") {
        return $this->fields['sso'] === $type;
    }

    public function verifyParams($params) {
        return $this->fields['params'] === $params;
    }

    public function getParams() {
        return $this->fields['params'];
    }


    /*
        Returns the raven service implementation associated with this request
        or if there isn't one instantiates a new one (if the constant 
        RAVEN_ENVIRONMENT equals "demo"  then the test server is used).
    */
    public function getRavenService() {
        if ( is_null( $this->raven_service ) ) {
            if (defined('RAVEN_ENVIRONMENT') and RAVEN_ENVIRONMENT === 'demo') {
                $this->raven_service = new RavenAuthDemoService();
            } else {
                $this->raven_service = new RavenAuthLiveService();
            }
        }

        return $this->raven_service;
    }

}

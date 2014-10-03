<?php

/*
  This class represents the request to raven.
*/

class RavenAuthRequest extends RavenAuthResource {

    protected $valid_parameters = array(
        'ver', 'url', 'desc', 'aauth', 'iact', 'msg', 'params', 'date', 'fail');
    protected $parameters = array();


    /*
      Instantiates the request object, $parameters takes an array of parameters
      as specified in http://raven.cam.ac.uk/project/waa2wls-protocol.txt
            
      Parameter Value
      --------- ---------------------------------------------------------------

      ver       The version of the WLS protocol in use.

      url       The url to return to after authentication.

      desc      A text description of the resource requesting authentication 
                which may be displayed to the end-user.

      aauth     A text string representing the types of authentication that 
                will satisfy this request. Each type is separated by a comma.
                    
                    pwd: An authentication using username and password

      iact      The value 'yes' requires that a re-authentication exchange takes 
                place with the user. This could be used prior to a sensitive 
                transaction in an attempt to ensure that a previously 
                authenticated user is still present at the browser. The value 'no' 
                requires that the authentication request will only succeed if 
                the user's identity can be returned without interacting with the
                user.
      
      msg       Text describing why authentication is being requested on this 
                occasion which may be displayed to the end-user.

      params    Data that will be returned unaltered as part of the 
                'authentication response message'.

      date      The current date and time. (only used for debugging purposes)

      fail      If this is 'yes' then raven will display an error to the user 
                if the status code isn't going to be 200 rather than redirect back.

    */
    public function __construct( $parameters = array(), $raven_service = null ) {

        // Set some sensible defaults
        $this->parameters['ver'] = 3;
        $this->parameters['url'] = $this->getRequestURI(); // doesn't matter if this is spoofed
                                                           // as it is verified by response
        $this->parameters['date'] = ra_format_timestamp();

        foreach ($parameters as $parameter => $value) {
            $this->setParameter($parameter, $value);
        }

        parent::__construct($raven_service);
    }

    public function setParameter($parameter, $value) {
        if (array_key_exists($parameter, $this->valid_parameters)) {
            $this->parameters[$parameter] = $value;
        } else {
            throw RavenAuthUnknownRequestParameterException($parameter);
        }
    }


    /*
        Returns the Raven URL to redirect to
    */
    public function getRavenURL() {
        $raven_url = $this->getRavenService->getURL();
        $query = http_build_query($this->parameters);

        return $raven_url + '?' + $query;
    }
}

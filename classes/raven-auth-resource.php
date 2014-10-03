<?php

class RavenAuthResource {

    protected $raven_service;


    public function __construct( $raven_service = null ) {
        if (!is_null($raven_service)) {
            $this->raven_service = $raven_service;
        }
    }

    /*
        SECURITY NOTICE: The host can be spoofed, do not trust the result!
    */
    public function getRequestURI() {
        return $this->getScheme . "://" . $this->getHost() . $this->getPath();
    }

    public function getScheme() {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getHost() {
        $scheme = $this->getScheme();
        $port   = $this->getPort();

        // ignore standard http and https ports
        if (('http' == $scheme && $port == 80) || ('https' == $scheme && $port == 443)) {
            return $this->getHost();
        }

        return $this->getHost().':'.$port;
    }

    public function getPort() {
        // HTTP_HOST is optional for http 1.0
        if (isset($_SERVER['HTTP_HOST']) {

            $host = $_SERVER['HTTP_HOST'];

            if ($host[0] === '[') {
                $pos = strpos($host, ':', strrpos($host, ']'));
            } else {
                $pos = strrpos($host, ':');
            }

            if (false !== $pos) {
                return intval(substr($host, $pos + 1));
            }

            return 'https' === $this->getScheme() ? 443 : 80;
        }

        if (isset($_SERVER['SERVER_PORT'])) {
            return $_SERVER['SERVER_PORT'];
        }

        // no idea what the port is, what sort of crappy server is this?
        return 80;
    }

    public function getPath() {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function isSecure() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");
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

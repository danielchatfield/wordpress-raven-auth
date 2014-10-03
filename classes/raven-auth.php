<?php
class RavenAuth {
    private static $instance = null;
    private $admin = null;

    /**
     * Singleton method for returning the plugin instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public static function install() {
        RavenAuth::getInstance()->_install();
    }

    private function __construct() {
        if( is_admin() )
            $this->getAdmin();

        $this->registerHooks();
    }

    public function registerHooks() {
        add_action('wp_logout', array($this, 'logout'));
        add_action('init', array($this, 'addEndpoint'));
        add_action('template_redirect', array($this, 'checkRequest') );
    }

    public function _install() {
        // plugin has just been activated
        if( !ra_get_option('salt') ) {
            ra_set_option('salt', wp_generate_password( $length=25 ));
        }
    }

    public function getAdmin() {
        if($this->admin === null) {
            $this->admin = new RavenAuth_Admin();
        }
    }

    public function disabled() {
        wp_die('This action is disabled by the Raven Authentication plugin');
    }

    public function loginUser() {
        $webauth = new Ucam_Webauth(array(
            'key_dir'       => $this->getKeysDirectory(),
            'cookie_key'    => ra_get_option('salt'),
            'cookie_name'   => 'wordpress_raven_auth',
            'hostname'      => $_SERVER['HTTP_HOST'],
        ));

        $auth = $webauth->authenticate();

        if(!$auth) {
            wp_die($webauth->status() . " " . $webauth->msg());
        }

        if(!($webauth->success())) {
            wp_die("Raven Authentication not completed.");
        }

        $username = $webauth->principal();
        $email = $username . '@cam.ac.uk';

        wp_die($email);

        if (function_exists('get_user_by') && function_exists('wp_create_user')) {
            if(!$this->userExists($username)) {
                $user_id = wp_create_user( $username, wp_generate_password( $length=25 ), $email );

                if ( !$user_id )  {
                    wp_die('Could not create user');
                }
            }

            $user = $this->getWpUser($username);
            wp_set_auth_cookie( $user->id);
            do_action('wp_login', $user->user_login, $user);

            session_start();

            if (isset($_SESSION["ravenauth_redirect_to"])) {
                wp_safe_redirect($_SESSION["ravenauth_redirect_to"]);
                unset($_SESSION["ravenauth_redirect_to"]);
            }
            else {
                wp_safe_redirect( home_url() );
            }
        }
        else
        {
            wp_die('Something went wrong adding the user to the database');
        }
    }

    public function logout() {
        setcookie('wordpress_raven_auth', '');
        wp_clear_auth_cookie();
    }

    public function userExists($crsid) {
        return (get_user_by('login', $crsid) != false);
    }

    public function getWpUser($crsid) {
        return get_user_by('login', $crsid);
    }

    public function addEndpoint() {
        add_rewrite_endpoint('raven', EP_ROOT);
    }

    public function checkRequest() {
        global $wp_query;

        if( array_key_exists( 'raven' , $wp_query->query_vars ) or array_key_exists('WLS-Response', $_GET)){
            $this->loginUser();
        }
    }

    public function getKeysDirectory() {
        return dirname(dirname(__file__)) . '/keys/';
    }
}

<?php
class RavenAuthPlugin extends RavenAuthClient {
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
            $this->admin = new RavenAuthAdmin();
        }
    }

    public function disabled() {
        wp_die('This action is disabled by the Raven Authentication plugin');
    }

    public function loginUser() {
        $site_name = get_bloginfo();
        try {
            $this->authenticate($site_name);
        } catch (RavenAuthException $e) {
            wp_die($e->getMessage());
        }
    }


    /*
        This overloads RavenAuthClient->setSession() so that we can use the WordPress
        users API.

        Note that we still call the parent setSession as this stores the info required
        to ascertain whether the user is an alumni or not and is thus still useful even 
        though the CRSID is redundant.
    */
    public function setSession() {
        parent::setSession();

        $crsid = $this->crsid;
        $email = $crsid . '@cam.ac.uk';

        if (function_exists('get_user_by') && function_exists('wp_create_user')) {
            if(!$this->userExists($crsid)) {
                $user_id = wp_create_user( $crsid, wp_generate_password( $length=25 ), $email );

                if ( !$user_id )  {
                    wp_die('Could not create user');
                }
            }

            $user = $this->getWpUser($crsid);
            wp_set_auth_cookie($user->id);
            do_action('wp_login', $user->user_login, $user);
        } else {
            wp_die('Something went wrong adding the user to the database');
        }
    }

    public function redirect() {
        if (is_null($this->redirect_to)) {
            wp_safe_redirect(admin_url());
            exit;
        } else {
            wp_safe_redirect($this->redirect_to);
            exit;
        }
    }

    public function logout() {
        parent::logout();
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

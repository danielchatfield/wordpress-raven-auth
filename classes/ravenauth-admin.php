<?php
if( !class_exists('RavenAuth_Admin') ) {

    final class RavenAuth_Admin {
        private $namespace;

        public function __construct() {
            $this->namespace = 'ravenauth';
            add_action('admin_menu', array($this, 'addMenuPages'), 0);
            add_action('admin_init', array( $this, 'registerSettings' ) );
        }

        public function addMenuPages() {
            // Register the main page (that appears in the sidebar)
            $page_title = __('Raven Authentication', 'ravenauth');
            $menu_title = __('Raven Auth', 'ravenauth');
            $capability = 'manage_options';
            $menu_slug = $this->namespace;
            $function = array($this, 'mainPage');

            add_options_page(
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                $function
            );
        }

        public function mainPage() {
          ra_load_file('/options.php', false);
        }

        public function registerSettings() {
            add_settings_section(
                $this->namespace,
                '',
                array( $this, 'sectionInfo' ),
                $this->namespace
            );

            add_settings_field(
                'ravenauth_admin',
                'CRSIDs that should have admin access (comma delimited)',
                array( $this, 'adminField' ),
                $this->namespace,
                $this->namespace
            );

            register_setting( $this->namespace, $this->namespace . '_admin' );
        }


        public function sectionInfo() {
            echo "Enter your settings below";
        }

        public function adminField() {
            printf(
                '<textarea id="ravenauth_admin" name="ravenauth_admin">%s</textarea>',
                esc_attr( ra_get_option('admin', '') )
            );
        }
    }
}
?>

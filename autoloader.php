<?php
class RavenAuth_Autoloader {
    public static function register() {
        spl_autoload_register(array(new self, 'autoload'));
    }

    public static function autoload($class) {

        $class = str_replace('_', '-', $class);
        $class = strtolower($class);

        $file = dirname(__FILE__) . '/classes/' . $class . '.php';

        if (is_file($file)) {
            require_once($file);
        }
    }
}

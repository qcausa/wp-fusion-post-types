<?php

class WPF_Post_Type_Methods {
    private static $instance;
    private $methods = array();

    private function __construct() {
        add_action('plugins_loaded', array($this, 'register_methods'));
    }

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_methods() {
        if (isset(wp_fusion()->crm)) {
            foreach ($this->methods as $method_name => $callback) {
                wp_fusion()->crm->{$method_name} = $callback;
            }
        }
    }

    public function add_method($name, $callback) {
        $this->methods[$name] = $callback;
    }
}

// Initialize the methods registration
WPF_Post_Type_Methods::instance();

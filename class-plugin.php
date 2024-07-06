<?php

namespace WPCT_ABSTRACT;

use Exception;
use ReflectionClass;

if (!class_exists('\WPCT_ABSTRACT\Plugin')) :

    abstract class Plugin extends Singleton
    {
        protected static $menu_class;

        public static $textdomain;
        public static $name;

        private $menu;

        abstract public function init();

        abstract public static function activate();

        abstract public static function deactivate();

        public function __construct()
        {
            if (empty(static::$name) || empty(static::$textdomain)) {
                throw new Exception('Bad plugin initialization');
            }

            if (static::$menu_class && $this->is_active()) {
                $this->menu = static::$menu_class::get_instance(static::$name, static::$textdomain);
            }

            add_action('init', [$this, 'init'], 10);
            add_action('init', function () {
                $this->load_textdomain();
            }, 5);

            add_filter('load_textdomain_mofile', function ($mofile, $domain) {
                return $this->load_mofile($mofile, $domain);
            }, 10, 2);
        }

        public function get_menu()
        {
            return $this->menu;
        }

        public function get_name()
        {
            return static::$name;
        }

        public function get_index()
        {
            $reflector = new ReflectionClass(get_class($this));
            $fn = $reflector->getFileName();
            return plugin_basename($fn);
        }

        public function get_textdomain()
        {
            return static::$textdomain;
        }

        public function get_data()
        {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugins = get_plugins();
            $plugin_name = $this->get_index();
            foreach ($plugins as $plugin => $data) {
                if ($plugin === $plugin_name) {
                    return $data;
                }
            }
        }

        public function is_active()
        {
            return apply_filters('wpct_is_plugin_active', false, $this->get_index());
        }

        private function load_textdomain()
        {
            $data = $this->get_data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            load_plugin_textdomain(
                static::$textdomain,
                false,
                dirname($this->get_index()) . $domain_path,
            );
        }

        private function load_mofile($mofile, $domain)
        {
            if ($domain === static::$textdomain && strpos($mofile, WP_LANG_DIR . '/plugins/') === false) {
                $data = $this->get_data();
                $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

                $locale = apply_filters('plugin_locale', determine_locale(), $domain);
                $mofile = WP_PLUGIN_DIR . '/' . dirname($this->get_index()) . $domain_path . '/' . $domain . '-' . $locale . '.mo';
            }

            return $mofile;
        }


    }

endif;

if (!function_exists('\WPCT_ABSTRACT\is_plugin_active')) :

    add_filter('wpct_is_plugin_active', '\WPCT_ABSTRACT\is_plugin_active', 10, 2);
    function is_plugin_active($_, $plugin_name)
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugins = get_plugins();

        if (is_multisite()) {
            $actives = apply_filters('active_plugins', array_map(function ($plugin_path) {
                return plugin_basename($plugin_path);
            }, wp_get_active_network_plugins()));
        } else {
            $actives = apply_filters('active_plugins', get_option('active_plugins'));
        }

        $actives = array_reduce(array_keys($plugins), function ($carry, $plugin_path) use ($plugins, $actives) {
            if (in_array($plugin_path, $actives)) {
                $carry[$plugin_path] = $plugins[$plugin_path];
            }

            return $carry;
        }, []);

        $plugin_name = plugin_basename($plugin_name);
        return in_array($plugin_name, array_keys($actives));
    }

endif;

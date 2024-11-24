<?php

namespace WPCT_ABSTRACT;

use Exception;
use ReflectionClass;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Plugin')) :

    require_once 'class-singleton.php';
    require_once 'class-menu.php';

    /**
     * Plugin abstract class.
     */
    abstract class Plugin extends Singleton
    {
        /**
         * Handle plugin menu class name.
         *
         * @var string $menu_class Menu class name.
         */
        protected static $menu_class = '\WPCT_ABSTRACT\Menu';

        /**
         * Handle plugin textdomain.
         *
         * @var string $textdomain Plugin text domain.
         */
        protected static $textdomain;

        /**
         * Handle plugin name.
         *
         * @var string $name Plugin name.
         */
        protected static $name;

        /**
         * Handle plugin menu instance.
         *
         * @var object $menu Plugin menu instance.
         */
        private $menu;

        /**
         * Plugin initializer.
         */
        abstract public function init();

        /**
         * Plugin activation callback.
         */
        abstract public static function activate();

        /**
         * Plugin deactivation callback.
         */
        abstract public static function deactivate();

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
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

        /**
         * Plugin menu getter.
         *
         * @return object $menu Plugin menu instance.
         */
        public function get_menu()
        {
            return $this->menu;
        }

        /**
         * Plugin name getter.
         *
         * @return string $name Plugin name.
         */
        public function get_name()
        {
            return static::$name;
        }

        /**
         * Plugin index getter.
         *
         * @return string $index Plugin index file path.
         */
        public function get_index()
        {
            $reflector = new ReflectionClass(get_class($this));
            $fn = $reflector->getFileName();
            return plugin_basename($fn);
        }

        /**
         * Plugin textdomain getter.
         *
         * @return string $textdomain Plugin textdomain.
         */
        public function get_textdomain()
        {
            return static::$textdomain;
        }

        /**
         * Plugin data getter.
         *
         * @return array $data Plugin data.
         */
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

        /**
         * Active state getter.
         *
         * @return boolean $is_active Plugin active state.
         */
        public function is_active()
        {
            return apply_filters('wpct_is_plugin_active', false, $this->get_index());
        }

        /**
         * Load plugin textdomain.
         */
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

        /**
         * Load plugin mofile.
         *
         * @param string $mofile Plugin mofile path.
         * @param string $domain Plugin textdomain.
         * @return string $mofile Plugin mofile path.
         */
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

    /**
     * Check if plugin is active
     */
    function is_plugin_active($plugin_name)
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

// Hooks is_plugin_active as filter.
add_filter('wpct_is_plugin_active', function ($null, $plugin_name) {
    return \WPCT_ABSTRACT\is_plugin_active($plugin_name);
}, 10, 2);

endif;

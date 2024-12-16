<?php

namespace WPCT_ABSTRACT;

use Exception;
use ReflectionClass;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Plugin')) {

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

        protected static $settings_class = '\WPCT_ABSTRACT\Settings';

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
         * Handle the instance of the settings store.
         *
         * @var Settings Settings instance.
         */ 
        private $settings;

        /**
         * Handle plugin menu instance.
         *
         * @var object $menu Plugin menu instance.
         */
        private $menu;

        /**
         * Plugin initializer.
         */
        protected function init()
        {
        }

        /**
         * Plugin activation callback.
         */
        public static function activate()
        {
        }

        /**
         * Plugin deactivation callback.
         */
        public static function deactivate()
        {
        }

        /**
         * Public plugin's initializer.
         */ 
        public static function setup(...$args)
        {
            return self::get_instance(...$args);
        }

        /**
         * Checks if some plugin is active, also in the network.
         *
         * @param string $plugin_name Index file of the plugin.
         *
         * @return boolean Activation state of the plugin.
         */ 
        public static function is_plugin_active($plugin_name)
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

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
        protected function construct(...$args)
        {
            if (empty(static::$name) || empty(static::$textdomain)) {
                throw new Exception('Bad plugin initialization');
            }

            if (static::$settings_class !== '\WPCT_ABSTRACT\Settings') {
                $this->settings = static::$settings_class::get_instance(static::$textdomain);

                if (static::$menu_class !== '\WPCT_ABSTRACT\Menu' && $this->is_active()) {
                    $this->menu = static::$menu_class::get_instance(static::$name, static::$textdomain, $this->settings);
                }
            }

            add_action('init', function () {
                $this->init();
                $this->load_textdomain();
            });

            add_filter('load_textdomain_mofile', function ($mofile, $domain) {
                return $this->load_mofile($mofile, $domain);
            }, 10, 2);

            add_filter(
                'plugin_action_links',
                static function ($links, $file) {
                    if (static::$menu_class === '\WPCT_ABSTRACT\Menu') {
                        return $links;
                    }

                    $reflector = new ReflectionClass(static::class);
                    $__FILE__ = $reflector->getFileName();

                    if ($file !== plugin_basename($__FILE__)) {
                        return $links;
                    }

                    $url = admin_url('options-general.php?page=' . static::$textdomain);
                    $label = __('Settings');
                    $link = "<a href='{$url}'>{$label}</a>";
                    array_unshift($links, $link);
                    return $links;
                },
                10,
                2
            );

            register_activation_hook($this->index(), function () {
                static::activate();
            });

            register_deactivation_hook($this->index(), function () {
                static::deactivate();
            });
        }

        /**
         * Plugin menu getter.
         *
         * @return object $menu Plugin menu instance.
         */
        public function menu()
        {
            return $this->menu;
        }

        /**
         * Plugin name getter.
         *
         * @return string $name Plugin name.
         */
        public function name()
        {
            return static::$name;
        }

        /**
         * Plugin index getter.
         *
         * @return string $index Plugin index file path.
         */
        public function index()
        {
            $reflector = new ReflectionClass(static::class);
            $__FILE__ = $reflector->getFileName();
            return plugin_basename($__FILE__);
        }

        /**
         * Plugin textdomain getter.
         *
         * @return string $textdomain Plugin textdomain.
         */
        public function textdomain()
        {
            return static::$textdomain;
        }

        /**
         * Plugin data getter.
         *
         * @return array $data Plugin data.
         */
        public function data()
        {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugins = get_plugins();
            $plugin_name = $this->index();
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
            return apply_filters('wpct_is_plugin_active', false, $this->index());
        }

        /**
         * Load plugin textdomain.
         */
        private function load_textdomain()
        {
            $data = $this->data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            load_plugin_textdomain(
                static::$textdomain,
                false,
                dirname($this->index()) . $domain_path,
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
                $data = $this->data();
                $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

                $locale = apply_filters('plugin_locale', determine_locale(), $domain);
                $mofile = WP_PLUGIN_DIR . '/' . dirname($this->index()) . $domain_path . '/' . $domain . '-' . $locale . '.mo';
            }

            return $mofile;
        }
    }

    add_filter('wpct_is_plugin_active', function ($false, $plugin_name) {
        return Plugin::is_plugin_active($plugin_name);
    }, 10, 2);
}

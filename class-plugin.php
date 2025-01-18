<?php

namespace WPCT_ABSTRACT;

use ReflectionClass;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Plugin')) {

    require_once 'class-singleton.php';
    require_once 'class-menu.php';
    require_once 'class-settings-store.php';

    /**
     * Plugin abstract class.
     */
    abstract class Plugin extends Singleton
    {
        /**
         * Handles plugin's menu class name.
         *
         * @var string
         */
        protected static $menu_class;

        /**
         * Handles plugin's settings store class name.
         *
         * @var string
         */
        protected static $settings_class;

        /**
         * Handles plugin's headers data.
         *
         * @var string
         */
        private static $data;

        /**
         * Handles plugin's settings store instance.
         *
         * @var SettingsStore
         */
        private $settings_store;

        /**
         * Handles plugin's menu instance.
         *
         * @var Menu
         */
        private $menu;

        /**
         * Plugin initializer.
         */
        protected static function init()
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
        final public static function setup(...$args)
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
        final public static function is_plugin_active($plugin_name)
        {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            return is_plugin_active($plugin_name);
        }

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
        protected function construct(...$args)
        {
            if (isset(static::$settings_class)) {
                $this->settings_store = static::$settings_class::get_instance(static::slug());

                if (isset(static::$menu_class) && static::is_active()) {
                    $this->menu = static::$menu_class::get_instance(static::name(), static::slug(), $this->settings_store);
                }
            }

            add_action('init', function () {
                static::init();
                self::load_textdomain();
            });

            add_filter(
                'plugin_action_links',
                static function ($links, $file) {
                    if (!isset(static::$menu_class)) {
                        return $links;
                    }

                    $reflector = new ReflectionClass(static::class);
                    $__FILE__ = $reflector->getFileName();

                    if ($file !== plugin_basename($__FILE__)) {
                        return $links;
                    }

                    $url = admin_url('options-general.php?page=' . self::slug());
                    $label = __('Settings', 'wpct-plugin-abstracts');
                    $link = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
                    array_unshift($links, $link);
                    return $links;
                },
                10,
                2
            );

            register_activation_hook(self::index(), function () {
                static::activate();
            });

            register_deactivation_hook(self::index(), function () {
                static::deactivate();
            });
        }

        /**
         * Plugin name getter.
         *
         * @return string $name Plugin name.
         */
        final public static function name()
        {
            return self::data()['Name'];
        }

        /**
         * Plugin slug getter.
         *
         * @return string $slug Plugin's textdomain alias.
         */
        final public static function slug()
        {
            return pathinfo(self::index())['filename'];
        }

        /**
         * Plugin index getter.
         *
         * @return string Plugin's index file.
         */
        final public static function index()
        {
            $reflector = new ReflectionClass(static::class);
            $__FILE__ = $reflector->getFileName();
            return plugin_basename($__FILE__);
        }

        /**
         * Plugin's path getter.
         *
         * @return string
         */
        final public static function path()
        {
            $reflector = new ReflectionClass(static::class);
            return plugin_dir_path($reflector->getFileName());
        }

        /**
         * Plugin textdomain getter.
         *
         * @return string Plugin's textdomain.
         */
        final public static function textdomain()
        {
            return self::data()['TextDomain'];
        }

        /**
         * Plugin version getter.
         *
         * @return string Plugin's version.
         */
        final public static function version()
        {
            return self::data()['Version'];
        }

        /**
         * Plugin dependencies getter.
         *
         * @return array Plugin's dependencies.
         */
        final public static function dependencies()
        {
            $dependencies = self::data()['RequiresPlugins'];
            if (empty($dependencies)) {
                return [];
            }

            return array_map(function ($plugin) {
                $plugin = trim($plugin);
                return $plugin . '/' . $plugin . '.php';
            }, explode(',', $dependencies));
        }

        /**
         * Plugin data getter.
         *
         * @return array $data Plugin data.
         */
        private static function data()
        {
            if (!empty(static::$data)) {
                return static::$data;
            }

            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugin_dir = self::path() . basename(self::index());
            static::$data = get_plugin_data($plugin_dir, false, false);
            return static::$data;
        }

        /**
         * Active state getter.
         *
         * @return boolean $is_active Plugin active state.
         */
        final public static function is_active()
        {
            return self::is_plugin_active(self::index());
        }

        /**
         * Load plugin textdomain.
         */
        private static function load_textdomain()
        {
            $data = self::data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            load_plugin_textdomain(
                self::textdomain(),
                false,
                dirname(self::index()) . $domain_path,
            );
        }

        final public static function menu()
        {
            static::get_instance()->menu;
        }

        final public static function settings()
        {
            $store = static::get_instance()->settings_store;
            if (empty($store)) {
                return;
            }

            return $store::settings();
        }

        final public static function setting($name)
        {
			$store = static::get_instance()->settings_store;
            if (empty($store)) {
                return;
            }

            return $store::setting($name);
        }
    }
}

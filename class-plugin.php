<?php

namespace WPCT_ABSTRACT;

use ReflectionClass;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Plugin')) {

    require_once 'class-singleton.php';
    require_once 'class-menu.php';
    require_once 'class-settings.php';

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
        protected static $menu_class;

        /**
         * Handle plugin settings store class name.
         *
         * @var string $settings_name Settings store class name.
         */
        protected static $settings_class;

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
            return is_plugin_active($plugin_name);
        }

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
        protected function construct(...$args)
        {
            if (isset(static::$settings_class)) {
                $this->settings = static::$settings_class::get_instance(static::slug());

                if (isset(static::$menu_class) && static::is_active()) {
                    $this->menu = static::$menu_class::get_instance(static::name(), static::slug(), $this->settings);
                }
            }

            add_action('init', function () {
                $this->init();
                static::load_textdomain();
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

                    $url = admin_url('options-general.php?page=' . static::slug());
                    $label = __('Settings', 'wpct-plugin-abstracts');
                    $link = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
                    array_unshift($links, $link);
                    return $links;
                },
                10,
                2
            );

            register_activation_hook(static::index(), function () {
                static::activate();
            });

            register_deactivation_hook(static::index(), function () {
                static::deactivate();
            });
        }

        /**
         * Plugin name getter.
         *
         * @return string $name Plugin name.
         */
        public static function name()
        {
            return self::data()['Name'];
        }

        /**
         * Plugin slug getter.
         *
         * @return string $slug Plugin's textdomain alias.
         */
        public static function slug()
        {
            return pathinfo(self::index())['filename'];
        }

        /**
         * Plugin index getter.
         *
         * @return string Plugin's index file.
         */
        public static function index()
        {
            $reflector = new ReflectionClass(static::class);
            $__FILE__ = $reflector->getFileName();
            return plugin_basename($__FILE__);
        }

        public static function path()
        {
            $reflector = new ReflectionClass(static::class);
            return plugin_dir_path($reflector->getFileName());
        }

        /**
         * Plugin textdomain getter.
         *
         * @return string Plugin's textdomain.
         */
        public static function textdomain()
        {
            return self::data()['TextDomain'];
        }

        /**
         * Plugin version getter.
         *
         * @return string Plugin's version.
         */
        public static function version()
        {
            return self::data()['Version'];
        }

        /**
         * Plugin dependencies getter.
         *
         * @return array Plugin's dependencies.
         */
        public static function dependencies()
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
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugin_dir = static::path() . basename(self::index());
            return get_plugin_data($plugin_dir, false, false);
        }

        /**
         * Active state getter.
         *
         * @return boolean $is_active Plugin active state.
         */
        public static function is_active()
        {
            return self::is_plugin_active(static::index());
        }

        /**
         * Load plugin textdomain.
         */
        private static function load_textdomain()
        {
            $data = static::data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            load_plugin_textdomain(
                static::textdomain(),
                false,
                dirname(static::index()) . $domain_path,
            );
        }
    }
}

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
            return is_plugin_active($plugin_name);
        }

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
        protected function construct(...$args)
        {
            if (empty(static::$name) || empty(static::$textdomain)) {
                throw new Exception('Bad plugin initialization');
            }

            if (isset(static::$settings_class)) {
                $this->settings = static::$settings_class::get_instance(static::$textdomain);

                if (isset(static::$menu_class) && static::is_active()) {
                    $this->menu = static::$menu_class::get_instance(static::$name, static::$textdomain, $this->settings);
                }
            }

            add_action('init', function () {
                $this->init();
                static::load_textdomain();
            });

            add_filter('load_textdomain_mofile', function ($mofile, $domain) {
                return static::load_mofile($mofile, $domain);
            }, 10, 2);

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

                    $url = admin_url('options-general.php?page=' . static::$textdomain);
                    $label = __('Settings');
                    $link = "<a href='{$url}'>{$label}</a>";
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
            return static::$name;
        }

        /**
         * Plugin index getter.
         *
         * @return string $index Plugin index file path.
         */
        public static function index()
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
        public static function textdomain()
        {
            return static::$textdomain;
        }

        /**
         * Plugin data getter.
         *
         * @return array $data Plugin data.
         */
        public static function data()
        {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugins = get_plugins();
            $plugin_name = static::index();
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
                static::$textdomain,
                false,
                dirname(static::index()) . $domain_path,
            );
        }

        /**
         * Load plugin mofile.
         *
         * @param string $mofile Plugin mofile path.
         * @param string $domain Plugin textdomain.
         * @return string $mofile Plugin mofile path.
         */
        private static function load_mofile($mofile, $domain)
        {
            if ($domain === static::$textdomain && strpos($mofile, WP_LANG_DIR . '/plugins/') === false) {
                $data = static::data();
                $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

                $locale = apply_filters('plugin_locale', determine_locale(), $domain);
                $mofile = WP_PLUGIN_DIR . '/' . dirname(static::index()) . $domain_path . '/' . $domain . '-' . $locale . '.mo';
            }

            return $mofile;
        }
    }
}

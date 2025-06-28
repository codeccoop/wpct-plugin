<?php

namespace WPCT_PLUGIN;

use ReflectionClass;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_PLUGIN\Plugin')) {
    require_once 'class-singleton.php';
    require_once 'class-menu.php';
    require_once 'class-settings-form.php';
    require_once 'class-settings-store.php';

    /**
     * Plugin abstract class.
     */
    class Plugin extends Singleton
    {
        /**
         * Handles plugin's menu class name.
         *
         * @var string
         */
        protected static $menu_class = '\WPCT_PLUGIN\Menu';

        /**
         * Handles plugin's settings store class name.
         *
         * @var string
         */
        protected static $store_class = '\WPCT_PLUGIN\Settings_Store';

        /**
         * Handles plugin's settings class name.
         *
         * @var string
         */
        protected static $settings_form_class = '\WPCT_PLUGIN\Settings_Form';

        /**
         * Handles plugin's headers data.
         *
         * @var string
         */
        private static $data;

        /**
         * Handles plugin's settings store instance.
         *
         * @var Settings_Store
         */
        private $store;

        /**
         * Handles plugin's menu instance.
         *
         * @var Menu
         */
        private $menu;

        /**
         * Handles plugin's settings ui instance.
         *
         * @var Settings_Form
         */
        private $settings_form;

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
            return static::get_instance(...$args);
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
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            return is_plugin_active($plugin_name);
        }

        /**
         * Plugin constructor. Bind plugin to wp init hook and load textdomain.
         */
        protected function construct(...$args)
        {
            if (isset(static::$store_class)) {
                $this->store = static::$store_class::get_instance(
                    static::slug()
                );

                if (static::is_active()) {
                    if (isset(static::$menu_class)) {
                        $this->menu = static::$menu_class::get_instance(
                            static::name(),
                            static::slug(),
                            $this->store
                        );

                        $this->settings_form = static::$settings_form_class::get_instance($this->store);
                    }
                }
            }

            add_action('init', function () {
                static::init();
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

                    $url = admin_url(
                        'options-general.php?page=' . static::slug()
                    );
                    $label = __('Settings', 'wpct-plugin');
                    $link = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($url),
                        esc_html($label)
                    );

                    array_push($links, $link);
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
        final public static function name()
        {
            return static::data()['Name'];
        }

        /**
         * Plugin slug getter.
         *
         * @return string $slug Plugin's textdomain alias.
         */
        final public static function slug()
        {
            return pathinfo(static::index())['filename'];
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
            return static::data()['TextDomain'];
        }

        /**
         * Plugin version getter.
         *
         * @return string Plugin's version.
         */
        final public static function version()
        {
            return static::data()['Version'];
        }

        /**
         * Plugin dependencies getter.
         *
         * @return array Plugin's dependencies.
         */
        final public static function dependencies()
        {
            $dependencies = static::data()['RequiresPlugins'];
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
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin_dir = static::path() . basename(static::index());
            return get_plugin_data($plugin_dir, false, false);
        }

        /**
         * Active state getter.
         *
         * @return boolean $is_active Plugin active state.
         */
        final public static function is_active()
        {
            return static::is_plugin_active(static::index());
        }

        /**
         * Load plugin textdomain.
         */
        private static function load_textdomain()
        {
            $data = static::data();
            $domain_path =
                isset($data['DomainPath']) && !empty($data['DomainPath'])
                    ? $data['DomainPath']
                    : '/languages';

            load_plugin_textdomain(
                static::textdomain(),
                false,
                dirname(static::index()) . $domain_path
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

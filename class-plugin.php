<?php

namespace WPCT_ABSTRACT;

use Exception;

if (!class_exists('Plugin')) :

    abstract class Plugin extends Singleton
    {
        protected static $menu;
        protected static $textdomain;
        protected static $name;
        protected static $index;

        abstract public function init();

        abstract public static function activate();

        abstract public static function deactivate();

        public function __construct()
        {
            if (empty($this->name) || empty($this->textdomain)) {
                throw new Exception('Bad plugin initialization');
            }

            $this->menu = self::$menu::get_instance(self::$name);

            add_action('init', [$this, 'init'], 10);
            add_action('init', function () {
                $this->load_textdomain();
            }, 5);

            add_filter('load_textdomain_mofile', function ($mofile, $domain) {
                return $this->load_mofile($mofile, $domain);
            }, 10, 2);

            add_filter('wpct_is_plugin_active', function ($_, $plugin_name) {
                return self::is_active($plugin_name);
            }, 1);
        }

        public function get_menu()
        {
            return $this->menu;
        }

        public function get_name()
        {
            return $this->name;
        }

        public function get_index()
        {
            $index = self::$index;
            if (empty($index)) {
                $index = sanitize_title(self::$name);
            }

            return plugin_basename(dirname(__FILE__, 2) . '/' . $index);
        }

        public function get_textdomain()
        {
            return $this->textdomain;
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

        private function load_textdomain()
        {
            $data = $this->get_data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            load_plugin_textdomain(
                $this->textdomain,
                false,
                dirname($this->index) . $domain_path,
            );
        }

        private function load_mofile($mofile, $domain)
        {
            $data = $this->get_data();
            $domain_path = isset($data['DomainPath']) && !empty($data['DomainPath']) ? $data['DomainPath'] : '/languages';

            if ($domain === $this->textdomain && strpos($mofile, WP_LANG_DIR . '/plugins/') === false) {
                $locale = apply_filters('plugin_locale', determine_locale(), $domain);
                $mofile = dirname($this->index) . $domain_path . '/' . $domain . '-' . $locale . '.mo';
            }

            return $mofile;
        }

        private function is_active($plugin_name)
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
    }

endif;

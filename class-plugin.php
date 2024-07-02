<?php

namespace WPCT_ABSTRACT;

use WPCT_HTTP\Menu as Menu;
use WPCT_HTTP\Settings as Settings;

use Exception;

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
}

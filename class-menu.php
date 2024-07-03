<?php

namespace WPCT_ABSTRACT;

if (!class_exists('\WPCT_ABSTRACT\Menu')) :

    abstract class Menu extends Singleton
    {
        protected static $settings_class = '\WPCT_ABSTRACT\Settings';

        protected $name;
        protected $slug;
        protected $settings;

        abstract protected function render_page();

        public function __construct($name, $slug)
        {
            $this->name = $name;
            $this->slug = $slug;
            $this->settings = static::$settings_class::get_instance($slug);

            add_action('admin_menu', function () {
                $this->add_menu();
            });

            add_action('admin_init', function () {
                $this->settings->register();
            });
        }

        private function add_menu()
        {
            add_options_page(
                $this->name, // page title
                __($this->name . ' Options', 'wpct'), // menu name
                'manage_options', // capabilities
                $this->slug, // menu slug
                function () { // render callback
                    $this->render_page();
                }
            );
        }

        public function get_name()
        {
            return $this->name;
        }

        public function get_slug()
        {
            return $this->slug;
        }

        public function get_settings()
        {
            return $this->settings;
        }
    }

endif;

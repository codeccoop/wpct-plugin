<?php

namespace WPCT_PLUGIN;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_PLUGIN\Menu')) {
    require_once 'class-singleton.php';

    /**
     * Plugin menu abstract class.
     */
    class Menu extends Singleton
    {
        /**
         * Handle menu name.
         *
         * @var string
         */
        private $name;

        /**
         * Handle menu slug.
         *
         * @var string
         */
        private $slug;

        /**
         * Handle plugin settings store instance.
         *
         * @var Settings_Store
         */
        private $store;

        /**
         * Class constructor. Set attributes and hooks to wp admin hooks.
         *
         * @param string $name Plugin's name.
         * @param string $slug Plugin's slug.
         * @param array $store Plugin's settings store instances.
         */
        protected function construct(...$args)
        {
            [$name, $slug, $store] = $args;
            $this->name = $name;
            $this->slug = $slug;
            $this->store = $store;

            add_action('admin_menu', function () {
                static::add_menu();
                do_action('wpct_plugin_register_menu', $this->name, $this);
            });
        }

        /**
         * Register plugin options page.
         */
        private static function add_menu()
        {
            add_options_page(
                static::name(),
                static::name(),
                'manage_options',
                static::slug(),
                static function () {
                    static::render_page();
                }
            );
        }

        /**
         * Render menu page HTML.
         *
         * @param boolean $echo Should put render to the output buffer.
         * @return string|null $render Page content.
         */
        protected static function render_page($echo = true)
        {
            $store_settings = static::store()->settings();

            $tabs = [];
            foreach ($store_settings as $setting) {
                $setting_name = $setting->full_name();
                $tabs[$setting_name] = esc_html(
                    static::tab_title($setting_name)
                );
            }

            $current_tab = isset($_GET['tab'])
                ? sanitize_text_field(wp_unslash($_GET['tab']))
                : array_key_first($tabs);

            ob_start();
            ?>
			<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
				<form method="post" action="options.php">
					<nav class="nav-tab-wrapper">
					<?php foreach ($tabs as $tab => $name) {
					    $current = $tab === $current_tab ? 'nav-tab-active' : '';
					    $url = add_query_arg(['page' => static::slug(), 'tab' => $tab], '');
					    printf(
					        '<a class="nav-tab %s" href="%s">%s</a>',
					        esc_attr($current),
					        esc_url($url),
					        esc_html($name)
					    );
					} ?>
					</nav>
				<?php
                settings_fields($current_tab);
            do_settings_sections($current_tab);
            submit_button();
            ?>
				</form>
			</div>
			<?php
            $output = ob_get_clean();

            if ($echo) {
                echo $output;
            }

            return $output;
        }

        /**
         * Menu name getter.
         *
         * @return string $name Menu name.
         */
        final public static function name()
        {
            return static::get_instance()->name;
        }

        /**
         * Menu slug getter.
         *
         * @return string $slug Menu slug.
         */
        final public static function slug()
        {
            return static::get_instance()->slug;
        }

        /**
         * Menu settings store getter.
         *
         * @return Settings_Store Plugin settings store instance.
         */
        final public static function store()
        {
            return static::get_instance()->store;
        }

        /**
         * To be overwriten by the child class.
         *
         * @param string $setting_name
         *
         * @return string
         */
        protected static function tab_title($setting_name)
        {
            return $setting_name;
        }

        public static function is_admin_current_page()
        {
            if (is_admin()) {
                $page = isset($_GET['page'])
                    ? sanitize_text_field($_GET['page'])
                    : null;
                $slug = static::slug();
                return $page && $page === $slug;
            }

            return false;
        }
    }
}

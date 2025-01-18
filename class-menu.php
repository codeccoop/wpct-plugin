<?php

namespace WPCT_ABSTRACT;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Menu')) {

    require_once 'class-singleton.php';

    /**
     * Plugin menu abstract class.
     */
    abstract class Menu extends Singleton
    {
        /**
         * Handle menu name.
         *
         * @var string
         */
        private static $name;

        /**
         * Handle menu slug.
         *
         * @var string
         */
        private static $slug;

        /**
         * Handle plugin settings instance.
         *
         * @var Settings
         */
        private static $settings;

        /**
         * Class constructor. Set attributes and hooks to wp admin hooks.
         *
         * @param string $name Plugin's name.
         * @param string $slug Plugin's slug.
         * @param array $settings Plugin's setting instances.
         */
        protected function construct(...$args)
        {
            [$name, $slug, $settings] = $args;
            static::$name = $name;
            static::$slug = $slug;
            static::$settings = $settings;

            add_action('admin_menu', function () {
                static::add_menu();
                do_action('wpct_register_menu', static::$name, $this);
            });
        }

        /**
         * Register plugin options page.
         */
        private static function add_menu()
        {
            add_options_page(
                static::$name,
                static::$name,
                'manage_options',
                static::$slug,
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
            $page_settings = static::$settings->settings();
            $tabs = array_reduce($page_settings, static function ($carry, $setting) {
                $setting_name = $setting->full_name();
                /* translators: %s: Setting name */
                $carry[$setting_name] = sprintf(esc_html__('%s--title', 'wpct-plugin-abstracts'), $setting_name);
                return $carry;
            }, []);
            $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : array_key_first($tabs);
            ob_start();
            ?>
			<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()) ?></h1>
				<form method="post" action="options.php">
					<nav class="nav-tab-wrapper">
					<?php foreach ($tabs as $tab => $name) {
					    $current = $tab === $current_tab ? 'nav-tab-active' : '';
					    $url = add_query_arg(['page' => static::$slug, 'tab' => $tab], '');
					    printf(
					        '<a class="nav-tab %s" href="%s">%s</a>',
					        esc_attr($current),
					        esc_url($url),
					        esc_html($name),
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
            return static::$name;
        }

        /**
         * Menu slug getter.
         *
         * @return string $slug Menu slug.
         */
        final public static function slug()
        {
            return static::$slug;
        }

        /**
         * Menu settings getter.
         *
         * @return object $settings Plugin settings instance.
         */
        final public static function settings()
        {
            return static::$settings;
        }
    }
}

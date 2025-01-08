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
         * @var string $name Menu name.
         */
        private $name;

        /**
         * Handle menu slug.
         *
         * @var string $settings_class Settings class name.
         */
        private $slug;

        /**
         * Handle plugin settings instance.
         *
         * @var object $settings Settings instance.
         */
        private $settings;

        /**
         * Class constructor. Set attributes and hooks to wp admin hooks.
         *
         * @param string $name Plugin name.
         * @param string $slug Plugin slug.
         */
        protected function construct(...$args)
        {
            [$name, $slug, $settings] = $args;
            $this->name = $name;
            $this->slug = $slug;
            $this->settings = $settings;

            add_action('admin_menu', function () {
                $this->add_menu();
                do_action('wpct_register_menu', $this->name, $this);
            });
        }

        /**
         * Register plugin options page.
         */
        private function add_menu()
        {
            add_options_page(
                $this->name,
                $this->name,
                'manage_options',
                $this->slug,
                function () {
                    $this->render_page();
                }
            );
        }

        /**
         * Render menu page HTML.
         *
         * @param boolean $echo Should put render to the output buffer.
         * @return string|null $render Page content.
         */
        protected function render_page($echo = true)
        {
            $page_settings = $this->settings->settings();
            $tabs = array_reduce($page_settings, function ($carry, $setting) {
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
					    $url = add_query_arg(['page' => $this->slug, 'tab' => $tab], '');
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
        public function name()
        {
            return $this->name;
        }

        /**
         * Menu slug getter.
         *
         * @return string $slug Menu slug.
         */
        public function slug()
        {
            return $this->slug;
        }

        /**
         * Menu settings getter.
         *
         * @return object $settings Plugin settings instance.
         */
        public function settings()
        {
            return $this->settings;
        }
    }
}

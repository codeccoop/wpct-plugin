<?php

namespace WPCT_ABSTRACT;

if (!class_exists('\WPCT_ABSTRACT\Menu')) :

    /**
     * Plugin menu abstract class.
     *
     * @since 1.0.0
     */
    abstract class Menu extends Singleton
    {
        /**
         * Handle plugin settings class name.
         *
         * @since 1.0.0
         *
         * @var string $settings_class Settings class name.
         */
        protected static $settings_class = '\WPCT_ABSTRACT\Settings';

        /**
         * Handle menu name.
         *
         * @since 1.0.0
         *
         * @var string $name Menu name.
         */
        protected $name;

        /**
         * Handle menu slug.
         *
         * @since 1.0.0
         *
         * @var string $settings_class Settings class name.
         */
        protected $slug;

        /**
         * Handle plugin settings instance.
         *
         * @since 1.0.0
         *
         * @var object $settings Settings instance.
         */
        protected $settings;

        /**
         * Class constructor. Set attributes and hooks to wp admin hooks.
         *
         * @since 1.0.0
         *
         * @param string $name Plugin name.
         * @param string $slug Plugin textdomain.
         */
        public function __construct($name, $slug)
        {
            $this->name = $name;
            $this->slug = $slug;
            $this->settings = static::$settings_class::get_instance($slug);

            add_action('admin_menu', function () {
                $this->add_menu();
            });

            add_action('init', function () {
                $this->settings->register();
            });
        }

        /**
         * Register plugin options page.
         *
         * @since 1.0.0
         */
        private function add_menu()
        {
            add_options_page(
                $this->name,
                __($this->name, $this->slug),
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
         * @since 1.0.0
         *
         * @param boolean $echo Should put render to the output buffer.
         * @return string|null $render Page content.
         */
        protected function render_page($echo = true)
        {
            $page_settings = $this->settings->get_settings();
            $tabs = array_reduce($page_settings, function ($carry, $setting) {
                $carry[$setting] = __($setting . '--title', $this->slug);
                return $carry;
            }, []);
            $current_tab = isset($_GET['tab']) ? $_GET['tab'] : array_key_first($tabs);
            ob_start();
            ?>
			<div class="wrap">
			<h1><?= get_admin_page_title() ?></h1>
				<form method="post" action="options.php">
					<nav class="nav-tab-wrapper">
					<?php foreach ($tabs as $tab => $name) {
					    $current = $tab === $current_tab ? ' nav-tab-active' : '';
					    $url = add_query_arg(['page' => $this->slug, 'tab' => $tab], '');
					    echo "<a class=\"nav-tab{$current}\" href=\"{$url}\">{$name}</a>";
					} ?>
					</nav>
					<?php
					    settings_fields("{$current_tab}");
            do_settings_sections("{$current_tab}");
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
         * @since 1.0.0
         *
         * @return string $name Menu name.
         */
        public function get_name()
        {
            return $this->name;
        }

        /**
         * Menu slug getter.
         *
         * @since 1.0.0
         *
         * @return string $slug Menu slug.
         */
        public function get_slug()
        {
            return $this->slug;
        }

        /**
         * Menu settings getter.
         *
         * @since 1.0.0
         *
         * @return object $settings Plugin settings instance.
         */
        public function get_settings()
        {
            return $this->settings;
        }
    }

endif;

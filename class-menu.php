<?php

namespace WPCT_ABSTRACT;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Menu')) {

    require_once 'class-singleton.php';
    require_once 'class-settings.php';

    /**
     * Plugin menu abstract class.
     */
    abstract class Menu extends Singleton
    {
        /**
         * Handle plugin settings class name.
         *
         * @var string $settings_class Settings class name.
         */
        protected static $settings_class = '\WPCT_ABSTRACT\Settings';

        /**
         * Handle menu name.
         *
         * @var string $name Menu name.
         */
        protected $name;

        /**
         * Handle menu slug.
         *
         * @var string $settings_class Settings class name.
         */
        protected $slug;

        /**
         * Handle plugin settings instance.
         *
         * @var object $settings Settings instance.
         */
        protected $settings;

        /**
         * Class constructor. Set attributes and hooks to wp admin hooks.
         *
         * @param string $name Plugin name.
         * @param string $slug Plugin textdomain.
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
         * @param boolean $echo Should put render to the output buffer.
         * @return string|null $render Page content.
         */
        protected function render_page($echo = true)
        {
            $page_settings = $this->settings->settings();
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

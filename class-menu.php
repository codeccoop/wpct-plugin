<?php

namespace WPCT_ABSTRACT;

if (!class_exists('\WPCT_ABSTRACT\Menu')) :

    abstract class Menu extends Singleton
    {
        protected static $settings_class = '\WPCT_ABSTRACT\Settings';

        protected $name;
        protected $slug;
        protected $settings;

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

        protected function render_page()
        {
            $page_settings = $this->settings->get_settings();
            $tabs = array_reduce($page_settings, function ($carry, $setting) {
                $carry[$setting] = __($setting . '--title', $this->slug);
                return $carry;
            }, []);
            $current_tab = isset($_GET['tab']) ? $_GET['tab'] : array_key_first($tabs);
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

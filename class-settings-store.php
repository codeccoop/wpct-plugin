<?php

namespace WPCT_ABSTRACT;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Settings_Store')) {

    require_once 'class-singleton.php';
    require_once 'class-setting.php';
    require_once 'class-rest-settings-controller.php';
    require_once 'class-undefined.php';

    /**
     * Plugin settings abstract class.
     */
    abstract class Settings_Store extends Singleton
    {
        /**
         * Handle plugin settings rest controller class name.
         *
         * @var string
         */
        protected static $rest_controller_class = '\WPCT_ABSTRACT\REST_Settings_Controller';

        /**
         * Handle settings group name.
         *
         * @var string
         */
        private $group;

        /**
         * Handle settings instanes store.
         *
         * @var array
         */
        private $store = [];

        private static function store($setting_name, $setting)
        {
            static::get_instance()->store[$setting_name] = $setting;
        }

        /**
         * Register settings method.
         */
        abstract public static function config();

        /**
         * Validates setting data before database inserts.
         *
         * @param array $data Setting data.
         * @param Setting $setting Setting instance.
         *
         * @return array $value Validated setting data.
         */
        abstract protected static function validate_setting($data, $setting);

        /**
         * Escape admin fields html.
         *
         * @param string $html Output rendered field.
         *
         * @return string Escaped html.
         */
        public static function kses($html)
        {
            return wp_kses($html, [
                'table' => [
                    'id' => [],
                    'class' => [],
                ],
                'th' => [],
                'tr' => [],
                'td' => [],
                'input' => [
                    'type' => [],
                    'value' => [],
                    'name' => [],
                    'id' => [],
                    'checked' => [],
                ],
                'select' => [
                    'name' => [],
                    'id' => [],
                    'multiple' => [],
                    'required' => [],
                ],
                'option' => [
                    'value' => [],
                    'selected' => [],
                ],
                'div' => [
                    'id' => [],
                    'class' => [],
                ],
                'button' => [
                    'class' => [],
                    'data-action' => [],
                ]
            ]);
        }

        /**
         * Private setting sanitization method.
         *
         * @param array $data Setting data.
         * @param string $name Setting name.
         *
         * @return array Sanitized data.
         */
        private static function sanitize_setting($data, $name)
        {
            $instance = static::get_instance();
            if ($instance->sanitizing === $name) {
                return $data;
            }

            $instance->sanitizing = $name;
            $setting = static::setting($name);
            $schema = $setting->schema();

            $data = static::validate_setting($data, $setting);
            $data = apply_filters('wpct_validate_setting', $data, $setting);

            $sanitized = [];
            foreach ($data as $field => $value) {
                if (!isset($schema['properties'][$field])) {
                    continue;
                }

                $sanitized[$field] = self::sanitize_value($schema['properties'][$field], $value);
            }

            $full_name = $setting->full_name();
            add_action("add_option_{$full_name}", static function () use ($instance) {
                $instance->sanitizing = null;
            }, 10, 0);

            add_action("update_option_{$full_name}", static function () use ($instance) {
                $instance->sanitizing = null;
            }, 10, 0);

            $setting->flush();
            return $sanitized;
        }

        /**
         * Sanitize value by schema type.
         *
         * @param array $schema Field schema.
         * @aram mixed $value Field value.
         *
         * @return mixed Sanitized field value.
         */
        private static function sanitize_value($schema, $value)
        {
            switch ($schema['type']) {
                case 'string':
                    $value = sanitize_text_field($value);
                    if (isset($schema['enum'])) {
                        $value = in_array($value, $schema['enum']) ? $value : null;
                    }

                    if (is_string($value) && isset($schema['minLength'])) {
                        $value = strlen($value) >= $schema['minLength'] ? $value : null;
                    }

                    if (is_string($value) && isset($schema['maxLength'])) {
                        $value = strlen($value) <= $schema['maxLength'] ? $value : null;
                    }

                    return $value;
                case 'number':
                    $value = (float) $value;

                    if (is_float($value) && isset($schema['minimum'])) {
                        $value = $value >= $schema['minimum'] ? $value : null;
                    }

                    if (is_float($value) && isset($schema['maximum'])) {
                        $value = $value <= $schema['maximum'] ? $value : null;
                    }

                    return $value;
                case 'integer':
                    $value = (int) $value;

                    if (is_int($value) && isset($schema['minimum'])) {
                        $value = $value >= $schema['minimum'] ? $value : null;
                    }

                    if (is_int($value) && isset($schema['maximum'])) {
                        $value = $value <= $schema['maximum'] ? $value : null;
                    }

                    return $value;
                case 'null':
                    return null;
                case 'boolean':
                    return (bool) $value;
                case 'array':
                    if (!wp_is_numeric_array($value)) {
                        return [];
                    }

                    $value = array_map(static function ($item) use ($schema) {
                        return self::sanitize_value($schema['items'], $item);
                    }, array_values($value));

                    if (isset($schema['enum']) && is_array($schema['enum'])) {
                        $value = array_values(array_filter($value, static function ($item) use ($schema) {
                            return in_array($item, $schema['enum'], true);
                        }));
                    }

                    if ($schema['uniqueItems'] ?? false) {
                        $value = array_unique($value);
                    }

                    return $value;
                case 'object':
                    if (!is_array($value)) {
                        return [];
                    }

                    return array_reduce(array_keys($value), static function ($sanitized, $key) use ($schema, $value) {
                        $additionals = $schema['additionalProperties'] ?? false;

                        if (isset($schema['properties'][$key]) || $additionals) {
                            $sanitized[$key] = self::sanitize_value($schema['properties'][$key], $value[$key]);
                        }

                        return $sanitized;
                    }, []);
            }
        }

        private $sanitizing = null;

        /**
         * Class constructor. Store the group name and hooks to pre_update_option.
         *
         * @param string $group Settings group name.
         */
        protected function construct(...$args)
        {
            [$group] = $args;
            $this->group = $group;

            static::$rest_controller_class::setup($group);

            add_action('init', static function () use ($group) {
                $settings = static::register_settings();
                do_action('wpct_register_settings', $settings, $group);
            });
        }

        /**
         * Get settings group name.
         *
         * @return string $group_name Settings group name.
         */
        final public static function group()
        {
            return static::get_instance()->group;
        }

        /**
         * Return group settings instances.
         *
         * @return array Group settings.
         */
        final public static function settings()
        {
            return static::get_instance()->store;
        }

        final public static function setting($name)
        {
            $store = static::settings();
            if (empty($store)) {
                return;
            }

            return $store[$name] ?? null;
        }

        /**
         * Registers a setting and its fields.
         *
         * @return array List with setting instances.
         */
        private static function register_settings()
        {
            $config = apply_filters('wpct_settings_config', static::config(), static::group());

            $settings = [];
            foreach ($config as $setting_config) {
                $group = static::group();
                [$name, $schema, $default] = $setting_config;

                $setting = new Setting(
                    $group,
                    $name,
                    $default,
                    [
                        '$id' => $group . '_' . $name,
                        '$schema' => 'http://json-schema.org/draft-04/schema#',
                        'title' => "Setting {$name} of {$group}",
                        'type' => 'object',
                        'properties' => $schema,
                        'required' => array_keys($schema),
                        'additionalProperties' => false
                    ],
                );

                $setting_name = $setting->full_name();

                // Register setting
                register_setting(
                    $setting_name,
                    $setting_name,
                    [
                        'type' => 'object',
                        'show_in_rest' => [
                            'name' => $setting_name,
                            'schema' => $setting->schema(),
                        ],
                        'sanitize_callback' => function ($value) use ($name) {
                            return static::sanitize_setting($value, $name);
                        },
                        'default' => $setting->default(),
                    ],
                );

                static::store($name, $setting);

                // Add settings section on admin init
                add_action('admin_init', function () use ($setting, $default) {
                    $setting_name = $setting->full_name();

                    $section_name = $setting_name . '_section';
                    $section_label = esc_html(static::setting_title($setting_name));

                    add_settings_section(
                        $section_name,
                        $section_label,
                        static function () use ($setting_name) {
                            $description = esc_html(static::setting_description($setting_name));
                            printf('<p>%s</p>', esc_html($description));
                        },
                        $setting_name,
                    );

                    foreach (array_keys($default) as $field) {
                        static::add_setting_field($setting, $field);
                    }
                });

                add_action('admin_enqueue_scripts', function () {
                    $plugin_url = plugin_dir_url(__FILE__);

                    wp_enqueue_script(
                        'wpct-fieldset-control',
                        $plugin_url . 'admin-form.js',
                        [],
                        '1.0.0',
                        ['in_footer' => true],
                    );

                    wp_enqueue_style(
                        'wpct-admin-style',
                        $plugin_url . 'admin-form.css',
                        [],
                        '1.0.0',
                    );
                });

                $settings[$setting->name()] = $setting;
            }

            return $settings;
        }

        /**
         * Registers a setting field.
         *
         * @param Setting $setting Setting name.
         * @param string $field Field name.
         */
        private static function add_setting_field($setting, $field)
        {
            $setting_name = $setting->full_name();
            $field_label = esc_html(static::field_label($field, $setting_name));

            add_settings_field(
                $field,
                $field_label,
                static function () use ($setting, $field) {
                    $setting_name = $setting->full_name();
                    $schema = $setting->schema($field);
                    $value = $setting->data($field);

                    echo static::kses(static::field_render($setting_name, $field, $schema, $value));
                },
                $setting_name,
                $setting_name . '_section',
            );
        }

        /**
         * Renders the field HTML.
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param array $schema Field schema.
         * @param mixed $value Field value.
         *
         * @return string
         */
        private static function field_render($setting, $field, $schema, $value)
        {
            if (!in_array($schema['type'], ['array', 'object'])) {
                return static::input_render($setting, $field, $schema, $value);
            } elseif ($schema['type'] === 'array' && isset($schema['enum'])) {
                return self::input_render($setting, $field, $schema, $value);
            } else {
                $fieldset = static::fieldset_render($setting, $field, $schema, $value);
                if ($schema['type'] === 'array') {
                    $fieldset .= static::control_render($setting, $field);
                }
                return $fieldset;
            }
        }

        /**
         * Render input HTML.
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param array $schema Field schema.
         * @param mixed $value Field value.
         *
         * @return string
         */
        protected static function input_render($setting, $field, $schema, $value)
        {
            if ($schema['type'] === 'boolean') {
                return sprintf(
                    '<input type="checkbox" name="%s" ' . ($value ? 'checked="true"' : '') . ' />',
                    esc_attr($setting . "[{$field}]"),
                );
            } elseif ($schema['type'] === 'string' && isset($schema['enum'])) {
                $options = implode('', array_map(function ($opt) use ($value) {
                    $is_selected = $value === $opt;
                    return sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($opt),
                        $is_selected ? 'selected' : '',
                        esc_html($opt),
                    );
                }, (array) $schema['enum']));

                return sprintf(
                    '<select name="%s">%s</select>',
                    esc_attr($setting . "[{$field}]"),
                    $options,
                );
            } elseif ($schema['type'] === 'array' && isset($schema['enum'])) {
                $options = implode('', array_map(function ($opt) use ($value) {
                    $is_selected = in_array($opt, $value);
                    return sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($opt),
                        $is_selected ? 'selected' : '',
                        esc_html($opt),
                    );
                }, (array) $schema['enum']));

                return sprintf(
                    '<select name="%s[]" multiple required>%s</select>',
                    esc_attr($setting . "[{$field}]"),
                    $options,
                );

            } else {
                return sprintf(
                    '<input type="text" name="%s" value="%s" />',
                    esc_attr($setting . "[{$field}]"),
                    esc_attr($value),
                );
            }
        }

        /**
         * Render fieldset HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param array $schema Field schema.
         * @param mixed $value Field value.
         *
         * @return string $html Fieldset HTML.
         */
        private static function fieldset_render($setting, $field, $schema, $value)
        {
            $is_list = $schema['type'] === 'array';

            $table_id = $setting . '__' . str_replace('][', '_', $field);
            $fieldset = '<table id="' . esc_attr($table_id) . '"';

            if ($is_list) {
                $fieldset .= ' class="is-list"';
            }

            $fieldset .= '>';

            foreach (array_keys($value) as $key) {
                $fieldset .= '<tr>';

                if (!$is_list) {
                    $fieldset .= '<th>' . esc_html($key) . '</th>';
                } else {
                    $key = (int) $key;
                }

                if ($schema['type'] === 'object') {
                    $sub_schema = $schema['properties'][$key];
                } else {
                    $sub_schema = $schema['items'];
                }

                $sub_value = $value[$key];
                $sub_field = $field . '][' . $key;

                $fieldset .= sprintf("<td>%s</td></td>", self::field_render($setting, $sub_field, $sub_schema, $sub_value));
            }

            return $fieldset . '</table>';
        }

        /**
         * Render control HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         *
         * @return string $html Control HTML.
         */
        private static function control_render($setting, $field)
        {
            $field_id = str_replace('][', '_', $field);
            ob_start();
            ?>
			<div id="<?php echo esc_attr($setting . '__' . $field_id . '--controls'); ?>" class="wpct-fieldset-control">
				<button class="button button-primary" data-action="add"><?php _e('Add'); ?></button>
				<button class="button button-secondary" data-action="remove"><?php _e('Remove'); ?></button>
			</div>
			<?php

            return ob_get_clean();
        }

        /**
         * To be overwriten by the child class. Should return the localized setting title
         * for the menu page.
         *
         * @param string $setting_name
         *
         * @return string
         */
        protected static function setting_title($setting_name)
        {
            return $setting_name;
        }

        /**
         * To be overwriten by the child class. Should return the localized setting description
         * for the menu page.
         *
         * @param string $setting_name
         *
         * @return string
         */
        protected static function setting_description($setting_name)
        {
            return 'Setting description';
        }

        /**
         * To be overwriten by the child class. Should return the localized
         * field label for the menu page.
         *
         * @param string $field_name Name of the field.
         * @param string $setting_name Name of the parent setting.
         *
         * @return string
         */
        protected static function field_label($field_name, $setting_name)
        {
            return $field_name;
        }
    }
}

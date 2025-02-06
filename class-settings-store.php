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
                ],
                'th' => [],
                'tr' => [],
                'td' => [],
                'input' => [
                    'value' => [],
                    'name' => [],
                    'id' => [],
                    'checked' => [],
                ],
                'select' => [
                    'name' => [],
                    'id' => [],
                    'multiple' => [],
                ],
                'option' => [
                    'value' => [],
                ],
                'div' => [
                    'id' => [],
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
                    return $value;
                case 'number':
                    return (float) $value;
                case 'integer':
                    return (int) $value;
                case 'null':
                    return null;
                case 'boolean':
                    return (bool) $value;
                case 'array':
                    return array_map(static function ($item) use ($schema) {
                        return self::sanitize_value($schema['items'], $item);
                    }, array_values($value));
                    break;
                case 'object':
                    return array_reduce(array_keys($value), static function ($sanitized, $key) use ($schema, $value) {
                        if (isset($schema['properties'][$key])) {
                            $sanitized[$key] = self::sanitize_value($schema['properties'][$key], $value[$key]);
                        }
                        return $sanitized;
                    }, []);
            }
        }

        private $sanitizing = null;

        private static function reset_sanitizing()
        {
            $instance = static::get_instance();
            if (!empty($instance->sanitizing)) {
                $instance->sanitizing = null;
            }
        }

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
                    /* translators: %s: Setting name */
                    $section_label = sprintf(__('%s--title', 'wpct-plugin-abstracts'), $setting_name);
                    add_settings_section(
                        $section_name,
                        $section_label,
                        function () use ($setting_name) {
                            /* translators: %s: Setting name */
                            $title = sprintf(__('%s--description', 'wpct-plugin-abstracts'), $setting_name);
                            printf('<p>%s</p>', esc_html($title));
                        },
                        $setting_name,
                    );

                    foreach (array_keys($default) as $field) {
                        static::add_setting_field($setting, $field);
                    }
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
            $field_id = $setting_name . '__' . $field;
            /* translators: %s: Setting name concatenated with two underscores with the field name */
            $field_label = sprintf(__('%s--label', 'wpct-plugin-abstracts'), $field_id);

            add_settings_field(
                $field,
                $field_label,
                function () use ($setting, $field) {
                    echo static::kses(static::field_render($setting, $field));
                },
                $setting_name,
                $setting_name . '_section',
                [
                    'class' => $field_id,
                ]
            );
        }

        /**
         * Renders the field HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         *
         * @return string $html Input HTML.
         */
        protected static function field_render()
        {
            $args = func_get_args();
            $setting = $args[0];
            $field = $args[1];
            if (count($args) >= 3) {
                $value = $args[2];
            } else {
                $value = new Undefined();
            }

            return static::_field_render($setting, $field, $value);
        }

        /**
         * Renders the field HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         *
         * @return string $html Input HTML.
         */
        private static function _field_render($setting, $field, $value)
        {
            $is_root = false;
            if ($value instanceof Undefined) {
                $value = $setting->data($field);
                $is_root = true;
            }

            if (!is_array($value)) {
                return static::input_render($setting, $field, $value);
            } else {
                $fieldset = static::fieldset_render($setting, $field, $value);
                if ($is_root && wp_is_numeric_array($value)) {
                    static::control_style($setting, $field);
                    $fieldset .= static::control_render($setting, $field);
                }

                return $fieldset;
            }
        }

        /**
         * Render input HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string $value Field value.
         *
         * @return string $html Input HTML.
         */
        protected static function input_render($setting, $field, $value)
        {
            $setting_name = $setting->full_name();
            $keys = explode('][', $field);
            $schema = $setting->schema($keys[0]);
            $value = $setting->data($keys[0]);

            $is_list = wp_is_numeric_array($value);
            for ($i = 1; $i < count($keys); $i++) {
                $key = $keys[$i];
                if ($is_list) {
                    $key = (int) $key;
                }

                if ($schema['type'] === 'object') {
                    $schema = $schema['properties'][$key];
                } else {
                    $schema = $schema['items'];
                }

                $value = $value[$key];
                $is_list = wp_is_numeric_array($value);
            }
            $is_bool = is_bool($value);

            if ($is_bool) {
                return sprintf(
                    '<input type="checkbox" name="%s" ' . ($value ? 'checked' : '') . ' />',
                    esc_attr($setting_name . "[{$field}]"),
                );
            } elseif (isset($schema['enum'])) {
                $options = implode('', array_map(function ($opt) use ($value) {
                    $is_selected = is_array($value) ? in_array($opt, $value) : $value === $opt;
                    return sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($opt),
                        $is_selected ? 'selected' : '',
                        esc_html($opt),
                    );
                }, (array) $schema['enum']));
                $multi = is_array($value) ? 'multiple' : '';
                return sprintf(
                    '<select name="%s" %s>%s</select>',
                    esc_attr($setting_name . "[{$field}]"),
                    esc_attr($multi),
                    $options,
                );
            } else {
                return sprintf(
                    '<input type="text" name="%s" value="%s" />',
                    esc_attr($setting_name . "[{$field}]"),
                    esc_attr($value),
                );
            }
        }

        /**
         * Render fieldset HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param array $data Setting data.
         *
         * @return string $html Fieldset HTML.
         */
        private static function fieldset_render($setting, $field, $data)
        {
            $setting_name = $setting->full_name();
            $table_id = $setting_name . '__' . str_replace('][', '_', $field);
            $fieldset = '<table id="' . esc_attr($table_id) . '">';
            $is_list = wp_is_numeric_array($data);
            foreach (array_keys($data) as $key) {
                $fieldset .= '<tr>';
                if (!$is_list) {
                    $fieldset .= '<th>' . esc_html($key) . '</th>';
                }
                $_field = $field . '][' . $key;
                $fieldset .= sprintf("<td>%s</td>", self::field_render($setting, $_field, $data[$key]));
                $fieldset .= '</tr>';
            }
            $fieldset .= '</table>';

            return $fieldset;
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
            $value = $setting->data()[$field][0];

            add_action('admin_print_scripts', function () use (
                $setting,
                $field,
                $value
            ) {
                static::control_script($setting, $field, $value);
            });

            ob_start();
            ?>
			<div id="<?php echo esc_attr($setting->full_name() . '__' . $field . '--controls'); ?>">
				<button class="button button-primary" data-action="add">Add</button>
				<button class="button button-secondary" data-action="remove">Remove</button>
			</div>
			<?php

            return ob_get_clean();
        }

        /**
         * Include localized fieldset control script and return the buffer as string.
         *
         * @param Setting $setting Setting instance.
         * @param string $field_name Setting's field name.
         * @param mixed $field_value Setting's field value.
         *
         * @return string Fieldset control rendered script.
         */
        private static function control_script($setting, $field_name, $field_value)
        {
            include 'fieldset-control-js.php';
        }

        /**
         * Render control style tag.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         *
         * @return string $tag Style HTML tag with control styles.
         */
        private static function control_style($setting, $field)
        {
            $setting_name = $setting->full_name();
            add_action('admin_print_styles', function () use ($setting_name, $field) {
                printf(
                    '<style>#%1$s__%2$s td td,#%1$s__%2$s td th{padding:0}#%1$s__%2$s table table{margin-bottom:1rem}</style>',
                    esc_attr($setting_name),
                    esc_attr($field),
                );
            });
        }
    }
}

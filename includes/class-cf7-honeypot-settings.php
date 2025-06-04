<?php
class CF7_Honeypot_Settings
{
    private static $instance = null;
    private $options_name = 'cf7_honeypot_settings';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'cf7-honeypot-stats',
            __('Blocked IPs', 'cf7-honeypot'),
            __('Blocked IPs', 'cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-blocked-ips',
            array($this, 'render_blocked_ips_page')
        );
    }

    public function register_settings()
    {
        register_setting($this->options_name, $this->options_name, array($this, 'sanitize_settings'));

        // Registra le sezioni con lo stesso identificatore
        $sections = array(
            'protection' => __('Protection Settings', 'cf7-honeypot'),
            'notification' => __('Notification Settings', 'cf7-honeypot'),
            'customization' => __('Customization Settings', 'cf7-honeypot')
        );

        foreach ($sections as $id => $title) {
            add_settings_section(
                $id . '_settings',
                $title,
                null,
                'cf7-honeypot-settings'
            );
        }

        // Aggiungi i campi alle sezioni corrette
        $this->add_protection_fields('protection_settings');
        $this->add_notification_fields('notification_settings');
        $this->add_customization_fields('customization_settings');
    }

    private function add_protection_fields()
    {
        // Auto-block IP
        add_settings_field(
            'enable_auto_block',
            __('Auto-block IPs', 'cf7-honeypot'),
            array($this, 'render_checkbox_field'),
            'cf7-honeypot-settings',
            'protection_settings',
            array(
                'label_for' => 'enable_auto_block',
                'description' => __('Automatically block IPs after repeated spam attempts', 'cf7-honeypot')
            )
        );

        // Block Threshold
        add_settings_field(
            'block_threshold',
            __('Block Threshold', 'cf7-honeypot'),
            array($this, 'render_number_field'),
            'cf7-honeypot-settings',
            'protection_settings',
            array(
                'label_for' => 'block_threshold',
                'description' => __('Number of attempts before blocking an IP', 'cf7-honeypot'),
                'min' => 1,
                'max' => 100
            )
        );

        // Block Duration
        add_settings_field(
            'block_duration',
            __('Block Duration (hours)', 'cf7-honeypot'),
            array($this, 'render_number_field'),
            'cf7-honeypot-settings',
            'protection_settings',
            array(
                'label_for' => 'block_duration',
                'description' => __('How long to block IPs (in hours)', 'cf7-honeypot'),
                'min' => 1,
                'max' => 720
            )
        );

        // Country Blocking
        add_settings_field(
            'country_blocking',
            __('Country Blocking', 'cf7-honeypot'),
            array($this, 'render_country_blocking_field'),
            'cf7-honeypot-settings',
            'protection_settings',
            array(
                'label_for' => 'country_blocking',
                'description' => __('Block submissions from specific countries', 'cf7-honeypot')
            )
        );
    }

    public function render_textarea_field($args)
    {
        $options = get_option($this->options_name);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        if (is_array($value)) {
            $value = implode("\n", $value);
        }
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($this->options_name . '[' . $args['label_for'] . ']'); ?>" class="large-text"
            rows="5"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    private function add_notification_fields()
    {
        // Admin Notifications
        add_settings_field(
            'admin_notifications',
            __('Admin Notifications', 'cf7-honeypot'),
            array($this, 'render_checkbox_field'),
            'cf7-honeypot-settings',
            'notification_settings',
            array(
                'label_for' => 'admin_notifications',
                'description' => __('Send email notifications for blocked attempts', 'cf7-honeypot')
            )
        );

        // Notification Threshold
        add_settings_field(
            'notification_threshold',
            __('Notification Threshold', 'cf7-honeypot'),
            array($this, 'render_number_field'),
            'cf7-honeypot-settings',
            'notification_settings',
            array(
                'label_for' => 'notification_threshold',
                'description' => __('Minimum attempts before sending notification', 'cf7-honeypot'),
                'min' => 1,
                'max' => 100
            )
        );

        // Additional Recipients
        add_settings_field(
            'additional_recipients',
            __('Additional Recipients', 'cf7-honeypot'),
            array($this, 'render_textarea_field'),
            'cf7-honeypot-settings',
            'notification_settings',
            array(
                'label_for' => 'additional_recipients',
                'description' => __('Additional email addresses to notify (one per line)', 'cf7-honeypot')
            )
        );
    }

    private function add_customization_fields()
    {
        // Custom Questions
        add_settings_field(
            'custom_questions',
            __('Custom Questions', 'cf7-honeypot'),
            array($this, 'render_custom_questions_field'),
            'cf7-honeypot-settings',
            'customization_settings',
            array(
                'label_for' => 'custom_questions',
                'description' => __('Add custom honeypot questions', 'cf7-honeypot')
            )
        );

        // Custom Error Messages
        add_settings_field(
            'custom_error_message',
            __('Custom Error Message', 'cf7-honeypot'),
            array($this, 'render_text_field'),
            'cf7-honeypot-settings',
            'customization_settings',
            array(
                'label_for' => 'custom_error_message',
                'description' => __('Custom message shown when spam is detected', 'cf7-honeypot')
            )
        );

        // Form-specific Settings
        add_settings_field(
            'form_specific_settings',
            __('Form-specific Settings', 'cf7-honeypot'),
            array($this, 'render_form_specific_settings'),
            'cf7-honeypot-settings',
            'customization_settings',
            array(
                'label_for' => 'form_specific_settings',
                'description' => __('Configure settings for specific forms', 'cf7-honeypot')
            )
        );
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'protection';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=cf7-honeypot-settings&tab=protection"
                    class="nav-tab <?php echo $active_tab == 'protection' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Protection', 'cf7-honeypot'); ?>
                </a>
                <a href="?page=cf7-honeypot-settings&tab=notification"
                    class="nav-tab <?php echo $active_tab == 'notification' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Notifications', 'cf7-honeypot'); ?>
                </a>
                <a href="?page=cf7-honeypot-settings&tab=customization"
                    class="nav-tab <?php echo $active_tab == 'customization' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Customization', 'cf7-honeypot'); ?>
                </a>
            </h2>

            <form action="options.php" method="post">
                <?php
                settings_fields($this->options_name);
                do_settings_sections('cf7-honeypot-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_blocked_ips_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        include plugin_dir_path(dirname(__FILE__)) . 'templates/blocked-ips.php';
    }

    // Render Methods for Different Field Types
    public function render_checkbox_field($args)
    {
        $options = get_option($this->options_name);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 0;
        ?>
        <label>
            <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>"
                name="<?php echo esc_attr($this->options_name . '[' . $args['label_for'] . ']'); ?>" value="1" <?php checked(1, $value); ?>>
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    public function render_number_field($args)
    {
        $options = get_option($this->options_name);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <input type="number" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($this->options_name . '[' . $args['label_for'] . ']'); ?>"
            value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($args['min']); ?>"
            max="<?php echo esc_attr($args['max']); ?>" class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_custom_questions_field($args)
    {
        $options = get_option($this->options_name);
        $questions = isset($options['custom_questions']) ? $options['custom_questions'] : array();
        ?>
        <div id="custom-questions-container">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-row">
                    <input type="text"
                        name="<?php echo esc_attr($this->options_name . '[custom_questions][' . $index . '][question]'); ?>"
                        value="<?php echo esc_attr($question['question']); ?>"
                        placeholder="<?php esc_attr_e('Question', 'cf7-honeypot'); ?>" class="regular-text">
                    <input type="text"
                        name="<?php echo esc_attr($this->options_name . '[custom_questions][' . $index . '][answer]'); ?>"
                        value="<?php echo esc_attr($question['answer']); ?>"
                        placeholder="<?php esc_attr_e('Answer', 'cf7-honeypot'); ?>" class="regular-text">
                    <button type="button" class="button remove-question"><?php _e('Remove', 'cf7-honeypot'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-question" class="button"><?php _e('Add Question', 'cf7-honeypot'); ?></button>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_form_specific_settings($args)
    {
        $forms = WPCF7_ContactForm::find();
        $options = get_option($this->options_name);
        $form_settings = isset($options['form_settings']) ? $options['form_settings'] : array();
        ?>
        <div id="form-specific-settings">
            <?php foreach ($forms as $form): ?>
                <div class="form-setting-row">
                    <h4><?php echo esc_html($form->title()); ?></h4>
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->options_name . '[form_settings][' . $form->id() . '][enabled]'); ?>"
                            value="1" <?php checked(1, isset($form_settings[$form->id()]['enabled']) ? $form_settings[$form->id()]['enabled'] : 0); ?>>
                        <?php _e('Enable Protection', 'cf7-honeypot'); ?>
                    </label>
                    <select
                        name="<?php echo esc_attr($this->options_name . '[form_settings][' . $form->id() . '][protection_level]'); ?>">
                        <option value="low" <?php selected('low', isset($form_settings[$form->id()]['protection_level']) ? $form_settings[$form->id()]['protection_level'] : ''); ?>><?php _e('Low', 'cf7-honeypot'); ?></option>
                        <option value="medium" <?php selected('medium', isset($form_settings[$form->id()]['protection_level']) ? $form_settings[$form->id()]['protection_level'] : ''); ?>><?php _e('Medium', 'cf7-honeypot'); ?>
                        </option>
                        <option value="high" <?php selected('high', isset($form_settings[$form->id()]['protection_level']) ? $form_settings[$form->id()]['protection_level'] : ''); ?>><?php _e('High', 'cf7-honeypot'); ?></option>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Sanitize protection settings
        $sanitized['enable_auto_block'] = isset($input['enable_auto_block']) ? 1 : 0;
        $sanitized['block_threshold'] = isset($input['block_threshold']) ?
            absint($input['block_threshold']) : 5;
        $sanitized['block_duration'] = isset($input['block_duration']) ?
            absint($input['block_duration']) : 24;

        // Sanitize notification settings
        $sanitized['admin_notifications'] = isset($input['admin_notifications']) ? 1 : 0;
        $sanitized['notification_threshold'] = isset($input['notification_threshold']) ?
            absint($input['notification_threshold']) : 3;

        if (isset($input['additional_recipients'])) {
            $emails = array_map('trim', explode("\n", $input['additional_recipients']));
            $sanitized['additional_recipients'] = array_filter($emails, 'is_email');
        }

        // Sanitize custom questions
        if (isset($input['custom_questions']) && is_array($input['custom_questions'])) {
            foreach ($input['custom_questions'] as $index => $question) {
                if (!empty($question['question']) && !empty($question['answer'])) {
                    $sanitized['custom_questions'][] = array(
                        'question' => sanitize_text_field($question['question']),
                        'answer' => sanitize_text_field($question['answer'])
                    );
                }
            }
        }

        // Sanitize form-specific settings
        if (isset($input['form_settings']) && is_array($input['form_settings'])) {
            foreach ($input['form_settings'] as $form_id => $settings) {
                $sanitized['form_settings'][$form_id] = array(
                    'enabled' => isset($settings['enabled']) ? 1 : 0,
                    'protection_level' => in_array($settings['protection_level'], array('low', 'medium', 'high'))
                        ? $settings['protection_level']
                        : 'medium'
                );
            }
        }

        return $sanitized;
    }

    public function enqueue_admin_scripts($hook)
    {
        if (!in_array($hook, array('cf7-honeypot_page_cf7-honeypot-settings', 'cf7-honeypot_page_cf7-honeypot-blocked-ips'))) {
            return;
        }

        // Select2
        wp_enqueue_style(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
            array(),
            '4.0.13'
        );

        wp_enqueue_script(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
            array('jquery'),
            '4.0.13',
            true
        );

        // Plugin CSS
        wp_enqueue_style(
            'cf7-honeypot-admin-settings',
            plugins_url('assets/css/admin.min.css', dirname(__FILE__)),
            array('select2'),
            '1.1.0'
        );

        // Stili inline per Select2
        wp_add_inline_style('cf7-honeypot-admin-settings', '
            .select2-container--default {
                width: 100% !important;
                max-width: 100% !important;
            }

            .select2-container--default .select2-selection--multiple {
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                min-height: 100px;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background-color: #2271b1;
                color: #fff;
                border: none;
                border-radius: 4px;
                padding: 4px 8px;
                margin: 4px;
            }

            .select2-container--default .select2-selection__choice__remove {
                color: #fff;
                margin-right: 5px;
            }

            .select2-container--default .select2-selection__choice__remove:hover {
                color: #fff;
                opacity: 0.8;
            }

            .select2-dropdown {
                border-color: #cbd5e1;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #2271b1;
            }

            .select2-search--inline .select2-search__field {
                margin-top: 6px !important;
            }
        ');

        // Plugin JavaScript
        wp_enqueue_script(
            'cf7-honeypot-admin',
            plugins_url('assets/js/admin.min.js', dirname(__FILE__)),
            array('jquery', 'select2'),
            '1.1.0',
            true
        );

        wp_localize_script('cf7-honeypot-admin', 'cf7HoneypotAdmin', array(
            'addQuestionNonce' => wp_create_nonce('cf7_honeypot_add_question'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this question?', 'cf7-honeypot'),
                'question' => __('Question', 'cf7-honeypot'),
                'answer' => __('Answer', 'cf7-honeypot'),
                'remove' => __('Remove', 'cf7-honeypot'),
                'selectCountries' => __('Select countries to block...', 'cf7-honeypot'),
                'searching' => __('Searching...', 'cf7-honeypot'),
                'noResults' => __('No countries found', 'cf7-honeypot')
            )
        ));
    }

    private function is_plugin_admin_page($hook)
    {
        return in_array($hook, array(
            'cf7-honeypot_page_cf7-honeypot-settings',
            'cf7-honeypot_page_cf7-honeypot-blocked-ips',
            'toplevel_page_cf7-honeypot-stats'
        ));
    }

    public function render_text_field($args)
    {
        $options = get_option($this->options_name);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($this->options_name . '[' . $args['label_for'] . ']'); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_country_blocking_field($args)
    {
        $options = get_option($this->options_name);
        $blocked_countries = isset($options['blocked_countries']) ? $options['blocked_countries'] : array();
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($this->options_name . '[blocked_countries][]'); ?>" multiple class="regular-text">
            <?php
            $countries = include(plugin_dir_path(dirname(__FILE__)) . 'includes/countries.php');
            foreach ($countries as $code => $name) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($code),
                    selected(in_array($code, $blocked_countries), true, false),
                    esc_html($name)
                );
            }
            ?>
        </select>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function get_setting($key, $default = false)
    {
        $options = get_option($this->options_name);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function get_form_settings($form_id)
    {
        $options = get_option($this->options_name);
        return isset($options['form_settings'][$form_id])
            ? $options['form_settings'][$form_id]
            : array(
                'enabled' => 1,
                'protection_level' => 'medium'
            );
    }
}

// Initialize the settings
add_action('plugins_loaded', array('CF7_Honeypot_Settings', 'get_instance'));
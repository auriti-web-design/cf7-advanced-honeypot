<?php
/**
 * Plugin Name: Advanced CF7 Honeypot System
 * Description: Advanced anti-spam protection system for Contact Form 7
 * Version: 1.0.0
 * Author: Juan Camilo Auriti
 * Text Domain: advanced-cf7-honeypot
 * Domain Path: /languages
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ACF7H_VERSION', '1.0.0');
define('ACF7H_PATH', plugin_dir_path(__FILE__));
define('ACF7H_URL', plugin_dir_url(__FILE__));

class CF7_Advanced_Honeypot
{
    private $debug = true;
    private static $instance = null;
    private $questions_table = 'cf7_honeypot_questions';
    private $stats_table = 'cf7_honeypot_stats';

    /**
     * Get singleton instance
     *
     * @return CF7_Advanced_Honeypot
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize plugin hooks and filters
     */
    private function __construct()
    {
        // Load text domain for translations
        add_action('init', array($this, 'load_plugin_textdomain'));

        // Initialize hooks
        add_action('init', array($this, 'init_honeypot'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('wpcf7_validate', array($this, 'validate_honeypot'), 10, 2);
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));

        // Database verification/update
        $this->maybe_update_database();

        // Biweekly cron
        if (!wp_next_scheduled('cf7_honeypot_cleanup')) {
            wp_schedule_event(time(), 'twiceweekly', 'cf7_honeypot_cleanup');
        }
        add_action('cf7_honeypot_cleanup', array($this, 'cleanup_old_logs'));
    }

    /**
     * Load plugin translations
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'advanced-cf7-honeypot',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin activation handler
     *
     * @return void
     */
    public static function activate_plugin()
    {
        global $wpdb;
        error_log('CF7 Honeypot: Starting plugin activation');

        // Create questions table
        $charset_collate = $wpdb->get_charset_collate();
        $questions_table = $wpdb->prefix . 'cf7_honeypot_questions';
        $stats_table = $wpdb->prefix . 'cf7_honeypot_stats';

        error_log('CF7 Honeypot: Preparing tables creation');
        error_log('CF7 Honeypot: Questions table name: ' . $questions_table);
        error_log('CF7 Honeypot: Stats table name: ' . $stats_table);

        $sql1 = "CREATE TABLE IF NOT EXISTS $questions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            field_id varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE IF NOT EXISTS $stats_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            honeypot_triggered tinyint(1) NOT NULL DEFAULT 0,
            ip_address varchar(45),
            email varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        error_log('CF7 Honeypot: Questions table creation query: ' . $sql1);
        error_log('CF7 Honeypot: Stats table creation query: ' . $sql2);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Execute queries separately
        error_log('CF7 Honeypot: Executing dbDelta for questions table');
        $result1 = dbDelta($sql1);
        error_log('CF7 Honeypot: Questions dbDelta result: ' . print_r($result1, true));

        error_log('CF7 Honeypot: Executing dbDelta for stats table');
        $result2 = dbDelta($sql2);
        error_log('CF7 Honeypot: Stats dbDelta result: ' . print_r($result2, true));

        // Verify tables existence
        $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '$questions_table'") == $questions_table;
        $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '$stats_table'") == $stats_table;

        error_log('CF7 Honeypot: Tables existence verification:');
        error_log('CF7 Honeypot: - Questions table exists: ' . ($table1_exists ? 'Yes' : 'No'));
        error_log('CF7 Honeypot: - Stats table exists: ' . ($table2_exists ? 'Yes' : 'No'));

        if (!$table1_exists) {
            error_log('CF7 Honeypot: ERROR: Unable to create questions table');
            error_log('CF7 Honeypot: Last MySQL error: ' . $wpdb->last_error);
            return;
        }

        if (!$table2_exists) {
            error_log('CF7 Honeypot: ERROR: Unable to create stats table');
            error_log('CF7 Honeypot: Last MySQL error: ' . $wpdb->last_error);
            return;
        }

        // Verify and insert questions
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table");
        error_log('CF7 Honeypot: Existing questions count: ' . $count);

        if ($count == 0) {
            error_log('CF7 Honeypot: Starting default questions insertion');
            self::insert_default_questions();
        } else {
            error_log('CF7 Honeypot: Questions already present, skipping insertion');
        }

        error_log('CF7 Honeypot: Plugin activation completed');
    }

    /**
     * Insert default honeypot questions
     *
     * @return void
     */
    private static function insert_default_questions()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_honeypot_questions';
        error_log('CF7 Honeypot: Starting questions insertion in ' . $table_name);

        $default_questions = array(
            array(__('What is the capital of Italy?', 'advanced-cf7-honeypot'), 'field_a1'),
            array(__('What is 2 + 2?', 'advanced-cf7-honeypot'), 'field_b2'),
            array(__('What color is the sky on a clear day?', 'advanced-cf7-honeypot'), 'field_c3'),
            array(__('What is Earth\'s natural satellite called?', 'advanced-cf7-honeypot'), 'field_d4'),
            array(__('What is 10 - 5?', 'advanced-cf7-honeypot'), 'field_e5'),
            array(__('In which continent is France located?', 'advanced-cf7-honeypot'), 'field_f6'),
            array(__('What color is the sun?', 'advanced-cf7-honeypot'), 'field_g7'),
            array(__('What is 3 x 3?', 'advanced-cf7-honeypot'), 'field_h8'),
            array(__('What is the capital of France?', 'advanced-cf7-honeypot'), 'field_i9'),
            array(__('What season comes after summer?', 'advanced-cf7-honeypot'), 'field_j10'),
            array(__('How many days are in a week?', 'advanced-cf7-honeypot'), 'field_k11'),
            array(__('Which animal makes the "meow" sound?', 'advanced-cf7-honeypot'), 'field_l12'),
            array(__('What color is grass?', 'advanced-cf7-honeypot'), 'field_m13'),
            array(__('What is 15 divided by 3?', 'advanced-cf7-honeypot'), 'field_n14'),
            array(__('In which country is the Eiffel Tower located?', 'advanced-cf7-honeypot'), 'field_o15'),
            array(__('What is the first month of the year?', 'advanced-cf7-honeypot'), 'field_p16'),
            array(__('What sound does a dog make?', 'advanced-cf7-honeypot'), 'field_q17'),
            array(__('How many legs does a cat have?', 'advanced-cf7-honeypot'), 'field_r18'),
            array(__('What color is a ripe banana?', 'advanced-cf7-honeypot'), 'field_s19'),
            array(__('What is 20 + 5?', 'advanced-cf7-honeypot'), 'field_t20')
        );

        $inserted = 0;
        $errors = 0;

        foreach ($default_questions as $question) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'question' => $question[0],
                    'field_id' => $question[1]
                ),
                array('%s', '%s')
            );

            if ($result) {
                $inserted++;
            } else {
                $errors++;
            }
        }

        error_log('CF7 Honeypot: Questions insertion completed');
        error_log('CF7 Honeypot: - Successfully inserted questions: ' . $inserted);
        error_log('CF7 Honeypot: - Errors: ' . $errors);

        if ($wpdb->last_error) {
            error_log("CF7 Honeypot Error: Error inserting fields: " . $wpdb->last_error);
        }
    }


    /**
     * Initialize honeypot functionality
     *
     * @return void
     */
    public function init_honeypot()
    {
        // Add hidden honeypot field to form
        add_filter('wpcf7_form_elements', array($this, 'add_honeypot_field'));
    }

    /**
     * Add honeypot field to the form
     *
     * @param string $content Form content
     * @return string Modified form content
     */
    public function add_honeypot_field($content)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->questions_table;

        // Query for random question
        $question = $wpdb->get_row("
            SELECT question, field_id
            FROM {$table_name}
            ORDER BY RAND()
            LIMIT 1
        ");

        if (!$question || empty($question->question)) {
            if ($this->debug) {
                error_log('CF7 Honeypot Warning: Using fallback question');
            }
            $question = new stdClass();
            $question->question = __('What is 2 + 2?', 'advanced-cf7-honeypot');
            $question->field_id = 'field_fallback_' . wp_rand(1000, 9999);
        }

        // Generate more complex random class
        $random_prefix = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
        $random_suffix = wp_rand(1000, 9999);
        $wrapper_class = $random_prefix . 'field-' . $random_suffix;

        // Generate random input ID
        $field_id = 'input_' . wp_rand(1000, 9999);

        // HTML field without inline styles
        $hidden_field = sprintf(
            '<div class="%s"><label for="%s">%s</label><input type="text" name="%s" id="%s" class="extra-field" autocomplete="off" aria-hidden="true"></div>',
            esc_attr($wrapper_class),
            esc_attr($field_id),
            esc_html($question->question),
            esc_attr($question->field_id),
            esc_attr($field_id)
        );

        // Register class in database or option for dynamic CSS
        $this->register_honeypot_class($wrapper_class);

        // Insert field in form
        if (strpos($content, '<input type="submit"') !== false) {
            $content = preg_replace(
                '/(<input[^>]*type=["\']submit["\'][^>]*>)/',
                $hidden_field . '$1',
                $content
            );
        } elseif (strpos($content, '</form>') !== false) {
            $content = str_replace('</form>', $hidden_field . '</form>', $content);
        } else {
            $content .= $hidden_field;
        }

        return $content;
    }

    /**
     * Register a honeypot class in the database
     *
     * @param string $class The class name to register
     * @return void
     */
    private function register_honeypot_class($class)
    {
        $classes = get_option('cf7_honeypot_classes', array());

        // Clean old classes (older than 1 hour)
        $current_time = time();
        $classes = array_filter($classes, function ($class_data) use ($current_time) {
            return isset($class_data['time']) && ($current_time - $class_data['time'] < 3600);
        });

        // Add new class with timestamp
        $classes[] = array(
            'name' => $class,
            'time' => $current_time
        );

        // Keep only last 100 classes
        if (count($classes) > 100) {
            $classes = array_slice($classes, -100);
        }

        update_option('cf7_honeypot_classes', $classes);

        if ($this->debug) {
            error_log(sprintf(
                /* translators: %s: class name */
                __('CF7 Honeypot: Class registered - %s', 'advanced-cf7-honeypot'),
                $class
            ));
        }
    }

    /**
     * Generate dynamic CSS to hide honeypot fields
     *
     * @return string Generated CSS
     */
    public function generate_dynamic_css()
    {
        $classes = get_option('cf7_honeypot_classes', array());
        $css = '';

        foreach ($classes as $class_data) {
            if (!isset($class_data['name']))
                continue;

            $class = $class_data['name'];
            $css .= ".$class {";
            $css .= "position: fixed !important;";
            $css .= "clip: rect(0, 0, 0, 0) !important;";
            $css .= "height: 1px !important;";
            $css .= "width: 1px !important;";
            $css .= "margin: -1px !important;";
            $css .= "padding: 0 !important;";
            $css .= "overflow: hidden !important;";
            $css .= "border: 0 !important;";
            $css .= "opacity: 0 !important;";
            $css .= "pointer-events: none !important;";
            $css .= "outline: none !important;";
            $css .= "-webkit-appearance: none !important;";
            $css .= "-moz-appearance: none !important;";
            $css .= "appearance: none !important;";
            $css .= "visibility: hidden !important;";
            $css .= "position: absolute !important;";
            $css .= "left: -9999px !important;";
            $css .= "}";
        }

        if ($this->debug) {
            error_log(sprintf(
                /* translators: %s: generated CSS */
                __('CF7 Honeypot: Generated CSS - %s', 'advanced-cf7-honeypot'),
                $css
            ));
        }

        return $css;
    }

    /**
     * Load frontend styles for honeypot fields
     * Generate dynamic CSS to hide fields
     *
     * @return void
     */
    public function enqueue_frontend_styles()
    {
        if ($this->debug) {
            error_log(__('CF7 Honeypot: Starting frontend styles loading', 'advanced-cf7-honeypot'));
        }

        // Generate unique handle for CSS
        $handle = 'cf7-custom-styles-' . wp_rand(1000, 9999);

        // Generate CSS
        $css = $this->generate_dynamic_css();

        if (!empty($css)) {
            // Register and enqueue dynamic CSS
            wp_register_style($handle, false);
            wp_enqueue_style($handle);

            // Add inline CSS
            wp_add_inline_style($handle, $css);

            if ($this->debug) {
                error_log(__('CF7 Honeypot: Styles loaded successfully', 'advanced-cf7-honeypot'));
            }
        } else {
            if ($this->debug) {
                error_log(__('CF7 Honeypot: No styles to load', 'advanced-cf7-honeypot'));
            }
        }
    }

    /**
     * Check and update database structure if needed
     *
     * @return void
     */
    public function maybe_update_database()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Check if email column exists
        $email_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'email'");

        if (empty($email_column_exists)) {
            if ($this->debug) {
                error_log(__('CF7 Honeypot Debug: Adding email column to stats table', 'advanced-cf7-honeypot'));
            }

            // Add email column
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN email varchar(255) AFTER ip_address");

            if ($wpdb->last_error) {
                error_log(sprintf(
                    /* translators: %s: database error message */
                    __('CF7 Honeypot Error: Error adding email column - %s', 'advanced-cf7-honeypot'),
                    $wpdb->last_error
                ));
            }
        }
    }

    /**
     * Validate honeypot fields
     *
     * @param WPCF7_Validation $result
     * @param array $tags
     * @return WPCF7_Validation
     */
    public function validate_honeypot($result, $tags)
    {
        if ($this->debug) {
            error_log(__('CF7 Honeypot Debug: Starting validation', 'advanced-cf7-honeypot'));
            error_log(sprintf(
                /* translators: %s: POST data */
                __('CF7 Honeypot Debug: POST Data: %s', 'advanced-cf7-honeypot'),
                print_r($_POST, true)
            ));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->questions_table;
        $field_ids = $wpdb->get_col("SELECT field_id FROM $table_name");

        if ($this->debug) {
            error_log(sprintf(
                /* translators: %s: field IDs */
                __('CF7 Honeypot Debug: Found Field IDs: %s', 'advanced-cf7-honeypot'),
                print_r($field_ids, true)
            ));
        }

        foreach ($_POST as $key => $value) {
            if ($this->debug) {
                error_log(sprintf(
                    /* translators: 1: field key, 2: field value */
                    __('CF7 Honeypot Debug: Checking field %1$s with value %2$s', 'advanced-cf7-honeypot'),
                    $key,
                    $value
                ));
            }

            if (in_array($key, $field_ids) && !empty($value)) {
                if ($this->debug) {
                    error_log(__('CF7 Honeypot Debug: Found filled honeypot field', 'advanced-cf7-honeypot'));
                }

                $result->invalidate('spam', __('Spam attempt detected.', 'advanced-cf7-honeypot'));
                add_filter('wpcf7_skip_mail', '__return_true');

                $form_id = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;
                $this->log_spam_attempt($form_id);

                return $result;
            }
        }

        if ($this->debug) {
            error_log(__('CF7 Honeypot Debug: Validation completed - no spam detected', 'advanced-cf7-honeypot'));
        }

        return $result;
    }

    /**
     * Log spam attempt in database
     *
     * @param int $form_id
     * @return void
     */
    private function log_spam_attempt($form_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Pre-insertion debug
        if ($this->debug) {
            error_log(__('CF7 Honeypot Debug: Attempting to log spam', 'advanced-cf7-honeypot'));
            error_log(sprintf(
                /* translators: 1: form ID, 2: table name, 3: POST data */
                __('CF7 Honeypot Debug: Form ID: %1$s | Table: %2$s | POST Data: %3$s', 'advanced-cf7-honeypot'),
                $form_id,
                $table_name,
                print_r($_POST, true)
            ));
        }

        // Get email from form
        $email = '';
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'email') !== false || strpos($key, 'your-email') !== false) {
                $email = sanitize_email($value);
                if ($this->debug) {
                    error_log(sprintf(
                        /* translators: %s: email address */
                        __('CF7 Honeypot Debug: Email found: %s', 'advanced-cf7-honeypot'),
                        $email
                    ));
                }
                break;
            }
        }

        // Prepare data for insertion
        $data = array(
            'form_id' => $form_id,
            'honeypot_triggered' => 1,
            'ip_address' => $this->get_client_ip()
        );

        // Add email only if column exists
        $email_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'email'");
        if ($email_column_exists) {
            $data['email'] = $email;
        }

        if ($this->debug) {
            error_log(sprintf(
                /* translators: %s: insertion data */
                __('CF7 Honeypot Debug: Data to insert: %s', 'advanced-cf7-honeypot'),
                print_r($data, true)
            ));
        }

        $inserted = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%d', '%s', '%s')
        );

        if ($this->debug) {
            if ($inserted === false) {
                error_log(sprintf(
                    /* translators: %s: database error */
                    __('CF7 Honeypot Error: Insertion error - %s', 'advanced-cf7-honeypot'),
                    $wpdb->last_error
                ));
            } else {
                error_log(sprintf(
                    /* translators: %d: inserted record ID */
                    __('CF7 Honeypot Success: Record inserted with ID: %d', 'advanced-cf7-honeypot'),
                    $wpdb->insert_id
                ));
            }
        }
    }

    /**
     * Get client IP address with fallback options
     *
     * @return string
     */
    private function get_client_ip()
    {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Convert ::1 to 127.0.0.1 for clarity
                if ($ip === '::1') {
                    return '127.0.0.1';
                }
                // Handle multiple IPs (take first)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Add admin menu page
     *
     * @return void
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('CF7 Honeypot Stats', 'advanced-cf7-honeypot'),
            __('CF7 Honeypot', 'advanced-cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-stats',
            array($this, 'render_stats_page'),
            'dashicons-shield',
            30
        );
    }

    /**
     * Render statistics page
     *
     * @return void
     */
    public function render_stats_page()
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        // Get statistics
        $total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $stats_table WHERE honeypot_triggered = 1");
        $recent_attempts = $wpdb->get_results(
            "SELECT * FROM $stats_table
            WHERE honeypot_triggered = 1
            ORDER BY created_at DESC
            LIMIT 10"
        );

        // Pass variables to template
        $template_vars = array(
            'total_attempts' => $total_attempts,
            'recent_attempts' => $recent_attempts,
            'stats_table' => $stats_table,
            'instance' => $this
        );

        // Extract variables for template
        extract($template_vars);

        // Include template
        include(plugin_dir_path(__FILE__) . 'templates/stats-page.php');
    }

    /**
     * Clean up old logs
     *
     * @param string $period Cleanup period
     * @return void
     */
    public function cleanup_old_logs($period)
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        if ($period === 'all') {
            // Delete all logs
            $wpdb->query("TRUNCATE TABLE {$stats_table}");
            if ($this->debug) {
                error_log(__('CF7 Honeypot: Complete log cleanup executed', 'advanced-cf7-honeypot'));
            }
        } else {
            // Convert period to days
            $days = intval($period);
            if (!in_array($days, [1, 7, 30])) {
                $days = 30; // Default value
            }

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$stats_table}
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            );

            if ($this->debug) {
                error_log(sprintf(
                    /* translators: %d: number of days */
                    __('CF7 Honeypot: Cleaned logs older than %d days', 'advanced-cf7-honeypot'),
                    $days
                ));
            }
        }
    }

    /**
     * Plugin deactivation handler
     *
     * @return void
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('cf7_honeypot_cleanup');
    }

    /**
     * Enqueue admin styles
     *
     * @return void
     */
    public function enqueue_admin_styles()
    {
        wp_enqueue_style(
            'cf7-honeypot-admin',
            plugins_url('assets/css/admin-style.css', __FILE__),
            array(),
            '1.0.0'
        );
    }
}

// Plugin initialization
function cf7_advanced_honeypot_init()
{
    return CF7_Advanced_Honeypot::get_instance();
}

/**
 * Enqueue admin styles callback
 *
 * @return void
 */
function cf7_advanced_honeypot_admin_styles()
{
    $instance = CF7_Advanced_Honeypot::get_instance();
    if (method_exists($instance, 'enqueue_admin_styles')) {
        $instance->enqueue_admin_styles();
    }
}
add_action('admin_enqueue_scripts', 'cf7_advanced_honeypot_admin_styles');

/**
 * Plugin deactivation callback
 *
 * @return void
 */
function cf7_advanced_honeypot_deactivate()
{
    CF7_Advanced_Honeypot::deactivate();
}

// Register hooks
register_activation_hook(__FILE__, array('CF7_Advanced_Honeypot', 'activate_plugin'));
register_deactivation_hook(__FILE__, 'cf7_advanced_honeypot_deactivate');
add_action('plugins_loaded', 'cf7_advanced_honeypot_init');

// Add twice weekly schedule
add_filter('cron_schedules', 'add_twiceweekly_schedule');
function add_twiceweekly_schedule($schedules)
{
    $schedules['twiceweekly'] = array(
        'interval' => 302400, // 3.5 days in seconds
        'display' => __('Twice Weekly', 'advanced-cf7-honeypot')
    );
    return $schedules;
}

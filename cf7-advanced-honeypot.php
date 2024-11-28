<?php
/**
 * Plugin Name: Advanced CF7 Honeypot System
 * Description: Advanced anti-spam protection system for Contact Form 7
 * Version: 1.0.0
 * Author: Dromedian s.r.l.
 * Text Domain: cf7-honeypot
 * Domain Path: /languages
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPINC')) {
    die;
}

class CF7_Advanced_Honeypot {
    private static $instance = null;
    private $questions_table = 'cf7_honeypot_questions';
    private $stats_table = 'cf7_honeypot_stats';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init_honeypot'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('wpcf7_validate', array($this, 'validate_honeypot'), 10, 2);
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));

        $this->maybe_update_database();

        if (!wp_next_scheduled('cf7_honeypot_cleanup')) {
            wp_schedule_event(time(), 'twiceweekly', 'cf7_honeypot_cleanup');
        }
        add_action('cf7_honeypot_cleanup', array($this, 'cleanup_old_logs'));
    }

    public static function activate_plugin() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $questions_table = $wpdb->prefix . 'cf7_honeypot_questions';
        $stats_table = $wpdb->prefix . 'cf7_honeypot_stats';

        $sql1 = "CREATE TABLE IF NOT EXISTS $questions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            field_id varchar(50) NOT NULL,
            correct_answer text NOT NULL,
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        $count = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table");
        if ($count == 0) {
            self::insert_default_questions();
        }
    }

    private static function insert_default_questions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_honeypot_questions';

        $default_questions = array(
            array(__('What is the capital of Italy?', 'cf7-honeypot'), 'field_a1', 'rome'),
            array(__('What is 2 + 2?', 'cf7-honeypot'), 'field_b2', '4'),
            array(__('What color is the sky on a clear day?', 'cf7-honeypot'), 'field_c3', 'blue'),
            array(__('What is Earth\'s natural satellite called?', 'cf7-honeypot'), 'field_d4', 'moon'),
            array(__('What is 10 - 5?', 'cf7-honeypot'), 'field_e5', '5'),
            array(__('In which continent is France located?', 'cf7-honeypot'), 'field_f6', 'europe'),
            array(__('What color is the sun?', 'cf7-honeypot'), 'field_g7', 'yellow'),
            array(__('What is 3 x 3?', 'cf7-honeypot'), 'field_h8', '9'),
            array(__('What is the capital of France?', 'cf7-honeypot'), 'field_i9', 'paris'),
            array(__('What season comes after summer?', 'cf7-honeypot'), 'field_j10', 'autumn'),
            array(__('How many days are in a week?', 'cf7-honeypot'), 'field_k11', '7'),
            array(__('What sound does a cat make?', 'cf7-honeypot'), 'field_l12', 'meow'),
            array(__('What color is grass?', 'cf7-honeypot'), 'field_m13', 'green'),
            array(__('What is 15 divided by 3?', 'cf7-honeypot'), 'field_n14', '5'),
            array(__('In which country is the Eiffel Tower?', 'cf7-honeypot'), 'field_o15', 'france'),
            array(__('What is the first month of the year?', 'cf7-honeypot'), 'field_p16', 'january'),
            array(__('What sound does a dog make?', 'cf7-honeypot'), 'field_q17', 'woof'),
            array(__('How many legs does a cat have?', 'cf7-honeypot'), 'field_r18', '4'),
            array(__('What color is a ripe banana?', 'cf7-honeypot'), 'field_s19', 'yellow'),
            array(__('What is 20 + 5?', 'cf7-honeypot'), 'field_t20', '25')
        );

        foreach ($default_questions as $question) {
            $wpdb->insert(
                $table_name,
                array(
                    'question' => $question[0],
                    'field_id' => $question[1],
                    'correct_answer' => strtolower($question[2])
                ),
                array('%s', '%s', '%s')
            );
        }
    }

    public function init_honeypot() {
        add_filter('wpcf7_form_elements', array($this, 'add_honeypot_field'));
        load_plugin_textdomain('cf7-honeypot', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function maybe_update_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        $email_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'email'");

        if (empty($email_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN email varchar(255) AFTER ip_address");
        }
    }

    public function add_honeypot_field($content) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->questions_table;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            self::activate_plugin();
        }

        $questions_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($questions_count == 0) {
            self::insert_default_questions();
        }

        $question = $wpdb->get_row("SELECT question, field_id FROM {$table_name} ORDER BY RAND() LIMIT 1");

        if (!$question || empty($question->question)) {
            $question = new stdClass();
            $question->question = __('What is 2 + 2?', 'cf7-honeypot');
            $question->field_id = 'field_fallback_' . wp_rand(1000, 9999);
        }

        $wrapper_class = 'additional-field-' . wp_rand(1000, 9999);
        $field_id = 'input_' . wp_rand(1000, 9999);

        $styles = "<style>.{$wrapper_class} {position:absolute !important;left:-9999px !important;top:-9999px !important;opacity:0 !important;z-index:-1 !important;pointer-events:none !important;visibility:hidden !important;}</style>";

        $hidden_field = sprintf(
            '<div class="%s"><label for="%s">%s</label><input type="text" name="%s" id="%s" class="extra-field" autocomplete="off"></div>',
            esc_attr($wrapper_class),
            esc_attr($field_id),
            esc_html($question->question),
            esc_attr($question->field_id),
            esc_attr($field_id)
        );

        if (strpos($content, '<input type="submit"') !== false) {
            $content = preg_replace(
                '/(<input[^>]*type=["\']submit["\'][^>]*>)/',
                $hidden_field . '$1',
                $content
            );
            return $styles . $content;
        } elseif (strpos($content, '</form>') !== false) {
            return $styles . str_replace('</form>', $hidden_field . '</form>', $content);
        }
        return $styles . $content . $hidden_field;
    }

    public function validate_honeypot($result, $tags) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->questions_table;
        $field_ids = $wpdb->get_col("SELECT field_id FROM $table_name");

        foreach ($_POST as $key => $value) {
            if (in_array($key, $field_ids) && !empty($value)) {
                $result->invalidate('spam', __('Spam attempt detected.', 'cf7-honeypot'));
                add_filter('wpcf7_skip_mail', '__return_true');

                $form_id = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;
                $this->log_spam_attempt($form_id);
                return $result;
            }
        }
        return $result;
    }

    private function log_spam_attempt($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        $email = '';
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'email') !== false || strpos($key, 'your-email') !== false) {
                $email = sanitize_email($value);
                break;
            }
        }

        $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'honeypot_triggered' => 1,
                'ip_address' => $this->get_client_ip(),
                'email' => $email
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    private function get_client_ip() {
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
                if ($ip === '::1') return '127.0.0.1';
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'UNKNOWN';
    }

    public function add_admin_menu() {
        add_menu_page(
            __('CF7 Honeypot Stats', 'cf7-honeypot'),
            __('CF7 Honeypot', 'cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-stats',
            array($this, 'render_stats_page'),
            'dashicons-shield',
            30
        );
    }

    public function render_stats_page() {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        // Get basic statistics
        $total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $stats_table WHERE honeypot_triggered = 1");
        $recent_attempts = $wpdb->get_results(
            "SELECT * FROM $stats_table
            WHERE honeypot_triggered = 1
            ORDER BY created_at DESC
            LIMIT 10"
        );

        // Get summary statistics
        $summary_stats = $wpdb->get_results("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as attempts,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT email) as unique_emails,
                COUNT(DISTINCT form_id) as forms_affected
            FROM $stats_table
            WHERE honeypot_triggered = 1
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");

        // Pass variables to template
        $template_vars = array(
            'total_attempts' => $total_attempts,
            'recent_attempts' => $recent_attempts,
            'summary_stats' => $summary_stats,
            'stats_table' => $stats_table,
            'instance' => $this
        );

        // Extract variables for the template
        extract($template_vars);

        // Include the template
        include(plugin_dir_path(__FILE__) . 'templates/stats-page.php');
    }

    public function cleanup_old_logs($period = '30') {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        if ($period === 'all') {
            $wpdb->query("TRUNCATE TABLE {$stats_table}");
        } else {
            $days = intval($period);
            if (!in_array($days, [1, 7, 30])) {
                $days = 30;
            }
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$stats_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('cf7_honeypot_cleanup');
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style(
            'cf7-honeypot-admin',
            plugins_url('assets/css/admin-style.css', __FILE__),
            array(),
            '1.0.0'
        );
    }
}

// Plugin initialization functions
function cf7_advanced_honeypot_init() {
    return CF7_Advanced_Honeypot::get_instance();
}

function cf7_advanced_honeypot_admin_styles() {
    $instance = CF7_Advanced_Honeypot::get_instance();
    if (method_exists($instance, 'enqueue_admin_styles')) {
        $instance->enqueue_admin_styles();
    }
}

// Hook registrations
add_action('admin_enqueue_scripts', 'cf7_advanced_honeypot_admin_styles');

function cf7_advanced_honeypot_deactivate() {
    CF7_Advanced_Honeypot::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('CF7_Advanced_Honeypot', 'activate_plugin'));
register_deactivation_hook(__FILE__, 'cf7_advanced_honeypot_deactivate');
add_action('plugins_loaded', 'cf7_advanced_honeypot_init');

// Add cron schedule
add_filter('cron_schedules', 'add_twiceweekly_schedule');
function add_twiceweekly_schedule($schedules) {
    $schedules['twiceweekly'] = array(
        'interval' => 302400, // 3.5 days in seconds
        'display'  => __('Twice Weekly', 'cf7-honeypot')
    );
    return $schedules;
}
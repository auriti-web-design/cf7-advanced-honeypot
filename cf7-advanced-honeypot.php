<?php
/**
 * Plugin Name: Advanced CF7 Honeypot System
 * Description: Advanced anti-spam protection system for Contact Form 7 with enhanced logging and CFDB7 integration
 * Version: 1.3.1
 * Author: Juan Camilo Auriti
 * Author URI: https://auritidesign.it
 * Text Domain: cf7-honeypot
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * This plugin provides advanced spam protection for Contact Form 7 with:
 * - Intelligent honeypot system
 * - Advanced spam detection
 * - Detailed logging and statistics
 * - CFDB7 integration prevention for spam entries
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPINC')) {
    die;
}

// Include la classe delle impostazioni
require_once plugin_dir_path(__FILE__) . 'includes/class-cf7-honeypot-settings.php';

class CF7_Advanced_Honeypot
{
    private static $instance = null;
    private $questions_table = 'cf7_honeypot_questions';
    private $stats_table = 'cf7_honeypot_stats';

    private $cache_key = 'cf7_honeypot_field_ids';
    private $cache_duration = 12 * HOUR_IN_SECONDS; // 12 ore di cache

    private $db_version = '1.3.0';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'init_honeypot'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('plugins_loaded', array($this, 'check_db_updates'));
        add_action('init', array($this, 'init_ajax_handlers'));

        // Hook per la validazione con priorità alta
        add_filter('wpcf7_validate', array($this, 'validate_honeypot'), 1, 2);

        register_activation_hook(__FILE__, array($this, 'activate_plugin'));

        // Cambia l'hook per l'aggiornamento del database
        add_action('init', array($this, 'maybe_update_database'), 1);

        add_filter('wpcf7_spam', array($this, 'check_for_spam'), 1, 2);
        add_action('wpcf7_before_send_mail', array($this, 'check_spam_before_save'), 1);
        add_filter('cfdb7_before_save_data', array($this, 'block_cfdb7_if_spam'), 1);

        if (!wp_next_scheduled('cf7_honeypot_cleanup')) {
            wp_schedule_event(time(), 'twiceweekly', 'cf7_honeypot_cleanup');
        }
        add_action('cf7_honeypot_cleanup', array($this, 'cleanup_old_logs'));
        add_action('admin_init', array($this, 'handle_cleanup_request'));
    }

    public static function activate_plugin()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $questions_table = $wpdb->prefix . 'cf7_honeypot_questions';
        $stats_table = $wpdb->prefix . 'cf7_honeypot_stats';

        // Tabella delle domande
        $sql1 = "CREATE TABLE IF NOT EXISTS $questions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            field_id varchar(50) NOT NULL,
            correct_answer text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Tabella delle statistiche con i nuovi campi
        $sql2 = "CREATE TABLE IF NOT EXISTS $stats_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            honeypot_triggered tinyint(1) NOT NULL DEFAULT 0,
            ip_address varchar(45),
            email varchar(255),
            user_agent text,
            referrer_url varchar(255),
            triggered_field varchar(50),
            browser_fingerprint varchar(32),
            country_code varchar(2),
            risk_score tinyint unsigned,
            created_at datetime(6) DEFAULT CURRENT_TIMESTAMP(6),
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        // Verifica e inserisce le domande predefinite se necessario
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table");
        if ($count == 0) {
            self::insert_default_questions();
        }
    }

    public function update_database_structure()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Array delle colonne da verificare e aggiungere se mancanti
        $columns_to_check = array(
            'browser_fingerprint' => 'VARCHAR(32)',
            'country_code' => 'CHAR(2)',
            'risk_score' => 'TINYINT UNSIGNED'
        );

        foreach ($columns_to_check as $column => $type) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
                $column
            ));

            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `{$column}` {$type}");
            }
        }
    }

    private static function insert_default_questions()
    {
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

    public function init_honeypot()
    {
        add_filter('wpcf7_form_elements', array($this, 'add_honeypot_field'));
        load_plugin_textdomain('cf7-honeypot', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function maybe_update_database()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Verifica colonna email
        $email_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'email'");
        if (empty($email_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN email varchar(255) AFTER ip_address");
        }

        // Verifica colonna user_agent
        $user_agent_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'user_agent'");
        if (empty($user_agent_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN user_agent text AFTER email");
        }

        // Verifica colonna referrer_url
        $referrer_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'referrer_url'");
        if (empty($referrer_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN referrer_url varchar(255) AFTER user_agent");
        }

        // Verifica colonna triggered_field
        $triggered_field_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'triggered_field'");
        if (empty($triggered_field_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN triggered_field varchar(50) AFTER referrer_url");
        }

        $this->update_database_structure();

        // Invalidiamo la cache dopo gli aggiornamenti
        $this->invalidate_field_ids_cache();
    }

    /**
     * Gestisce gli aggiornamenti del database in modo sicuro
     */
    private function run_database_updates()
    {
        $current_version = get_option('cf7_honeypot_db_version', '1.0.0');

        // Aggiornamento a 1.1.0 - Aggiunge il campo email
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->update_to_1_1_0();
        }

        // Aggiornamento a 1.2.0 - Aggiunge i campi per il logging avanzato
        if (version_compare($current_version, '1.2.0', '<')) {
            $this->update_to_1_2_0();
        }

        // Salva la versione corrente
        update_option('cf7_honeypot_db_version', '1.2.0');
    }

    /**
     * Aggiornamento alla versione 1.1.0
     */
    private function update_to_1_1_0()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Verifica colonna email
        $email_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'email'");
        if (empty($email_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN email varchar(255) AFTER ip_address");
        }
    }

    /**
     * Aggiornamento alla versione 1.2.0
     */
    private function update_to_1_2_0()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Array delle nuove colonne da aggiungere
        $new_columns = [
            'user_agent' => 'TEXT',
            'referrer_url' => 'VARCHAR(255)',
            'triggered_field' => 'VARCHAR(50)',
            'browser_fingerprint' => 'VARCHAR(32)',
            'country_code' => 'CHAR(2)',
            'risk_score' => 'TINYINT'
        ];

        foreach ($new_columns as $column => $type) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE '$column'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN $column $type");
            }
        }

        // Aggiungi indici per performance
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_created_at (created_at)");
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_ip_address (ip_address)");
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_risk_score (risk_score)");
    }

    /**
     * Determina il livello di rischio basato sul numero di tentativi
     *
     * @param int $attempts_count
     * @return string
     */
    private function get_risk_level($attempts_count)
    {
        if ($attempts_count > 5) {
            return 'high';
        }
        if ($attempts_count > 2) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Ottiene l'etichetta del livello di rischio
     *
     * @param string $risk_level
     * @return string
     */
    private function get_risk_label($risk_level)
    {
        switch ($risk_level) {
            case 'high':
                return __('High Risk', 'cf7-honeypot');
            case 'medium':
                return __('Medium Risk', 'cf7-honeypot');
            default:
                return __('Low Risk', 'cf7-honeypot');
        }
    }

    public function add_honeypot_field($content)
    {
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

    /**
     * Validates honeypot fields and handles spam detection
     *
     * @param WPCF7_Validation $result The validation result object
     * @param array $tags Form tags array
     * @return WPCF7_Validation Modified validation result
     */
    public function validate_honeypot($result, $tags)
    {
        // Get form ID and settings
        $form_id  = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;
        $settings = CF7_Honeypot_Settings::get_instance()->get_form_settings($form_id);

        // Block immediately if IP is blacklisted
        if (!empty($settings['enabled']) && $this->is_ip_blocked($this->get_client_ip())) {
            $error_message = $settings['custom_error_message'] ?? __('Unable to send message.', 'cf7-honeypot');
            $result->invalidate('spam', $error_message);

            $submission = WPCF7_Submission::get_instance();
            if ($submission) {
                $submission->set_status('spam');
                if (method_exists($submission, 'set_response')) {
                    $submission->set_response($error_message);
                }
            }

            add_filter('wpcf7_skip_mail', '__return_true');
            add_filter('cfdb7_before_save_data', '__return_false');
            remove_all_actions('wpcf7_before_send_mail');
            remove_all_actions('wpcf7_mail_sent');
            remove_all_actions('wpcf7_mail_failed');

            return $result;
        }

        // Get cached field IDs
        $field_ids = $this->get_cached_field_ids();

        // Country blocking check
        $blocked_countries = CF7_Honeypot_Settings::get_instance()->get_setting('blocked_countries', array());
        $client_ip = $this->get_client_ip();
        $country_code = $this->get_country_from_ip($client_ip);

        if (!empty($blocked_countries) && in_array($country_code, $blocked_countries, true)) {
            $form_id = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;
            $settings = CF7_Honeypot_Settings::get_instance()->get_form_settings($form_id);

            $this->log_spam_attempt($form_id, 'country_block');

            $error_message = $settings['custom_error_message'] ?? __('Unable to send message.', 'cf7-honeypot');

            $result->invalidate('spam', $error_message);

            $submission = WPCF7_Submission::get_instance();
            if ($submission) {
                $submission->set_status('spam');
                if (method_exists($submission, 'set_response')) {
                    $submission->set_response($error_message);
                }
            }

            add_filter('wpcf7_skip_mail', '__return_true');
            add_filter('cfdb7_before_save_data', '__return_false');

            remove_all_actions('wpcf7_before_send_mail');
            remove_all_actions('wpcf7_mail_sent');
            remove_all_actions('wpcf7_mail_failed');

            do_action('cf7_honeypot_spam_detected', array(
                'form_id' => $form_id,
                'triggered_field' => 'country_block',
                'ip_address' => $client_ip,
                'email' => $this->extract_email_from_post(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'risk_score' => $this->calculate_risk_score($client_ip, $this->extract_email_from_post()),
                'country_code' => $country_code,
            ));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('CF7 Honeypot: Submission blocked for country %s', $country_code));
            }

            if ($this->should_block_ip($client_ip)) {
                $this->block_ip($client_ip);
            }

            return $result;
        }

        // Check for spam submission
        if ($this->is_spam_submission($field_ids)) {
            // Get form ID from submission
            $form_id = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;

            // Get triggered field
            $triggered_field = $this->get_triggered_field($field_ids);

            // Get form settings
            $settings = CF7_Honeypot_Settings::get_instance()->get_form_settings($form_id);

            // Check if protection is enabled for this form
            if (!empty($settings['enabled'])) {
                // Log the spam attempt
                $this->log_spam_attempt($form_id, $triggered_field);

                // Get custom error message if set
                $error_message = $settings['custom_error_message'] ?? __('Unable to send message.', 'cf7-honeypot');

                // Invalidate the form
                $result->invalidate('spam', $error_message);

                // Get submission instance
                $submission = WPCF7_Submission::get_instance();
                if ($submission) {
                    // Mark as spam
                    $submission->set_status('spam');

                    // Set response message
                    if (method_exists($submission, 'set_response')) {
                        $submission->set_response($error_message);
                    }
                }

                // Prevent email sending
                add_filter('wpcf7_skip_mail', '__return_true');

                // Block CFDB7 save
                add_filter('cfdb7_before_save_data', '__return_false');

                // Remove subsequent actions
                remove_all_actions('wpcf7_before_send_mail');
                remove_all_actions('wpcf7_mail_sent');
                remove_all_actions('wpcf7_mail_failed');

                // Trigger spam detected action for extensions
                do_action('cf7_honeypot_spam_detected', array(
                    'form_id' => $form_id,
                    'triggered_field' => $triggered_field,
                    'ip_address' => $this->get_client_ip(),
                    'email' => $this->extract_email_from_post(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ?
                        sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                    'risk_score' => $this->calculate_risk_score(
                        $this->get_client_ip(),
                        $this->extract_email_from_post()
                    )
                ));

                // Log debug information if enabled
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'CF7 Honeypot: Spam detected in form %d (Field: %s, IP: %s)',
                        $form_id,
                        $triggered_field,
                        $this->get_client_ip()
                    ));
                }

                // Optional: Block IP if threshold exceeded
                if ($this->should_block_ip($this->get_client_ip())) {
                    $this->block_ip($this->get_client_ip());
                }
            }
        }

        return $result;
    }

    /**
     * Checks if an IP should be blocked based on spam attempts
     *
     * @param string $ip IP address to check
     * @return boolean
     */
    private function should_block_ip($ip)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Get block threshold from settings
        $settings = get_option('cf7_honeypot_settings');
        $threshold = isset($settings['block_threshold']) ? (int) $settings['block_threshold'] : 5;

        // Count recent attempts from this IP
        $attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
        WHERE ip_address = %s
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $ip
        ));

        return $attempts >= $threshold;
    }

    /**
     * Blocks an IP address
     *
     * @param string $ip IP address to block
     */
    private function block_ip($ip)
    {
        $blocked_ips = get_option('cf7_honeypot_blocked_ips', array());
        $blocked_ips[$ip] = time();
        update_option('cf7_honeypot_blocked_ips', $blocked_ips);
    }

    /**
     * Checks if an IP is currently blocked and handles expiration.
     *
     * @param string $ip IP address to check
     * @return bool True if blocked, false otherwise
     */
    private function is_ip_blocked($ip)
    {
        $blocked_ips = get_option('cf7_honeypot_blocked_ips', array());

        if (!isset($blocked_ips[$ip])) {
            return false;
        }

        $settings = get_option('cf7_honeypot_settings');
        $duration = isset($settings['block_duration']) ? (int) $settings['block_duration'] : 24;
        $expires  = $blocked_ips[$ip] + ($duration * HOUR_IN_SECONDS);

        if (time() < $expires) {
            return true;
        }

        // Remove expired IP
        unset($blocked_ips[$ip]);
        update_option('cf7_honeypot_blocked_ips', $blocked_ips);

        return false;
    }

    /**
     * Gestisce le richieste di pulizia dei log
     */
    public function handle_cleanup_request()
    {
        // Verifica se siamo nella pagina corretta
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'cf7-honeypot-stats') {
            return;
        }

        // Verifica se è stata inviata una richiesta di pulizia
        if (!isset($_POST['cleanup_period'])) {
            return;
        }

        // Verifica il nonce
        if (!isset($_POST['cleanup_nonce']) || !wp_verify_nonce($_POST['cleanup_nonce'], 'cf7_honeypot_cleanup')) {
            wp_die('Security check failed');
        }

        // Verifica i permessi
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $period = sanitize_text_field($_POST['cleanup_period']);

        // Esegui la pulizia
        $this->cleanup_old_logs($period);

        // Imposta un messaggio di successo
        $message = '';
        switch ($period) {
            case '1':
                $message = __('Logs from the last 24 hours have been cleared.', 'cf7-honeypot');
                break;
            case '7':
                $message = __('Logs from the last 7 days have been cleared.', 'cf7-honeypot');
                break;
            case '30':
                $message = __('Logs from the last 30 days have been cleared.', 'cf7-honeypot');
                break;
            case 'all':
                $message = __('All logs have been cleared.', 'cf7-honeypot');
                break;
        }

        // Redirect con messaggio
        wp_redirect(add_query_arg(
            array(
                'page' => 'cf7-honeypot-stats',
                'message' => urlencode($message),
                'status' => 'success'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Logs a spam attempt with detailed information
     *
     * @param int    $form_id         The ID of the form that triggered the spam detection
     * @param string $triggered_field The field ID that triggered the spam detection
     * @return bool|int False on failure, number of rows affected on success
     */
    private function log_spam_attempt($form_id, $triggered_field = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Verify and update database structure if needed
        $this->update_database_structure();

        // Prepare data for logging
        $data = array(
            'form_id' => $form_id,
            'honeypot_triggered' => 1,
            'ip_address' => $this->get_client_ip(),
            'email' => $this->extract_email_from_post(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ?
                sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'referrer_url' => isset($_SERVER['HTTP_REFERER']) ?
                esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'triggered_field' => $triggered_field,
            'browser_fingerprint' => $this->generate_browser_fingerprint(),
            'country_code' => $this->get_country_from_ip($this->get_client_ip()),
            'risk_score' => $this->calculate_risk_score($this->get_client_ip(), $this->extract_email_from_post()),
            'created_at' => current_time('mysql', true)
        );

        // Verify which columns actually exist in the database
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
        foreach ($data as $key => $value) {
            if (!in_array($key, $columns)) {
                unset($data[$key]);
            }
        }

        // Format for wpdb
        $format = array_fill(0, count($data), '%s');

        // Attempt to insert the data
        $result = $wpdb->insert($table_name, $data, $format);

        // Log any database errors if WP_DEBUG is enabled
        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CF7 Honeypot Error: ' . $wpdb->last_error);
            }
            return false;
        }

        // Trigger action for potential extensions
        do_action('cf7_honeypot_after_log_spam', $form_id, $data);

        // Clear any relevant caches
        wp_cache_delete('cf7_honeypot_recent_attempts');
        delete_transient('cf7_honeypot_stats_' . $form_id);

        // Return the result
        return $result;
    }

    /**
     * Checks if the current submission is spam
     *
     * @param array $field_ids Array of honeypot field IDs
     * @return boolean
     */
    private function is_spam_submission($field_ids = null)
    {
        if ($field_ids === null) {
            $field_ids = $this->get_cached_field_ids();
        }

        foreach ($_POST as $key => $value) {
            if (in_array($key, $field_ids) && !empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the first triggered honeypot field
     *
     * @param array $field_ids Array of honeypot field IDs
     * @return string|null
     */
    private function get_triggered_field($field_ids)
    {
        foreach ($_POST as $key => $value) {
            if (in_array($key, $field_ids) && !empty($value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Estrae l'email dai dati POST
     */
    private function extract_email_from_post()
    {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'email') !== false || strpos($key, 'your-email') !== false) {
                return sanitize_email($value);
            }
        }
        return '';
    }

    public function block_cfdb7_if_spam($form_data)
    {
        $field_ids = $this->get_cached_field_ids();

        foreach ($_POST as $key => $value) {
            if (in_array($key, $field_ids) && !empty($value)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CF7 Honeypot: Blocking CFDB7 save due to spam detection');
                }
                return false;
            }
        }

        return $form_data;
    }

    private function mark_as_spam($form_id)
    {
        // Imposta i flag necessari
        update_option('cf7_honeypot_last_spam_' . $form_id, time());

        // Notifica gli admin se necessario
        if (get_option('cf7_honeypot_notify_admin', false)) {
            $this->notify_admin_of_spam($form_id);
        }

        // Blocca ulteriori processamenti
        remove_all_filters('wpcf7_posted_data');
        remove_all_actions('wpcf7_before_send_mail');

        return false;
    }

    /**
     * Genera un fingerprint del browser
     */
    private function generate_browser_fingerprint()
    {
        $data = [
            isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '',
            isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ''
        ];

        return md5(implode('|', $data));
    }

    /**
     * Calcola un punteggio di rischio
     */
    private function calculate_risk_score($ip, $email)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        $score = 0;

        // Controllo frequenza IP
        $ip_attempts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $ip
        ));

        // Controllo frequenza Email
        $email_attempts = 0;
        if (!empty($email)) {
            $email_attempts = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $email
            ));
        }

        // Calcolo score
        if ($ip_attempts > 5)
            $score += 30;
        if ($ip_attempts > 10)
            $score += 30;
        if ($email_attempts > 3)
            $score += 20;
        if ($email_attempts > 7)
            $score += 20;

        return min(100, $score);
    }

    /**
     * Ottiene il codice paese dall'IP
     */
    private function get_country_from_ip($ip)
    {
        $ip = sanitize_text_field($ip);

        if (empty($ip) || 'UNKNOWN' === $ip) {
            return 'XX';
        }

        $response = wp_remote_get('http://ip-api.com/json/' . $ip . '?fields=countryCode');

        if (is_wp_error($response)) {
            return 'XX';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['countryCode'])) {
            return strtoupper(sanitize_text_field($data['countryCode']));
        }

        return 'XX';
    }

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
                if ($ip === '::1')
                    return '127.0.0.1';
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

    /**
     * Ottiene i field IDs dalla cache o dal database
     *
     * @return array
     */
    private function get_cached_field_ids()
    {
        // Prova prima con il cache object
        $field_ids = wp_cache_get('cf7_honeypot_field_ids');

        if (false === $field_ids) {
            // Prova con il transient se l'object cache fallisce
            $field_ids = get_transient($this->cache_key);

            if (false === $field_ids) {
                global $wpdb;
                $table_name = $wpdb->prefix . $this->questions_table;

                $field_ids = $wpdb->get_col("SELECT field_id FROM $table_name");

                // Salva in entrambe le cache
                wp_cache_set('cf7_honeypot_field_ids', $field_ids, '', 3600);
                set_transient($this->cache_key, $field_ids, $this->cache_duration);
            }
        }

        return is_array($field_ids) ? $field_ids : array();
    }

    private function handle_spam_event($form_id, $field_id)
    {
        // Logga il tentativo
        $this->log_spam_attempt($form_id, $field_id);

        // Blocca tutte le azioni successive
        add_filter('wpcf7_skip_mail', '__return_true');
        add_filter('cfdb7_before_save_data', '__return_false');
        remove_all_actions('wpcf7_before_send_mail');
        remove_all_actions('wpcf7_mail_sent');

        // Notifica gli admin se l'opzione è attiva
        if (get_option('cf7_honeypot_notify_admin', false)) {
            $this->notify_admin_of_spam($form_id);
        }

        return false;
    }


    /**
     * Invalida la cache dei field IDs
     * Da chiamare quando vengono modificate le domande
     */
    private function invalidate_field_ids_cache()
    {
        delete_transient($this->cache_key);
    }

    public function check_for_spam($spam, $submission)
    {
        if (!$submission) {
            return $spam;
        }

        $field_ids = $this->get_cached_field_ids();
        $posted_data = $submission->get_posted_data();

        foreach ($field_ids as $field_id) {
            if (isset($posted_data[$field_id]) && !empty($posted_data[$field_id])) {
                // Log del tentativo
                $this->log_spam_attempt($submission->get_contact_form()->id(), $field_id);

                // Imposta come spam
                $submission->set_status('spam');

                // Blocca email e CFDB7
                add_filter('wpcf7_skip_mail', '__return_true');
                add_filter('cfdb7_before_save_data', '__return_false');

                return true;
            }
        }

        return $spam;
    }

    public function init_ajax_handlers()
    {
        add_action('wp_ajax_cf7_honeypot_delete_records', array($this, 'ajax_delete_records'));
        add_action('wp_ajax_nopriv_cf7_honeypot_delete_records', array($this, 'ajax_delete_records'));
        add_action('wp_ajax_cf7_honeypot_unblock_ip', array($this, 'ajax_unblock_ip'));
    }

    public function ajax_delete_records()
    {
        check_ajax_referer('cf7_honeypot_delete_records', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();

        if (empty($ids)) {
            wp_send_json_error('No records selected');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->stats_table;

        // Esegui la cancellazione
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                $ids
            )
        );

        if ($deleted === false) {
            wp_send_json_error('Database error');
        }

        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    '%s record deleted successfully.',
                    '%s records deleted successfully.',
                    $deleted,
                    'cf7-honeypot'
                ),
                number_format_i18n($deleted)
            )
        ));
    }

    /**
     * AJAX handler to manually unblock an IP address.
     */
    public function ajax_unblock_ip()
    {
        check_ajax_referer('cf7_honeypot_unblock_ip', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';

        if (empty($ip)) {
            wp_send_json_error('Invalid IP');
        }

        $blocked_ips = get_option('cf7_honeypot_blocked_ips', array());

        if (isset($blocked_ips[$ip])) {
            unset($blocked_ips[$ip]);
            update_option('cf7_honeypot_blocked_ips', $blocked_ips);
            wp_send_json_success(array('message' => __('IP removed', 'cf7-honeypot')));
        }

        wp_send_json_error('IP not found');
    }

    /**
     * Verifica la presenza di spam prima del salvataggio CFDB7
     *
     * @param WPCF7_ContactForm $contact_form
     * @return void
     */
    public function check_spam_before_save($contact_form)
    {
        // Otteniamo i field IDs dalla cache
        $field_ids = $this->get_cached_field_ids();

        foreach ($_POST as $key => $value) {
            if (in_array($key, $field_ids) && !empty($value)) {
                // Logghiamo il tentativo di spam con il campo che ha triggerato
                $this->log_spam_attempt($contact_form->id(), $key);

                $submission = WPCF7_Submission::get_instance();
                if ($submission) {
                    $submission->set_status('spam');
                    $submission->set_response($contact_form->message('spam'));

                    // Preveniamo il salvataggio in CFDB7
                    add_filter('wpcf7_skip_mail', '__return_true');

                    // Invece di wp_send_json_error, lasciamo che CF7 gestisca la risposta
                    return;
                }
            }
        }
    }

    public function add_admin_menu()
    {
        // Menu principale
        add_menu_page(
            __('CF7 Honeypot', 'cf7-honeypot'),
            __('CF7 Honeypot', 'cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-stats', // Questo è l'slug del menu principale
            array($this, 'render_stats_page'),
            'dashicons-shield',
            30
        );

        // Sottomenu Statistiche (per rendere esplicito il primo elemento del menu)
        add_submenu_page(
            'cf7-honeypot-stats',
            __('Statistics', 'cf7-honeypot'),
            __('Statistics', 'cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-stats',
            array($this, 'render_stats_page')
        );

        // Sottomenu Impostazioni
        add_submenu_page(
            'cf7-honeypot-stats',
            __('Settings', 'cf7-honeypot'),
            __('Settings', 'cf7-honeypot'),
            'manage_options',
            'cf7-honeypot-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Renderizza la pagina delle impostazioni
     *
     * @return void
     */
    public function render_settings_page()
    {
        // Verifica i permessi
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include il template delle impostazioni
        include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
    }

    public function render_stats_page()
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        // Get time-based statistics
        $last_24h = $wpdb->get_var("
        SELECT COUNT(*) FROM $stats_table
        WHERE honeypot_triggered = 1
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");

        $last_7d = $wpdb->get_var("
        SELECT COUNT(*) FROM $stats_table
        WHERE honeypot_triggered = 1
        AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");

        $last_30d = $wpdb->get_var("
        SELECT COUNT(*) FROM $stats_table
        WHERE honeypot_triggered = 1
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

        // Get basic statistics
        $total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $stats_table WHERE honeypot_triggered = 1");

        // Get recent attempts with enhanced information
        $recent_attempts = $wpdb->get_results("
        SELECT s.*,
            p.post_title as form_title,
            (SELECT COUNT(*)
             FROM {$stats_table}
             WHERE ip_address = s.ip_address
             AND honeypot_triggered = 1) as attempts_count,
            (SELECT GROUP_CONCAT(DISTINCT form_id)
             FROM {$stats_table}
             WHERE ip_address = s.ip_address
             AND honeypot_triggered = 1) as targeted_forms
        FROM {$stats_table} s
        LEFT JOIN {$wpdb->posts} p ON s.form_id = p.ID
        WHERE s.honeypot_triggered = 1
        ORDER BY s.created_at DESC
        LIMIT 10
    ");

        // Get summary statistics with null coalesce per gestire colonne potenzialmente mancanti
        $summary_stats = $wpdb->get_results("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as attempts,
            COUNT(DISTINCT ip_address) as unique_ips,
            COUNT(DISTINCT email) as unique_emails,
            COUNT(DISTINCT form_id) as forms_affected,
            COUNT(DISTINCT COALESCE(user_agent, 'unknown')) as unique_browsers,
            COUNT(DISTINCT COALESCE(triggered_field, 'unknown')) as unique_fields_triggered,
            COUNT(DISTINCT COALESCE(referrer_url, 'unknown')) as unique_referrers
        FROM $stats_table
        WHERE honeypot_triggered = 1
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");

        // Pass variables to template
        $template_vars = array(
            'last_24h' => $last_24h,
            'last_7d' => $last_7d,
            'last_30d' => $last_30d,
            'total_attempts' => $total_attempts,
            'recent_attempts' => $recent_attempts,
            'summary_stats' => $summary_stats,
            'stats_table' => $stats_table,
            'instance' => $this
        );

        // Extract variables for the template
        extract($template_vars);

        // Definisci le funzioni helper qui per renderle disponibili al template
        if (!function_exists('get_risk_level')) {
            function get_risk_level($attempts_count)
            {
                if ($attempts_count > 5)
                    return 'high';
                if ($attempts_count > 2)
                    return 'medium';
                return 'low';
            }
        }

        if (!function_exists('get_risk_label')) {
            function get_risk_label($risk_level)
            {
                switch ($risk_level) {
                    case 'high':
                        return __('High Risk', 'cf7-honeypot');
                    case 'medium':
                        return __('Medium Risk', 'cf7-honeypot');
                    default:
                        return __('Low Risk', 'cf7-honeypot');
                }
            }
        }

        // Include the template
        include(plugin_dir_path(__FILE__) . 'templates/stats-page.php');
    }

    /**
     * Miglioriamo anche la funzione di pulizia
     */
    public function cleanup_old_logs($period = '30')
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . $this->stats_table;

        if ($period === 'all') {
            $result = $wpdb->query("TRUNCATE TABLE {$stats_table}");
        } else {
            $days = intval($period);
            if (!in_array($days, [1, 7, 30])) {
                $days = 30;
            }
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$stats_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            );
        }

        // Log dell'operazione se attivo WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'CF7 Honeypot: Cleaned up logs. Period: %s, Rows affected: %d',
                $period,
                $result
            ));
        }

        return $result;
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('cf7_honeypot_cleanup');
    }

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

// Plugin initialization functions
function cf7_advanced_honeypot_init()
{
    return CF7_Advanced_Honeypot::get_instance();
}

function cf7_advanced_honeypot_admin_styles()
{
    $instance = CF7_Advanced_Honeypot::get_instance();
    if (method_exists($instance, 'enqueue_admin_styles')) {
        $instance->enqueue_admin_styles();
    }
}

// Hook registrations
add_action('admin_enqueue_scripts', 'cf7_advanced_honeypot_admin_styles');

function cf7_advanced_honeypot_deactivate()
{
    CF7_Advanced_Honeypot::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('CF7_Advanced_Honeypot', 'activate_plugin'));
register_deactivation_hook(__FILE__, 'cf7_advanced_honeypot_deactivate');
add_action('plugins_loaded', 'cf7_advanced_honeypot_init');

// Add cron schedule
add_filter('cron_schedules', 'add_twiceweekly_schedule');
function add_twiceweekly_schedule($schedules)
{
    $schedules['twiceweekly'] = array(
        'interval' => 302400, // 3.5 days in seconds
        'display' => __('Twice Weekly', 'cf7-honeypot')
    );
    return $schedules;
}
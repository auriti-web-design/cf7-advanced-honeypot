<?php
if (!defined('ABSPATH')) exit;

$blocked_ips = get_option('cf7_honeypot_blocked_ips', array());
$settings    = get_option('cf7_honeypot_settings');
$duration    = isset($settings['block_duration']) ? (int) $settings['block_duration'] : 24;
?>
<div class="cf7-honeypot-wrapper">
    <h1><?php esc_html_e('Blocked IP Addresses', 'cf7-honeypot'); ?></h1>
    <?php if (empty($blocked_ips)) : ?>
        <p><?php esc_html_e('No blocked IPs.', 'cf7-honeypot'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('IP Address', 'cf7-honeypot'); ?></th>
                    <th><?php esc_html_e('Unblock Date', 'cf7-honeypot'); ?></th>
                    <th><?php esc_html_e('Actions', 'cf7-honeypot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocked_ips as $ip => $time) :
                    $unblock = $time + ($duration * HOUR_IN_SECONDS); ?>
                    <tr data-ip="<?php echo esc_attr($ip); ?>">
                        <td><?php echo esc_html($ip); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', $unblock)); ?></td>
                        <td><button type="button" class="button unblock-ip" data-ip="<?php echo esc_attr($ip); ?>"><?php esc_html_e('Remove', 'cf7-honeypot'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
    wp_localize_script('cf7-honeypot-admin', 'cf7HoneypotAdmin', array(
        'unblockNonce' => wp_create_nonce('cf7_honeypot_unblock_ip')
    ));
    ?>
</div>

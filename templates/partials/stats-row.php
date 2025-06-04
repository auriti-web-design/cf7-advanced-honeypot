<?php if (!defined('ABSPATH')) exit; ?>
<tr data-record-id="<?php echo esc_attr($attempt->id); ?>">
    <td class="column-cb check-column">
        <input type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($attempt->id); ?>">
    </td>
    <td><?php echo esc_html(wp_date('d/m/Y H:i:s', strtotime($attempt->created_at))); ?></td>
    <td><?php echo esc_html($attempt->form_title ?: 'Form #' . $attempt->form_id); ?></td>
    <td><?php echo esc_html($attempt->ip_address); ?></td>
    <td><?php echo esc_html($attempt->email ?: 'N/A'); ?></td>
    <td><?php echo esc_html($attempt->triggered_field ?: 'Unknown'); ?></td>
    <td>
        <span class="status-badge risk-<?php echo esc_attr(get_risk_level($attempt->attempts_count)); ?>">
            <?php echo esc_html(get_risk_label(get_risk_level($attempt->attempts_count))); ?>
        </span>
    </td>
    <td>
        <button type="button" class="button button-small show-details"
            data-ua="<?php echo esc_attr($attempt->user_agent); ?>"
            data-referrer="<?php echo esc_attr($attempt->referrer_url); ?>">
            <?php esc_html_e('Show Details', 'cf7-honeypot'); ?>
        </button>
    </td>
</tr>
<tr class="details-row hidden">
    <td colspan="8">
        <div class="details-content">
            <p><strong><?php esc_html_e('Browser:', 'cf7-honeypot'); ?></strong>
                <?php echo esc_html($attempt->user_agent ?: 'Unknown'); ?></p>
            <p><strong><?php esc_html_e('Referrer:', 'cf7-honeypot'); ?></strong>
                <?php echo esc_html($attempt->referrer_url ?: 'Direct Access'); ?></p>
            <p><strong><?php esc_html_e('Total Attempts:', 'cf7-honeypot'); ?></strong>
                <?php echo esc_html($attempt->attempts_count); ?></p>
        </div>
    </td>
</tr>

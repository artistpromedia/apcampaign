<?php
/**
 * Safe queue processing, compliance footer, and first-party tracking.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class APCampaign_Delivery
{
    private const CRON_HOOK = 'apcampaign_process_send_job';

    public static function boot(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'process_job']);
        add_action('template_redirect', [self::class, 'maybe_handle_public_event'], 0);
    }

    public static function schedule(int $job_id, int $timestamp): bool
    {
        if ($job_id < 1 || $timestamp <= time()) {
            return false;
        }
        return (bool) wp_schedule_single_event($timestamp, self::CRON_HOOK, [$job_id]);
    }

    /**
     * Process at most 100 recipients. A continuation is scheduled when needed.
     *
     * @return array<string,mixed>
     */
    public static function process_job(int $job_id): array
    {
        if ($job_id < 1) return ['success' => false, 'error' => 'Invalid send job.'];
        global $wpdb;
        static $running = [];
        if (isset($running[$job_id])) return ['success' => false, 'error' => 'Send job is already processing.'];
        // The named lock is shared by separate PHP workers, without changing the
        // connection's transaction state. Fail closed when it cannot be acquired.
        $lock = 'apcampaign_' . substr(hash('sha256', $wpdb->prefix . ':' . $job_id), 0, 48);
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock));
        if ((string) $acquired !== '1') return ['success' => false, 'error' => 'Send job is already processing or its lock is unavailable.'];
        $running[$job_id] = true;
        try {
            return self::process_locked_job($job_id);
        } finally {
            unset($running[$job_id]);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Reconcile one uncertain handoff after an administrator checks provider logs.
     * A rejected message is recorded as failed, never silently retried.
     */
    public static function resolve_uncertain(int $job_id, int $recipient_id, string $outcome): array
    {
        if ($job_id < 1 || $recipient_id < 1 || !in_array($outcome, ['accepted', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid review choice.'];
        }
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $lock = 'apcampaign_' . substr(hash('sha256', $wpdb->prefix . ':' . $job_id), 0, 48);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') {
            return ['success' => false, 'error' => 'The send is still processing. Try again later.'];
        }
        try {
            $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['send_jobs'], $job_id), ARRAY_A);
            $recipient = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d AND send_job_id = %d AND status = 'sending'", $t['send_job_recipients'], $recipient_id, $job_id), ARRAY_A);
            if (!$job || !$recipient || !in_array((string) $job['status'], ['review_required', 'sending'], true)) {
                return ['success' => false, 'error' => 'This recipient is no longer awaiting review.'];
            }
            // Active workers hold the same lock. A crash can leave the job in sending state.
            $saved = $wpdb->update($t['send_job_recipients'], [
                'status' => $outcome === 'accepted' ? 'sent' : 'failed',
                'sent_at' => $outcome === 'accepted' ? current_time('mysql') : null,
                'error_message' => $outcome === 'accepted' ? 'Provider acceptance confirmed by an administrator.' : 'Provider rejection confirmed by an administrator.',
                'updated_at' => current_time('mysql'),
            ], ['id' => $recipient_id, 'send_job_id' => $job_id, 'status' => 'sending']);
            if ($saved !== 1) return ['success' => false, 'error' => 'The review could not be saved.'];
            self::record_event($recipient, $outcome === 'accepted' ? 'sent' : 'failed');
            $remaining_uncertain = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE send_job_id = %d AND status = 'sending'", $t['send_job_recipients'], $job_id));
            if ($remaining_uncertain === null || (int) $remaining_uncertain > 0) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
            $updated = $wpdb->update($t['send_jobs'], ['status' => 'queued', 'error_message' => '', 'updated_at' => current_time('mysql')], ['id' => $job_id]);
            if ($updated !== 1) return ['success' => false, 'error' => 'Review saved, but the send could not be resumed. Contact support.'];
            $wpdb->update($t['campaigns'], ['status' => 'sending', 'updated_at' => current_time('mysql')], ['id' => (int) $job['campaign_id']]);
            if (!self::schedule($job_id, time() + 10)) {
                self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
                return ['success' => false, 'error' => 'Review saved, but continuation could not be scheduled. Contact support.'];
            }
            return ['success' => true];
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Resume a paused job only after every uncertain recipient has been reviewed.
     */
    public static function resume_reviewed_job(int $job_id): array
    {
        if ($job_id < 1) return ['success' => false, 'error' => 'Invalid send job.'];
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $lock = 'apcampaign_' . substr(hash('sha256', $wpdb->prefix . ':' . $job_id), 0, 48);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') {
            return ['success' => false, 'error' => 'The send is still processing. Try again later.'];
        }
        try {
            $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['send_jobs'], $job_id), ARRAY_A);
            if (!$job || (string) $job['status'] !== 'review_required') {
                return ['success' => false, 'error' => 'This send is not awaiting review.'];
            }
            $pending = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE send_job_id = %d AND status = 'sending'", $t['send_job_recipients'], $job_id));
            if ($pending === null || (int) $pending > 0) {
                return ['success' => false, 'error' => 'Review every uncertain recipient before resuming.'];
            }
            // Schedule while still paused; an event that fires early fails closed.
            if (!wp_next_scheduled(self::CRON_HOOK, [$job_id]) && !self::schedule($job_id, time() + 60)) {
                return ['success' => false, 'error' => 'WordPress could not schedule the send. Try again or contact support.'];
            }
            $updated = $wpdb->update($t['send_jobs'], ['status' => 'queued', 'error_message' => '', 'updated_at' => current_time('mysql')], ['id' => $job_id, 'status' => 'review_required']);
            if ($updated !== 1) return ['success' => false, 'error' => 'The send could not be resumed. Contact support.'];
            $wpdb->update($t['campaigns'], ['status' => 'sending', 'updated_at' => current_time('mysql')], ['id' => (int) $job['campaign_id']]);
            return ['success' => true];
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private static function pause_uncertain_job(int $job_id, int $campaign_id): array
    {
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $message = 'Sending paused because delivery could not be confirmed. Check the mail provider logs and contact support before sending this campaign again.';
        $wpdb->update($t['send_jobs'], ['status' => 'review_required', 'error_message' => $message, 'updated_at' => current_time('mysql')], ['id' => $job_id]);
        $wpdb->update($t['campaigns'], ['status' => 'review_required', 'updated_at' => current_time('mysql')], ['id' => $campaign_id]);
        return ['success' => false, 'error' => $message];
    }

    private static function process_locked_job(int $job_id): array
    {
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['send_jobs'], $job_id), ARRAY_A);
        if (!$job || !in_array((string) $job['status'], ['queued', 'scheduled', 'sending'], true)) {
            return ['success' => false, 'error' => 'Send job is not processable.'];
        }

        if ((string) $job['status'] === 'scheduled') {
            $settings = json_decode((string) ($job['settings_json'] ?? ''), true);
            if (is_array($settings) && array_key_exists('scheduled_timestamp', $settings)) {
                $timestamp = $settings['scheduled_timestamp'];
                if (!is_int($timestamp) || $timestamp <= 0 || $timestamp > time()) return ['success' => false, 'error' => 'Scheduled send is not due yet or has an invalid time.'];
            } else {
                // Older scheduled jobs stored local time only; retain their existing compatibility.
                $scheduled_at = (string) ($job['scheduled_at'] ?? '');
                $due = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $scheduled_at, wp_timezone());
                if (!$due || $due->format('Y-m-d H:i:s') !== $scheduled_at || $due->getTimestamp() > time()) return ['success' => false, 'error' => 'Scheduled send is not due yet or has an invalid time.'];
            }
        }

        // Do not retry a recipient whose handoff may have been interrupted.
        $pending = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE send_job_id = %d AND status = 'sending'", $t['send_job_recipients'], $job_id));
        if ($pending === null || (int) $pending > 0) {
            return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        }
        if (!self::compliance_ready()) {
            $wpdb->update($t['send_jobs'], [
                'status' => 'failed',
                'error_message' => 'A physical mailing address is required before live sending.',
                'updated_at' => current_time('mysql'),
            ], ['id' => $job_id]);
            return ['success' => false, 'error' => 'Physical mailing address is required.'];
        }

        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['campaigns'], (int) $job['campaign_id']), ARRAY_A);
        $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['content_snapshots'], (int) $job['snapshot_id']), ARRAY_A);
        if (!$campaign || !$snapshot) {
            return ['success' => false, 'error' => 'Campaign content is missing.'];
        }

        $now = current_time('mysql');
        $started = $wpdb->update($t['send_jobs'], [
            'status' => 'sending',
            'started_at' => $job['started_at'] ?: $now,
            'updated_at' => $now,
        ], ['id' => $job_id]);
        if ($started === false) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);

        $recipients = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE send_job_id = %d AND status = 'queued' ORDER BY id ASC LIMIT 100", $t['send_job_recipients'],
            $job_id
        ), ARRAY_A);
        if ($recipients === null || $wpdb->last_error !== '') return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($recipients as $recipient) {
            $email = sanitize_email((string) $recipient['email']);
            $suppressed = $email === '' ? true : self::is_suppressed($email);
            if ($suppressed === null) {
                $failed++;
                $saved = $wpdb->update($t['send_job_recipients'], [
                    'status' => 'failed',
                    'error_message' => 'Could not verify recipient suppression.',
                    'updated_at' => current_time('mysql'),
                ], ['id' => (int) $recipient['id'], 'send_job_id' => $job_id, 'status' => 'queued']);
                if ($saved !== 1) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
                continue;
            }
            if ($suppressed) {
                $skipped++;
                $saved = $wpdb->update($t['send_job_recipients'], [
                    'status' => 'skipped',
                    'error_message' => 'Recipient is suppressed or invalid.',
                    'updated_at' => current_time('mysql'),
                ], ['id' => (int) $recipient['id'], 'send_job_id' => $job_id, 'status' => 'queued']);
                if ($saved !== 1) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
                continue;
            }

            $personalized = self::personalize(
                (string) $snapshot['rendered_html'],
                (string) $campaign['subject_line'],
                $recipient
            );
            $html = self::decorate_html(
                APCampaign_Renderer::wrap_document($personalized['html'], $personalized['subject']),
                (string) $recipient['tracking_token']
            );
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . sanitize_text_field((string) $campaign['from_name']) . ' <' . sanitize_email((string) $campaign['from_email']) . '>',
            ];
            if (is_email((string) $campaign['reply_to_email'])) {
                $headers[] = 'Reply-To: ' . sanitize_email((string) $campaign['reply_to_email']);
            }

            $claimed = $wpdb->update($t['send_job_recipients'], ['status' => 'sending', 'updated_at' => current_time('mysql')], ['id' => (int) $recipient['id'], 'send_job_id' => $job_id, 'status' => 'queued']);
            if ($claimed !== 1) {
            return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
            }
            try {
                do_action('apcampaign_before_mail');
                try { $accepted = apply_filters('apcampaign_allow_mail', true) ? wp_mail($email, $personalized['subject'], $html, $headers) : false; }
                finally { do_action('apcampaign_after_mail'); }
            } catch (Throwable $error) {
                return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
            }
            $saved = $wpdb->update($t['send_job_recipients'], [
                'status' => $accepted ? 'sent' : 'failed',
                'sent_at' => $accepted ? current_time('mysql') : null,
                'error_message' => $accepted ? '' : 'WordPress mail transport rejected the message.',
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $recipient['id'], 'send_job_id' => $job_id, 'status' => 'sending']);
            if ($saved !== 1) {
                return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
            }
            self::record_event($recipient, $accepted ? 'sent' : 'failed');
            $accepted ? $sent++ : $failed++;
        }

        $remaining = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE send_job_id = %d AND status = 'queued'", $t['send_job_recipients'],
            $job_id
        ));
        if ($remaining === null || $wpdb->last_error !== '') return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        $remaining = (int) $remaining;
        $counts = $wpdb->get_row($wpdb->prepare("SELECT
                SUM(status = 'sent') AS sent_count,
                SUM(status = 'failed') AS failed_count,
                SUM(status = 'failed' AND error_message = 'Could not verify recipient suppression.') AS suppression_errors,
                SUM(status = 'skipped') AS skipped_count
             FROM %i WHERE send_job_id = %d", $t['send_job_recipients'],
            $job_id
        ), ARRAY_A);
        if ($counts === null || $wpdb->last_error !== '') return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        $complete = $remaining === 0;
        $has_failures = (int) ($counts['failed_count'] ?? 0) > 0;
        $summary_saved = $wpdb->update($t['send_jobs'], [
            'status' => $complete ? ($has_failures ? 'failed' : 'completed') : 'sending',
            'sent_count' => (int) ($counts['sent_count'] ?? 0),
            'failed_count' => (int) ($counts['failed_count'] ?? 0),
            'error_message' => (int) ($counts['suppression_errors'] ?? 0) > 0 ? 'Could not verify recipient suppression. No message was sent to affected recipients.' : (string) ($job['error_message'] ?? ''),
            'skipped_count' => (int) ($counts['skipped_count'] ?? 0),
            'completed_at' => $complete ? current_time('mysql') : null,
            'updated_at' => current_time('mysql'),
        ], ['id' => $job_id]);

        if ($summary_saved === false) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        if ($complete) {
            $campaign_saved = $wpdb->update($t['campaigns'], [
                'status' => $has_failures ? 'failed' : 'sent',
                'sent_at' => $has_failures ? null : current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $job['campaign_id']]);
            if ($campaign_saved === false) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        } else {
            if (!self::schedule($job_id, time() + 60)) return self::pause_uncertain_job($job_id, (int) $job['campaign_id']);
        }

        return [
            'success' => !($complete && $has_failures),
            'complete' => $complete,
            'sent_this_run' => $sent,
            'failed_this_run' => $failed,
            'skipped_this_run' => $skipped,
            'remaining' => $remaining,
        ];
    }

    public static function maybe_handle_public_event(): void
    {
        $event = sanitize_key((string) wp_unslash($_GET['apcampaign_event'] ?? ''));
        $token = sanitize_text_field((string) wp_unslash($_GET['token'] ?? ''));
        if ($event === '' || $token === '') {
            return;
        }

        global $wpdb;
        $t = APCampaign_Schema::tables();
        $recipient = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE tracking_token = %s LIMIT 1", $t['send_job_recipients'],
            $token
        ), ARRAY_A);
        if (!$recipient) {
            status_header(404);
            exit;
        }

        if ($event === 'open') {
            self::record_event($recipient, 'open');
            nocache_headers();
            header('Content-Type: image/gif');
            echo "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x4c\x00\x3b";
            exit;
        }

        if ($event === 'unsubscribe') {
            $suppression = APCampaign_Lifecycle::suppress_email((string) $recipient['email'], 'unsubscribe', 'apcampaign');
            if (empty($suppression['suppression_id']) || !empty($suppression['error'])) {
                status_header(503);
                nocache_headers();
                echo '<!doctype html><meta charset="utf-8"><title>Unsubscribe unavailable</title><main style="max-width:640px;margin:10vh auto;font:18px/1.5 system-ui;padding:24px"><h1>We could not process your unsubscribe request</h1><p>Please try again later or contact the sender directly.</p></main>';
                exit;
            }
            $wpdb->update($t['send_job_recipients'], [
                'status' => 'unsubscribed',
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $recipient['id']]);
            self::record_event($recipient, 'unsubscribe');
            status_header(200);
            nocache_headers();
            echo '<!doctype html><meta charset="utf-8"><title>Unsubscribed</title><main style="max-width:640px;margin:10vh auto;font:18px/1.5 system-ui;padding:24px"><h1>You are unsubscribed</h1><p>This email address will not receive future campaigns from this site.</p></main>';
            exit;
        }

        if ($event === 'click') {
            $destination = esc_url_raw((string) wp_unslash($_GET['url'] ?? ''));
            if (!self::destination_is_allowed($recipient, $destination)) {
                status_header(400);
                exit;
            }
            self::record_event($recipient, 'click', $destination);
            $destination_host = (string) wp_parse_url($destination, PHP_URL_HOST);
            add_filter('allowed_redirect_hosts', static function (array $hosts) use ($destination_host): array {
                if ($destination_host !== '') $hosts[] = $destination_host;
                return $hosts;
            });
            wp_safe_redirect($destination, 302, 'apCampaign');
            exit;
        }
    }

    private static function decorate_html(string $html, string $token): string
    {
        $html = preg_replace_callback(
            '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
            static function (array $matches) use ($token): string {
                $url = html_entity_decode((string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return 'href=' . $matches[1] . esc_url(self::event_url('click', $token, $url)) . $matches[1];
            },
            $html
        ) ?: $html;

        $address = esc_html((string) get_option('apcampaign_physical_address', ''));
        $unsubscribe = esc_url(self::event_url('unsubscribe', $token));
        $open = esc_url(self::event_url('open', $token));
        $footer = '<div style="margin:32px auto 0;padding:18px;max-width:600px;text-align:center;font:12px/1.5 Arial,sans-serif;color:#64748b">'
            . $address . '<br><a href="' . $unsubscribe . '">Unsubscribe</a></div>'
            . '<img src="' . $open . '" width="1" height="1" alt="" style="display:block;border:0">';
        return str_ireplace('</body>', $footer . '</body>', $html);
    }

    /**
     * Replace customer-owned merge tags at delivery time, after the audience
     * snapshot has frozen each recipient's values.
     *
     * Supported tags include first_name, last_name, full_name, company, email,
     * and any scalar value stored in the recipient merge map.
     *
     * @param array<string,mixed> $recipient
     * @return array{subject:string,html:string}
     */
    private static function personalize(string $html, string $subject, array $recipient): array
    {
        $stored = json_decode((string) ($recipient['recipient_data_json'] ?? '{}'), true);
        $stored = is_array($stored) ? $stored : [];
        $merge = isset($stored['merge']) && is_array($stored['merge']) ? $stored['merge'] : [];
        $merge['email'] = sanitize_email((string) ($recipient['email'] ?? ''));
        $merge['first_name'] = (string) ($merge['first_name'] ?? $stored['first_name'] ?? '');
        $merge['last_name'] = (string) ($merge['last_name'] ?? $stored['last_name'] ?? '');
        $merge['company'] = (string) ($merge['company'] ?? $stored['company'] ?? '');
        $merge['full_name'] = trim($merge['first_name'] . ' ' . $merge['last_name']);

        $replace = [];
        foreach ($merge as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $safe_key = sanitize_key((string) $key);
            if ($safe_key === '') {
                continue;
            }
            $replace['{{' . $safe_key . '}}'] = esc_html((string) $value);
            $replace['%%' . $safe_key . '%%'] = esc_html((string) $value);
        }

        return [
            'subject' => sanitize_text_field(html_entity_decode(strtr($subject, $replace), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'html' => strtr($html, $replace),
        ];
    }

    private static function event_url(string $event, string $token, string $destination = ''): string
    {
        $args = ['apcampaign_event' => $event, 'token' => $token];
        if ($destination !== '') {
            $args['url'] = $destination;
        }
        return add_query_arg($args, home_url('/'));
    }

    private static function destination_is_allowed(array $recipient, string $destination): bool
    {
        if (!wp_http_validate_url($destination)) {
            return false;
        }
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $html = (string) $wpdb->get_var($wpdb->prepare("SELECT s.rendered_html FROM %i s
             INNER JOIN %i j ON j.snapshot_id = s.id
             WHERE j.id = %d LIMIT 1", $t['content_snapshots'], $t['send_jobs'],
            (int) $recipient['send_job_id']
        ));
        // Match the same personalized links that are decorated for delivery.
        $html = self::personalize($html, '', $recipient)['html'];
        preg_match_all('/href=(["\'])(https?:\/\/[^"\']+)\1/i', $html, $links);
        foreach ($links[2] as $link) {
            if (html_entity_decode((string) $link, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $destination) {
                return true;
            }
        }
        return false;
    }

    private static function is_suppressed(string $email): ?bool
    {
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM %i WHERE email_hash = %s AND status = 'active' LIMIT 1", $t['suppressions'],
            hash('sha256', strtolower($email))
        ));
        // A failed lookup must never be treated as permission to send.
        return $wpdb->last_error !== '' ? null : (bool) $id;
    }

    public static function compliance_ready(): bool
    {
        return trim((string) get_option('apcampaign_physical_address', '')) !== '';
    }

    private static function record_event(array $recipient, string $event, string $destination = ''): void
    {
        global $wpdb;
        $t = APCampaign_Schema::tables();
        $wpdb->insert($t['tracking_events'], [
            'campaign_id' => (int) $recipient['campaign_id'],
            'send_job_id' => (int) $recipient['send_job_id'],
            'recipient_id' => (int) $recipient['id'],
            'tracking_token' => (string) $recipient['tracking_token'],
            'event_type' => sanitize_key($event),
            'email' => sanitize_email((string) $recipient['email']),
            'destination_url' => $destination,
            'event_data_json' => '{}',
            'occurred_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);
    }
}

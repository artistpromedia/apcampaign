<?php
/**
 * apCampaign lifecycle services.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Lifecycle')) {
    final class APCampaign_Lifecycle
    {
        /**
         * @return array<string,int>
         */
        public static function counts(): array
        {
            global $wpdb;
            $counts = [];
            foreach (APCampaign_Schema::tables() as $key => $table) {
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                $counts[$key] = $exists === $table ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table)) : 0;
            }
            return $counts;
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_campaign(array $data): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $campaign_key = sanitize_key((string) ($data['campaign_key'] ?? 'campaign_' . strtolower(wp_generate_password(10, false, false))));
            $status = sanitize_key((string) ($data['status'] ?? 'draft'));
            if (!in_array($status, APCampaign_Contract::campaign_lifecycle(), true)) {
                $status = 'draft';
            }
            $composer_mode = sanitize_key((string) ($data['composer_mode'] ?? 'block'));
            if (!in_array($composer_mode, APCampaign_Contract::composer_modes(), true)) {
                $composer_mode = 'block';
            }

            $inserted = $wpdb->insert($t['campaigns'], [
                'campaign_key' => $campaign_key,
                'name' => sanitize_text_field((string) ($data['name'] ?? 'Untitled Campaign')),
                'status' => $status,
                'composer_mode' => $composer_mode,
                'audience_key' => sanitize_key((string) ($data['audience_key'] ?? '')),
                'subject_line' => sanitize_text_field((string) ($data['subject_line'] ?? '')),
                'preheader_text' => sanitize_text_field((string) ($data['preheader_text'] ?? '')),
                'from_name' => sanitize_text_field((string) ($data['from_name'] ?? '')),
                'from_email' => sanitize_email((string) ($data['from_email'] ?? '')),
                'reply_to_email' => sanitize_email((string) ($data['reply_to_email'] ?? '')),
                'sender_identity_ref' => sanitize_text_field((string) ($data['sender_identity_ref'] ?? '')),
                'settings_json' => wp_json_encode((array) ($data['settings'] ?? [])),
                'source_plugin' => sanitize_key((string) ($data['source_plugin'] ?? '')),
                'source_record_id' => sanitize_text_field((string) ($data['source_record_id'] ?? '')),
                'created_by_user_id' => get_current_user_id(),
                'scheduled_at' => self::nullable_datetime($data['scheduled_at'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $campaign_id = (int) $wpdb->insert_id;
            if ($inserted === false || $campaign_id < 1) return ['error' => 'Could not create campaign.'];
            return ['campaign_id' => $campaign_id, 'campaign_key' => $campaign_key];
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function save_content_snapshot(int $campaign_id, array $data, ?array $campaign_fields = null): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $composer_mode = sanitize_key((string) ($data['composer_mode'] ?? 'block'));
            $design = (array) ($data['design'] ?? []);
            $raw_html = (string) ($data['raw_html'] ?? '');
            $rendered = (string) ($data['rendered_html'] ?? '');

            if ($composer_mode === 'block' && $rendered === '') {
                $rendered = APCampaign_Renderer::render_from_design($design);
            }
            if (($composer_mode === 'raw_html' || $composer_mode === 'imported_html') && $rendered === '') {
                $rendered = $raw_html;
            }

            $hash = hash('sha256', $composer_mode . '|' . wp_json_encode($design) . '|' . $raw_html . '|' . $rendered);
            $snapshot_key = sanitize_key((string) ($data['snapshot_key'] ?? 'snapshot_' . strtolower(wp_generate_password(10, false, false))));
            $inserted = $wpdb->insert($t['content_snapshots'], [
                'campaign_id' => $campaign_id,
                'snapshot_key' => $snapshot_key,
                'snapshot_type' => sanitize_key((string) ($data['snapshot_type'] ?? 'draft')),
                'composer_mode' => $composer_mode,
                'design_json' => wp_json_encode($design),
                'raw_html' => $raw_html,
                'rendered_html' => $rendered,
                'content_hash' => $hash,
                'renderer_version' => APCAMPAIGN_VERSION,
                'is_current' => 0,
                'created_by_user_id' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($inserted === false || (int) $wpdb->insert_id < 1) {
                return ['error' => 'Could not save campaign content. Your previous saved content is unchanged.'];
            }
            $snapshot_id = (int) $wpdb->insert_id;
            // One atomic update changes the current marker only after insertion succeeds.
            if ($campaign_fields === null) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE %i SET is_current = CASE WHEN id = %d THEN 1 ELSE 0 END, updated_at = %s WHERE campaign_id = %d",
                    $t['content_snapshots'], $snapshot_id, $now, $campaign_id
                ));
            } else {
                // Commit campaign details and the selected content in one statement.
                $keys = ['name', 'status', 'composer_mode', 'audience_key', 'subject_line', 'preheader_text', 'from_name', 'from_email', 'reply_to_email'];
                foreach ($keys as $key) {
                    if (!array_key_exists($key, $campaign_fields) || !is_string($campaign_fields[$key])) return ['error' => 'Could not save campaign details.'];
                }
                $updated = $wpdb->query($wpdb->prepare(
                    'UPDATE %i AS c JOIN %i AS s ON s.campaign_id = c.id SET c.name = %s, c.status = %s, c.composer_mode = %s, c.audience_key = %s, c.subject_line = %s, c.preheader_text = %s, c.from_name = %s, c.from_email = %s, c.reply_to_email = %s, c.updated_at = %s, s.is_current = CASE WHEN s.id = %d THEN 1 ELSE 0 END, s.updated_at = %s WHERE c.id = %d',
                    $t['campaigns'], $t['content_snapshots'], $campaign_fields['name'], $campaign_fields['status'], $campaign_fields['composer_mode'], $campaign_fields['audience_key'], $campaign_fields['subject_line'], $campaign_fields['preheader_text'], $campaign_fields['from_name'], $campaign_fields['from_email'], $campaign_fields['reply_to_email'], $now, $snapshot_id, $now, $campaign_id
                ));
            }
            if ($updated === false || $updated < 1) {
                return ['error' => 'Could not make the new content current. Your previous saved content is unchanged.'];
            }

            return [
                'snapshot_id' => $snapshot_id,
                'snapshot_key' => $snapshot_key,
                'content_hash' => $hash,
                'rendered_html_length' => strlen($rendered),
            ];
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_template(array $data): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $template_key = sanitize_key((string) ($data['template_key'] ?? 'template_' . strtolower(wp_generate_password(10, false, false))));
            $design = (array) ($data['design'] ?? []);
            $raw_html = (string) ($data['raw_html'] ?? '');
            $mode = sanitize_key((string) ($data['composer_mode'] ?? 'block'));
            $rendered = (string) ($data['rendered_html'] ?? '');
            if ($rendered === '') {
                $rendered = $mode === 'block' ? APCampaign_Renderer::render_from_design($design) : $raw_html;
            }

            $inserted = $wpdb->insert($t['templates'], [
                'template_key' => $template_key,
                'name' => sanitize_text_field((string) ($data['name'] ?? 'Campaign Template')),
                'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
                'composer_mode' => $mode,
                'design_json' => wp_json_encode($design),
                'raw_html' => $raw_html,
                'rendered_html' => $rendered,
                'category' => sanitize_key((string) ($data['category'] ?? '')),
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'created_by_user_id' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === false || (int) $wpdb->insert_id < 1) return ['error' => 'Could not save template.'];
            return ['template_id' => (int) $wpdb->insert_id, 'template_key' => $template_key];
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_contact_list(array $data): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $list_key = sanitize_key((string) ($data['list_key'] ?? 'list_' . strtolower(wp_generate_password(10, false, false))));
            $wpdb->insert($t['contact_lists'], [
                'list_key' => $list_key,
                'name' => sanitize_text_field((string) ($data['name'] ?? 'Contact List')),
                'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'settings_json' => wp_json_encode((array) ($data['settings'] ?? [])),
                'source_system' => sanitize_key((string) ($data['source_system'] ?? 'apcampaign')),
                'created_by_user_id' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return ['contact_list_id' => (int) $wpdb->insert_id, 'list_key' => $list_key];
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function upsert_contact(int $list_id, array $data): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $email = strtolower(sanitize_email((string) ($data['email'] ?? '')));
            if ($email === '' || !is_email($email)) {
                return ['success' => false, 'error' => 'Invalid email'];
            }
            $hash = hash('sha256', $email);
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM %i WHERE list_id = %d AND email_hash = %s", $t['contacts'], $list_id, $hash));
            $payload = [
                'list_id' => $list_id,
                'email' => $email,
                'email_hash' => $hash,
                'first_name' => sanitize_text_field((string) ($data['first_name'] ?? '')),
                'last_name' => sanitize_text_field((string) ($data['last_name'] ?? '')),
                'company' => sanitize_text_field((string) ($data['company'] ?? '')),
                'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'tags_json' => wp_json_encode((array) ($data['tags'] ?? [])),
                'custom_fields_json' => wp_json_encode((array) ($data['custom_fields'] ?? [])),
                'source_system' => sanitize_key((string) ($data['source_system'] ?? 'manual')),
                'source_ref' => sanitize_text_field((string) ($data['source_ref'] ?? '')),
                'updated_at' => $now,
            ];
            if ($existing) {
                $wpdb->update($t['contacts'], $payload, ['id' => (int) $existing]);
                self::refresh_contact_list_counts($list_id);
                return ['success' => true, 'contact_id' => (int) $existing, 'updated' => true];
            }
            $payload['contact_key'] = 'contact_' . strtolower(wp_generate_password(10, false, false));
            $payload['created_at'] = $now;
            $wpdb->insert($t['contacts'], $payload);
            self::refresh_contact_list_counts($list_id);
            return ['success' => true, 'contact_id' => (int) $wpdb->insert_id, 'updated' => false];
        }

        /**
         * @param array<string,mixed> $criteria
         * @return array<string,mixed>
         */
        public static function create_segment_from_list(int $source_list_id, string $name, array $criteria): array
        {
            if (!class_exists('APCampaign_Pro_Segments')) return ['success' => false, 'error' => 'Advanced segmentation is unavailable.'];
            return APCampaign_Pro_Segments::create_segment_from_list($source_list_id, $name, $criteria);
        }

        /**
         * @return array<string,mixed>
         */
        public static function create_audience_snapshot_from_list(int $campaign_id, int $list_id, array $criteria = []): array
        {
            return self::create_audience_snapshot($campaign_id, 'list_' . $list_id, self::contacts_for_criteria($list_id, $criteria), array_merge($criteria, [
                'source_system' => 'apcampaign_list',
                'source_ref' => (string) $list_id,
            ]));
        }

        /**
         * @param array<int,array<string,mixed>> $recipients
         * @return array<string,mixed>
         */
        public static function create_audience_snapshot(int $campaign_id, string $audience_key, array $recipients, array $criteria = []): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $normalized = [];
            foreach ($recipients as $recipient) {
                if (!is_array($recipient) || empty($recipient['email']) || !is_email((string) $recipient['email'])) {
                    continue;
                }
                $normalized[] = [
                    'email' => sanitize_email((string) $recipient['email']),
                    'contact_ref' => sanitize_text_field((string) ($recipient['contact_ref'] ?? '')),
                    'merge' => (array) ($recipient['merge'] ?? []),
                ];
            }
            $hash = hash('sha256', wp_json_encode($normalized));
            $wpdb->insert($t['audience_snapshots'], [
                'campaign_id' => $campaign_id,
                'audience_key' => sanitize_key($audience_key),
                'source_system' => sanitize_key((string) ($criteria['source_system'] ?? 'manual')),
                'source_ref' => sanitize_text_field((string) ($criteria['source_ref'] ?? '')),
                'recipient_count' => count($normalized),
                'criteria_json' => wp_json_encode($criteria),
                'recipients_json' => wp_json_encode($normalized),
                'snapshot_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['audience_snapshot_id' => (int) $wpdb->insert_id, 'recipient_count' => count($normalized), 'snapshot_hash' => $hash];
        }

        /**
         * @return array<string,mixed>
         */
        public static function run_intelligence(int $campaign_id, int $snapshot_id): array
        {
            if (!class_exists('APCampaign_Intelligence')) { return ['success' => false, 'error' => 'Campaign intelligence is unavailable.']; }
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['campaigns'], $campaign_id), ARRAY_A);
            $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['content_snapshots'], $snapshot_id), ARRAY_A);
            if (!$campaign || !$snapshot) {
                return ['success' => false, 'error' => 'Campaign or snapshot not found'];
            }

            $analysis = APCampaign_Intelligence::score_campaign([
                'subject_line' => $campaign['subject_line'] ?? '',
                'preheader_text' => $campaign['preheader_text'] ?? '',
                'audience_key' => $campaign['audience_key'] ?? '',
                'rendered_html' => $snapshot['rendered_html'] ?? '',
            ]);
            $now = current_time('mysql');
            $report_key = 'intel_' . strtolower(wp_generate_password(10, false, false));
            $wpdb->insert($t['intelligence_reports'], [
                'campaign_id' => $campaign_id,
                'snapshot_id' => $snapshot_id,
                'report_key' => $report_key,
                'mode' => sanitize_key((string) $analysis['mode']),
                'overall_score' => (string) $analysis['overall_score'],
                'subject_score' => (string) $analysis['subject_score'],
                'content_score' => (string) $analysis['content_score'],
                'cta_score' => (string) $analysis['cta_score'],
                'predicted_open_rate' => (float) $analysis['predicted_open_rate'],
                'predicted_click_rate' => (float) $analysis['predicted_click_rate'],
                'confidence' => (string) $analysis['confidence'],
                'data_sources_json' => wp_json_encode($analysis['data_sources_used']),
                'findings_json' => wp_json_encode($analysis['findings']),
                'suggestions_json' => wp_json_encode($analysis['suggestions']),
                'raw_response_json' => wp_json_encode($analysis),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['success' => true, 'intelligence_report_id' => (int) $wpdb->insert_id, 'analysis' => $analysis];
        }

        /**
         * @return array<string,mixed>
         */
        public static function run_preflight(int $campaign_id, int $snapshot_id): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['campaigns'], $campaign_id), ARRAY_A);
            $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['content_snapshots'], $snapshot_id), ARRAY_A);
            if (!$campaign || !$snapshot) {
                return ['success' => false, 'error' => 'Campaign or snapshot not found'];
            }
            $preflight = APCampaign_Preflight::run(array_merge($campaign, ['rendered_html' => (string) ($snapshot['rendered_html'] ?? '')]));
            $now = current_time('mysql');
            $report_key = 'preflight_' . strtolower(wp_generate_password(10, false, false));
            $wpdb->insert($t['preflight_reports'], [
                'campaign_id' => $campaign_id,
                'snapshot_id' => $snapshot_id,
                'report_key' => $report_key,
                'status' => (string) $preflight['status'],
                'checked_count' => (int) $preflight['checked'],
                'failure_count' => count((array) $preflight['failures']),
                'warning_count' => count((array) $preflight['warnings']),
                'failures_json' => wp_json_encode($preflight['failures']),
                'warnings_json' => wp_json_encode($preflight['warnings']),
                'checks_json' => wp_json_encode($preflight),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $preflight['preflight_report_id'] = (int) $wpdb->insert_id;
            return $preflight;
        }

        /**
         * @return array<string,mixed>
         */
        public static function queue_send_job(int $campaign_id, int $snapshot_id, int $audience_snapshot_id, array $settings = []): array
        {
            if ($campaign_id < 1) return ['success' => false, 'error' => 'Invalid campaign.'];
            if (array_key_exists('scheduled_timestamp', $settings) && (!is_int($settings['scheduled_timestamp']) || $settings['scheduled_timestamp'] <= time())) {
                return ['success' => false, 'error' => 'Invalid scheduled time.'];
            }
            global $wpdb;
            // Serialize the check and creation across separate PHP workers.
            $lock = 'apcampaign_queue_' . substr(hash('sha256', $wpdb->prefix . ':' . $campaign_id), 0, 42);
            if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') {
                return ['success' => false, 'error' => 'This campaign is already being queued. Please wait.'];
            }
            try {
                return self::queue_send_job_locked($campaign_id, $snapshot_id, $audience_snapshot_id, $settings);
            } finally {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            }
        }

        private static function queue_send_job_locked(int $campaign_id, int $snapshot_id, int $audience_snapshot_id, array $settings): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            // Prevent an additional send while an earlier one is active or uncertain.
            $blocking = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i j LEFT JOIN %i r ON r.send_job_id = j.id AND r.status = 'sending' WHERE j.campaign_id = %d AND (j.status IN ('preparing', 'queued', 'scheduled', 'sending', 'review_required') OR r.id IS NOT NULL)", $t['send_jobs'], $t['send_job_recipients'], $campaign_id));
            if ($blocking === null || (int) $blocking > 0) {
                return ['success' => false, 'error' => 'This campaign has a send in progress or awaiting review. Check the dashboard before sending again.'];
            }
            $audience = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['audience_snapshots'], $audience_snapshot_id), ARRAY_A);
            if (!$audience) {
                return ['success' => false, 'error' => 'Audience snapshot not found'];
            }
            $recipients = json_decode((string) ($audience['recipients_json'] ?? '[]'), true);
            if (!is_array($recipients) || empty($recipients)) {
                return ['success' => false, 'error' => 'Audience has no recipients'];
            }

            $preflight = self::run_preflight($campaign_id, $snapshot_id);
            if (empty($preflight['success'])) {
                return ['success' => false, 'error' => 'Preflight failed', 'preflight' => $preflight];
            }

            $now = current_time('mysql');
            $job_key = 'send_' . strtolower(wp_generate_password(10, false, false));
            $inserted_job = $wpdb->insert($t['send_jobs'], [
                'campaign_id' => $campaign_id,
                'job_key' => $job_key,
                'snapshot_id' => $snapshot_id,
                'audience_snapshot_id' => $audience_snapshot_id,
                'status' => 'preparing',
                'transport' => function_exists('apmailer_component') ? 'apmailer' : 'wordpress',
                'total_recipients' => count($recipients),
                'queued_count' => count($recipients),
                'settings_json' => wp_json_encode($settings),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $job_id = (int) $wpdb->insert_id;
            if (!$inserted_job || $job_id < 1) {
                return ['success' => false, 'error' => 'The send job could not be created.'];
            }

            $queued = 0;
            $queue_failed = false;
            foreach ($recipients as $recipient) {
                if (!is_array($recipient) || empty($recipient['email'])) {
                    continue;
                }
                $email = sanitize_email((string) $recipient['email']);
                if ($email === '') continue;
                $suppressed = self::is_suppressed($email);
                if ($suppressed === null) {
                    $wpdb->update($t['send_jobs'], ['status' => 'failed', 'queued_count' => 0, 'error_message' => 'Could not verify recipient suppression.', 'updated_at' => $now], ['id' => $job_id, 'status' => 'preparing']);
                    return ['success' => false, 'error' => 'Could not verify recipient suppression. The campaign was not queued.'];
                }
                if ($suppressed) continue;
                $recipient_key = 'recipient_' . hash('sha256', $job_id . '|' . $email);
                $inserted = $wpdb->insert($t['send_job_recipients'], [
                    'send_job_id' => $job_id,
                    'campaign_id' => $campaign_id,
                    'recipient_key' => $recipient_key,
                    'email' => $email,
                    'contact_ref' => sanitize_text_field((string) ($recipient['contact_ref'] ?? '')),
                    'status' => 'queued',
                    'tracking_token' => bin2hex(random_bytes(32)),
                    'recipient_data_json' => wp_json_encode($recipient),
                    'queued_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($inserted) {
                    $queued++;
                } else {
                    $queue_failed = true;
                }
            }

            if ($queue_failed || $queued === 0) {
                $wpdb->update($t['send_jobs'], ['status' => 'failed', 'queued_count' => 0, 'error_message' => 'No eligible recipients could be queued.', 'updated_at' => $now], ['id' => $job_id]);
                return ['success' => false, 'error' => 'No eligible recipients could be queued.'];
            }
            $scheduled_timestamp = $settings['scheduled_timestamp'] ?? null;
            $promotion = ['status' => $scheduled_timestamp === null ? 'queued' : 'scheduled', 'queued_count' => $queued, 'total_recipients' => $queued];
            if ($scheduled_timestamp !== null) $promotion['scheduled_at'] = wp_date('Y-m-d H:i:s', $scheduled_timestamp, wp_timezone());
            $promoted = $wpdb->update($t['send_jobs'], $promotion, ['id' => $job_id, 'status' => 'preparing']);
            if ($promoted !== 1) return ['success' => false, 'error' => 'The send job could not be finalized. Check the dashboard before retrying.'];

            $wpdb->update($t['campaigns'], ['status' => 'queued', 'queued_at' => $now, 'updated_at' => $now], ['id' => $campaign_id]);
            return ['success' => true, 'send_job_id' => $job_id, 'job_key' => $job_key, 'queued' => $queued, 'preflight' => $preflight];
        }

        /** Stop an abandoned, never-runnable preparation without deleting its records. */
        public static function cancel_abandoned_preparation(int $job_id): array
        {
            if ($job_id < 1) return ['success' => false, 'error' => 'Invalid send job.'];
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $job = $wpdb->get_row($wpdb->prepare('SELECT id, campaign_id FROM %i WHERE id=%d', $t['send_jobs'], $job_id), ARRAY_A);
            if (!$job) return ['success' => false, 'error' => 'The send could not be found.'];
            $lock = 'apcampaign_queue_' . substr(hash('sha256', $wpdb->prefix . ':' . (int) $job['campaign_id']), 0, 42);
            if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') return ['success' => false, 'error' => 'The campaign is still being prepared. Try again later.'];
            try {
                $cutoff = wp_date('Y-m-d H:i:s', time() - 300, wp_timezone());
                $job = $wpdb->get_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND campaign_id=%d AND status='preparing' AND updated_at < %s", $t['send_jobs'], $job_id, (int) $job['campaign_id'], $cutoff), ARRAY_A);
                if (!$job) return ['success' => false, 'error' => 'This preparation is not old enough or is no longer pending.'];
                $unexpected = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE send_job_id=%d AND status <> 'queued'", $t['send_job_recipients'], $job_id));
                if ($wpdb->last_error !== '' || $unexpected === null || (int) $unexpected > 0) return ['success' => false, 'error' => 'This send needs investigation before it can be cancelled.'];
                $saved = $wpdb->update($t['send_jobs'], ['status' => 'failed', 'queued_count' => 0, 'error_message' => 'Incomplete preparation cancelled by an administrator. No queued messages were sent by this job.', 'updated_at' => current_time('mysql')], ['id' => $job_id, 'status' => 'preparing']);
                if ($saved !== 1) return ['success' => false, 'error' => 'The preparation could not be cancelled.'];
                return ['success' => true];
            } finally {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            }
        }

        public static function suppress_email(string $email, string $reason = 'manual', string $source = ''): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $now = current_time('mysql');
            $email = strtolower(sanitize_email($email));
            if (!is_email($email)) return ['suppression_id' => 0, 'error' => 'Invalid email'];
            $hash = hash('sha256', $email);
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM %i WHERE email_hash = %s", $t['suppressions'], $hash));
            $data = [
                'email' => $email,
                'email_hash' => $hash,
                'reason' => sanitize_key($reason),
                'source' => sanitize_key($source),
                'status' => 'active',
                'suppressed_at' => $now,
                'updated_at' => $now,
            ];
            if ($existing) {
                if ($wpdb->update($t['suppressions'], $data, ['id' => (int) $existing]) === false) return ['suppression_id' => 0, 'error' => 'Could not save suppression'];
                return ['suppression_id' => (int) $existing, 'updated' => true];
            }
            $data['created_at'] = $now;
            if ($wpdb->insert($t['suppressions'], $data) === false) return ['suppression_id' => 0, 'error' => 'Could not save suppression'];
            return ['suppression_id' => (int) $wpdb->insert_id, 'updated' => false];
        }

        private static function is_suppressed(string $email): ?bool
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $hash = hash('sha256', strtolower($email));
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM %i WHERE email_hash = %s AND status = 'active' LIMIT 1", $t['suppressions'], $hash));
            return $wpdb->last_error !== '' ? null : (bool) $id;
        }

        /**
         * @param array<string,mixed> $criteria
         * @return array<int,array<string,mixed>>
         */
        private static function contacts_for_criteria(int $list_id, array $criteria = []): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE list_id = %d AND status = 'active'", $t['contacts'], $list_id), ARRAY_A);
            $behavior = sanitize_key((string) ($criteria['behavior'] ?? 'all'));
            $out = [];
            foreach ($rows as $row) {
                if ($behavior === 'opened' && (int) $row['total_opens'] < 1) {
                    continue;
                }
                if ($behavior === 'clicked' && (int) $row['total_clicks'] < 1) {
                    continue;
                }
                if ($behavior === 'not_opened' && (int) $row['total_opens'] > 0) {
                    continue;
                }
                if ($behavior === 'bounced' && (int) $row['total_bounces'] < 1) {
                    continue;
                }
                $out[] = [
                    'email' => (string) $row['email'],
                    'contact_ref' => 'apcampaign_contact:' . (int) $row['id'],
                    'first_name' => (string) $row['first_name'],
                    'last_name' => (string) $row['last_name'],
                    'company' => (string) $row['company'],
                    'merge' => [
                        'first_name' => (string) $row['first_name'],
                        'last_name' => (string) $row['last_name'],
                        'company' => (string) $row['company'],
                    ],
                ];
            }
            return $out;
        }

        private static function refresh_contact_list_counts(int $list_id): void
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE list_id = %d", $t['contacts'], $list_id));
            $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE list_id = %d AND status = 'active'", $t['contacts'], $list_id));
            $wpdb->update($t['contact_lists'], ['contact_count' => $total, 'active_count' => $active, 'updated_at' => current_time('mysql')], ['id' => $list_id]);
        }

        private static function nullable_datetime($value): ?string
        {
            if (!$value) {
                return null;
            }
            $timestamp = strtotime((string) $value);
            return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
        }
    }
}

<?php
/**
 * apCampaign schema installer.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Schema')) {
    final class APCampaign_Schema
    {
        /**
         * @return array<string,string>
         */
        public static function tables(): array
        {
            global $wpdb;
            $prefix = $wpdb->prefix;

            return [
                'campaigns' => $prefix . 'apcampaign_campaigns',
                'content_snapshots' => $prefix . 'apcampaign_content_snapshots',
                'templates' => $prefix . 'apcampaign_templates',
                'contact_lists' => $prefix . 'apcampaign_contact_lists',
                'contacts' => $prefix . 'apcampaign_contacts',
                'contact_events' => $prefix . 'apcampaign_contact_events',
                'segments' => $prefix . 'apcampaign_segments',
                'audience_snapshots' => $prefix . 'apcampaign_audience_snapshots',
                'intelligence_reports' => $prefix . 'apcampaign_intelligence_reports',
                'spam_reports' => $prefix . 'apcampaign_spam_reports',
                'preflight_reports' => $prefix . 'apcampaign_preflight_reports',
                'send_jobs' => $prefix . 'apcampaign_send_jobs',
                'send_job_recipients' => $prefix . 'apcampaign_send_job_recipients',
                'tracking_events' => $prefix . 'apcampaign_tracking_events',
                'suppressions' => $prefix . 'apcampaign_suppressions',
                'webhook_events' => $prefix . 'apcampaign_webhook_events',
                'history_imports' => $prefix . 'apcampaign_history_imports',
                'history_metrics' => $prefix . 'apcampaign_history_metrics',
                'settings_profiles' => $prefix . 'apcampaign_settings_profiles',
            ];
        }

        public static function install(): void
        {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            $t = self::tables();
            $sql = [];

            $sql[] = "CREATE TABLE {$t['campaigns']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'draft',
                composer_mode varchar(32) NOT NULL DEFAULT 'block',
                audience_key varchar(100) NOT NULL DEFAULT '',
                subject_line varchar(255) NOT NULL DEFAULT '',
                preheader_text varchar(255) NOT NULL DEFAULT '',
                from_name varchar(190) NOT NULL DEFAULT '',
                from_email varchar(190) NOT NULL DEFAULT '',
                reply_to_email varchar(190) NOT NULL DEFAULT '',
                sender_identity_ref varchar(190) NOT NULL DEFAULT '',
                settings_json longtext NULL,
                source_plugin varchar(64) NOT NULL DEFAULT '',
                source_record_id varchar(100) NOT NULL DEFAULT '',
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                scheduled_at datetime NULL,
                queued_at datetime NULL,
                sent_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY campaign_key (campaign_key),
                KEY status (status),
                KEY composer_mode (composer_mode),
                KEY audience_key (audience_key),
                KEY scheduled_at (scheduled_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['content_snapshots']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                snapshot_key varchar(100) NOT NULL,
                snapshot_type varchar(32) NOT NULL DEFAULT 'draft',
                composer_mode varchar(32) NOT NULL DEFAULT 'block',
                design_json longtext NULL,
                raw_html longtext NULL,
                rendered_html longtext NULL,
                content_hash varchar(64) NOT NULL DEFAULT '',
                renderer_version varchar(32) NOT NULL DEFAULT '',
                is_current tinyint unsigned NOT NULL DEFAULT 1,
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY snapshot_key (snapshot_key),
                KEY campaign_id (campaign_id),
                KEY is_current (is_current),
                KEY content_hash (content_hash)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['templates']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                template_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                description text NULL,
                composer_mode varchar(32) NOT NULL DEFAULT 'block',
                design_json longtext NULL,
                raw_html longtext NULL,
                rendered_html longtext NULL,
                category varchar(100) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'active',
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY template_key (template_key),
                KEY category (category),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['contact_lists']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                list_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                description text NULL,
                status varchar(32) NOT NULL DEFAULT 'active',
                contact_count int unsigned NOT NULL DEFAULT 0,
                active_count int unsigned NOT NULL DEFAULT 0,
                deliverability_score int unsigned NOT NULL DEFAULT 0,
                settings_json longtext NULL,
                source_system varchar(64) NOT NULL DEFAULT 'apcampaign',
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY list_key (list_key),
                KEY status (status),
                KEY source_system (source_system)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['contacts']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                list_id bigint unsigned NOT NULL,
                contact_key varchar(100) NOT NULL,
                email varchar(190) NOT NULL,
                email_hash varchar(64) NOT NULL,
                first_name varchar(100) NOT NULL DEFAULT '',
                last_name varchar(100) NOT NULL DEFAULT '',
                company varchar(190) NOT NULL DEFAULT '',
                phone varchar(64) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'active',
                tags_json longtext NULL,
                custom_fields_json longtext NULL,
                engagement_score int unsigned NOT NULL DEFAULT 0,
                engagement_tier varchar(32) NOT NULL DEFAULT 'unknown',
                total_sent int unsigned NOT NULL DEFAULT 0,
                total_opens int unsigned NOT NULL DEFAULT 0,
                total_clicks int unsigned NOT NULL DEFAULT 0,
                total_bounces int unsigned NOT NULL DEFAULT 0,
                last_sent_at datetime NULL,
                last_opened_at datetime NULL,
                last_clicked_at datetime NULL,
                unsubscribed_at datetime NULL,
                source_system varchar(64) NOT NULL DEFAULT 'manual',
                source_ref varchar(190) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY list_email (list_id, email_hash),
                UNIQUE KEY contact_key (contact_key),
                KEY list_id (list_id),
                KEY email (email),
                KEY status (status),
                KEY engagement_tier (engagement_tier)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['contact_events']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                contact_id bigint unsigned NOT NULL DEFAULT 0,
                list_id bigint unsigned NOT NULL DEFAULT 0,
                campaign_id bigint unsigned NOT NULL DEFAULT 0,
                send_job_id bigint unsigned NOT NULL DEFAULT 0,
                email varchar(190) NOT NULL DEFAULT '',
                event_type varchar(32) NOT NULL,
                event_data_json longtext NULL,
                occurred_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY contact_id (contact_id),
                KEY list_id (list_id),
                KEY campaign_id (campaign_id),
                KEY email (email),
                KEY event_type (event_type)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['segments']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                segment_key varchar(100) NOT NULL,
                source_list_id bigint unsigned NOT NULL DEFAULT 0,
                name varchar(190) NOT NULL,
                criteria_json longtext NULL,
                status varchar(32) NOT NULL DEFAULT 'active',
                generated_list_id bigint unsigned NOT NULL DEFAULT 0,
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY segment_key (segment_key),
                KEY source_list_id (source_list_id),
                KEY generated_list_id (generated_list_id),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['audience_snapshots']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                audience_key varchar(100) NOT NULL,
                source_system varchar(64) NOT NULL DEFAULT 'manual',
                source_ref varchar(190) NOT NULL DEFAULT '',
                recipient_count int unsigned NOT NULL DEFAULT 0,
                criteria_json longtext NULL,
                recipients_json longtext NULL,
                snapshot_hash varchar(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY campaign_id (campaign_id),
                KEY audience_key (audience_key),
                KEY source_system (source_system),
                KEY snapshot_hash (snapshot_hash)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['intelligence_reports']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                snapshot_id bigint unsigned NOT NULL DEFAULT 0,
                report_key varchar(100) NOT NULL,
                mode varchar(64) NOT NULL DEFAULT 'campaign_intelligence',
                overall_score varchar(8) NOT NULL DEFAULT '',
                subject_score varchar(8) NOT NULL DEFAULT '',
                content_score varchar(8) NOT NULL DEFAULT '',
                cta_score varchar(8) NOT NULL DEFAULT '',
                predicted_open_rate decimal(6,2) NOT NULL DEFAULT 0,
                predicted_click_rate decimal(6,2) NOT NULL DEFAULT 0,
                confidence varchar(32) NOT NULL DEFAULT 'medium',
                data_sources_json longtext NULL,
                findings_json longtext NULL,
                suggestions_json longtext NULL,
                raw_response_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY report_key (report_key),
                KEY campaign_id (campaign_id),
                KEY snapshot_id (snapshot_id),
                KEY mode (mode)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['spam_reports']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                snapshot_id bigint unsigned NOT NULL DEFAULT 0,
                report_key varchar(100) NOT NULL,
                score int unsigned NOT NULL DEFAULT 100,
                grade varchar(8) NOT NULL DEFAULT 'A',
                pass_count int unsigned NOT NULL DEFAULT 0,
                issue_count int unsigned NOT NULL DEFAULT 0,
                issues_json longtext NULL,
                passes_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY report_key (report_key),
                KEY campaign_id (campaign_id),
                KEY snapshot_id (snapshot_id),
                KEY score (score)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['preflight_reports']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                snapshot_id bigint unsigned NOT NULL DEFAULT 0,
                report_key varchar(100) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'passed',
                checked_count int unsigned NOT NULL DEFAULT 0,
                failure_count int unsigned NOT NULL DEFAULT 0,
                warning_count int unsigned NOT NULL DEFAULT 0,
                failures_json longtext NULL,
                warnings_json longtext NULL,
                checks_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY report_key (report_key),
                KEY campaign_id (campaign_id),
                KEY snapshot_id (snapshot_id),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['send_jobs']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                job_key varchar(100) NOT NULL,
                snapshot_id bigint unsigned NOT NULL DEFAULT 0,
                audience_snapshot_id bigint unsigned NOT NULL DEFAULT 0,
                status varchar(32) NOT NULL DEFAULT 'queued',
                transport varchar(64) NOT NULL DEFAULT 'wordpress',
                sender_identity_ref varchar(190) NOT NULL DEFAULT '',
                total_recipients int unsigned NOT NULL DEFAULT 0,
                queued_count int unsigned NOT NULL DEFAULT 0,
                sent_count int unsigned NOT NULL DEFAULT 0,
                failed_count int unsigned NOT NULL DEFAULT 0,
                skipped_count int unsigned NOT NULL DEFAULT 0,
                scheduled_at datetime NULL,
                started_at datetime NULL,
                completed_at datetime NULL,
                error_message text NULL,
                settings_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_key (job_key),
                KEY campaign_id (campaign_id),
                KEY status (status),
                KEY scheduled_at (scheduled_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['send_job_recipients']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                send_job_id bigint unsigned NOT NULL,
                campaign_id bigint unsigned NOT NULL,
                recipient_key varchar(100) NOT NULL,
                email varchar(190) NOT NULL,
                contact_ref varchar(190) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'queued',
                tracking_token varchar(100) NOT NULL DEFAULT '',
                recipient_data_json longtext NULL,
                provider_message_id varchar(190) NOT NULL DEFAULT '',
                error_message text NULL,
                queued_at datetime NULL,
                sent_at datetime NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY recipient_key (recipient_key),
                KEY send_job_id (send_job_id),
                KEY campaign_id (campaign_id),
                KEY email (email),
                KEY status (status),
                KEY tracking_token (tracking_token)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['tracking_events']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint unsigned NOT NULL,
                send_job_id bigint unsigned NOT NULL DEFAULT 0,
                recipient_id bigint unsigned NOT NULL DEFAULT 0,
                tracking_token varchar(100) NOT NULL DEFAULT '',
                event_type varchar(32) NOT NULL,
                email varchar(190) NOT NULL DEFAULT '',
                destination_url text NULL,
                event_data_json longtext NULL,
                occurred_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY campaign_id (campaign_id),
                KEY send_job_id (send_job_id),
                KEY recipient_id (recipient_id),
                KEY tracking_token (tracking_token),
                KEY event_type (event_type),
                KEY email (email)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['suppressions']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                email varchar(190) NOT NULL,
                email_hash varchar(64) NOT NULL,
                reason varchar(64) NOT NULL DEFAULT 'manual',
                source varchar(64) NOT NULL DEFAULT '',
                source_ref varchar(190) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'active',
                metadata_json longtext NULL,
                suppressed_at datetime NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email_hash (email_hash),
                KEY email (email),
                KEY reason (reason),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['webhook_events']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                provider varchar(64) NOT NULL,
                event_key varchar(190) NOT NULL,
                event_type varchar(64) NOT NULL,
                email varchar(190) NOT NULL DEFAULT '',
                provider_message_id varchar(190) NOT NULL DEFAULT '',
                payload_json longtext NULL,
                processed_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY provider_event_key (provider, event_key),
                KEY event_type (event_type),
                KEY email (email),
                KEY processed_at (processed_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['history_imports']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                import_key varchar(100) NOT NULL,
                source_system varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'queued',
                record_count int unsigned NOT NULL DEFAULT 0,
                imported_count int unsigned NOT NULL DEFAULT 0,
                error_count int unsigned NOT NULL DEFAULT 0,
                file_ref varchar(255) NOT NULL DEFAULT '',
                notes text NULL,
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY import_key (import_key),
                KEY source_system (source_system),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['history_metrics']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                import_id bigint unsigned NOT NULL DEFAULT 0,
                metric_key varchar(190) NOT NULL,
                source_system varchar(64) NOT NULL DEFAULT '',
                subject_line varchar(255) NOT NULL DEFAULT '',
                audience_key varchar(100) NOT NULL DEFAULT '',
                sent_count int unsigned NOT NULL DEFAULT 0,
                open_rate decimal(6,2) NOT NULL DEFAULT 0,
                click_rate decimal(6,2) NOT NULL DEFAULT 0,
                bounce_rate decimal(6,2) NOT NULL DEFAULT 0,
                complaint_rate decimal(6,2) NOT NULL DEFAULT 0,
                sent_at datetime NULL,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY metric_key (metric_key),
                KEY import_id (import_id),
                KEY audience_key (audience_key),
                KEY sent_at (sent_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['settings_profiles']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                profile_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                scope varchar(64) NOT NULL DEFAULT 'site',
                status varchar(32) NOT NULL DEFAULT 'active',
                settings_json longtext NULL,
                managed_by varchar(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY profile_key (profile_key),
                KEY scope (scope),
                KEY status (status),
                KEY managed_by (managed_by)
            ) $charset;";

            foreach ($sql as $statement) {
                dbDelta($statement);
            }

            update_option('apcampaign_schema_version', APCAMPAIGN_VERSION, false);
        }
    }
}

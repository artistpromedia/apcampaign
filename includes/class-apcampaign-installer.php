<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class APCampaign_Installer
{
    public static function activate(bool $networkWide): void
    {
        if (is_multisite() && $networkWide) {
            $siteIds = get_sites(['fields' => 'ids']);
            foreach ($siteIds as $siteId) {
                switch_to_blog((int) $siteId);
                self::install_for_current_site();
                restore_current_blog();
            }
            return;
        }

        self::install_for_current_site();
    }

    public static function install_for_current_site(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $campaigns = APCampaign_Repository::campaigns_table();
        $lists = APCampaign_Repository::lists_table();
        $templates = APCampaign_Repository::templates_table();
        $logs = APCampaign_Repository::logs_table();

        dbDelta(
            "CREATE TABLE {$campaigns} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                slug varchar(191) NOT NULL,
                status varchar(50) NOT NULL DEFAULT 'draft',
                campaign_type varchar(50) NOT NULL DEFAULT 'email',
                editor_mode varchar(50) NOT NULL DEFAULT 'block',
                subject_line varchar(255) NOT NULL DEFAULT '',
                preview_text varchar(255) NOT NULL DEFAULT '',
                from_name varchar(191) NOT NULL DEFAULT '',
                from_email varchar(191) NOT NULL DEFAULT '',
                reply_to_email varchar(191) NOT NULL DEFAULT '',
                audience_mode varchar(50) NOT NULL DEFAULT 'manual',
                content_html longtext NULL,
                content_json longtext NULL,
                settings_json longtext NULL,
                stats_json longtext NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY slug (slug),
                KEY status (status),
                KEY campaign_type (campaign_type)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$lists} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                list_type varchar(50) NOT NULL DEFAULT 'target_list',
                status varchar(50) NOT NULL DEFAULT 'active',
                criteria_json longtext NULL,
                members_json longtext NULL,
                settings_json longtext NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY list_type (list_type),
                KEY status (status)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$templates} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                template_type varchar(50) NOT NULL DEFAULT 'campaign',
                editor_mode varchar(50) NOT NULL DEFAULT 'block',
                subject_line varchar(255) NOT NULL DEFAULT '',
                preview_text varchar(255) NOT NULL DEFAULT '',
                content_html longtext NULL,
                content_json longtext NULL,
                settings_json longtext NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY template_type (template_type),
                KEY editor_mode (editor_mode)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$logs} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
                event_type varchar(50) NOT NULL DEFAULT 'sent',
                recipient_email varchar(191) NOT NULL DEFAULT '',
                revenue_amount decimal(12,2) NOT NULL DEFAULT 0.00,
                event_meta_json longtext NULL,
                occurred_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY campaign_id (campaign_id),
                KEY event_type (event_type),
                KEY recipient_email (recipient_email)
            ) {$charset};"
        );

        update_option('apcampaign_db_version', APCAMPAIGN_VERSION, false);
    }
}


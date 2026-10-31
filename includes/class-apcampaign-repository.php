<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class APCampaign_Repository
{
    public static function campaigns_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'apcampaign_campaigns';
    }

    public static function lists_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'apcampaign_lists';
    }

    public static function templates_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'apcampaign_templates';
    }

    public static function logs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'apcampaign_logs';
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list_campaigns(): array
    {
        global $wpdb;
        $table = self::campaigns_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY updated_at DESC", $table), ARRAY_A) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_campaign(int $id): ?array
    {
        global $wpdb;
        $table = self::campaigns_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table, $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function save_campaign(array $payload, int $id = 0): int
    {
        global $wpdb;
        $table = self::campaigns_table();
        $now = current_time('mysql');
        $row = [
            'name' => sanitize_text_field((string) ($payload['name'] ?? '')),
            'slug' => sanitize_title((string) ($payload['slug'] ?? $payload['name'] ?? 'campaign')),
            'status' => sanitize_key((string) ($payload['status'] ?? 'draft')),
            'campaign_type' => sanitize_key((string) ($payload['campaign_type'] ?? 'email')),
            'editor_mode' => sanitize_key((string) ($payload['editor_mode'] ?? 'block')),
            'subject_line' => sanitize_text_field((string) ($payload['subject_line'] ?? '')),
            'preview_text' => sanitize_text_field((string) ($payload['preview_text'] ?? '')),
            'from_name' => sanitize_text_field((string) ($payload['from_name'] ?? '')),
            'from_email' => sanitize_email((string) ($payload['from_email'] ?? '')),
            'reply_to_email' => sanitize_email((string) ($payload['reply_to_email'] ?? '')),
            'audience_mode' => sanitize_key((string) ($payload['audience_mode'] ?? 'manual')),
            'content_html' => (string) ($payload['content_html'] ?? ''),
            'content_json' => wp_json_encode($payload['content_json'] ?? []) ?: '{}',
            'settings_json' => wp_json_encode($payload['settings'] ?? []) ?: '{}',
            'stats_json' => wp_json_encode($payload['stats'] ?? []) ?: '{}',
            'updated_by' => get_current_user_id(),
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $wpdb->update($table, $row, ['id' => $id]);
            return $id;
        }

        $row['created_by'] = get_current_user_id();
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list_lists(): array
    {
        global $wpdb;
        $table = self::lists_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY updated_at DESC", $table), ARRAY_A) ?: [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function save_list(array $payload, int $id = 0): int
    {
        global $wpdb;
        $table = self::lists_table();
        $now = current_time('mysql');
        $row = [
            'name' => sanitize_text_field((string) ($payload['name'] ?? '')),
            'list_type' => sanitize_key((string) ($payload['list_type'] ?? 'target_list')),
            'status' => sanitize_key((string) ($payload['status'] ?? 'active')),
            'criteria_json' => wp_json_encode($payload['criteria'] ?? []) ?: '{}',
            'members_json' => wp_json_encode($payload['members'] ?? []) ?: '[]',
            'settings_json' => wp_json_encode($payload['settings'] ?? []) ?: '{}',
            'updated_by' => get_current_user_id(),
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $wpdb->update($table, $row, ['id' => $id]);
            return $id;
        }

        $row['created_by'] = get_current_user_id();
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_list(int $id): ?array
    {
        global $wpdb;
        $table = self::lists_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table, $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list_templates(): array
    {
        global $wpdb;
        $table = self::templates_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY updated_at DESC", $table), ARRAY_A) ?: [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function save_template(array $payload, int $id = 0): int
    {
        global $wpdb;
        $table = self::templates_table();
        $now = current_time('mysql');
        $row = [
            'name' => sanitize_text_field((string) ($payload['name'] ?? '')),
            'template_type' => sanitize_key((string) ($payload['template_type'] ?? 'campaign')),
            'editor_mode' => sanitize_key((string) ($payload['editor_mode'] ?? 'block')),
            'subject_line' => sanitize_text_field((string) ($payload['subject_line'] ?? '')),
            'preview_text' => sanitize_text_field((string) ($payload['preview_text'] ?? '')),
            'content_html' => (string) ($payload['content_html'] ?? ''),
            'content_json' => wp_json_encode($payload['content_json'] ?? []) ?: '{}',
            'settings_json' => wp_json_encode($payload['settings'] ?? []) ?: '{}',
            'updated_by' => get_current_user_id(),
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $wpdb->update($table, $row, ['id' => $id]);
            return $id;
        }

        $row['created_by'] = get_current_user_id();
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_template(int $id): ?array
    {
        global $wpdb;
        $table = self::templates_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table, $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function add_log(array $payload): int
    {
        global $wpdb;
        $table = self::logs_table();
        $row = [
            'campaign_id' => absint($payload['campaign_id'] ?? 0),
            'event_type' => sanitize_key((string) ($payload['event_type'] ?? 'sent')),
            'recipient_email' => sanitize_email((string) ($payload['recipient_email'] ?? '')),
            'revenue_amount' => number_format((float) ($payload['revenue_amount'] ?? 0), 2, '.', ''),
            'event_meta_json' => wp_json_encode($payload['meta'] ?? []) ?: '{}',
            'occurred_at' => sanitize_text_field((string) ($payload['occurred_at'] ?? current_time('mysql'))),
            'created_at' => current_time('mysql'),
        ];
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function campaign_logs(int $campaignId): array
    {
        global $wpdb;
        $table = self::logs_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE campaign_id = %d ORDER BY occurred_at DESC", $table, $campaignId), ARRAY_A) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list_logs(int $limit = 100): array
    {
        global $wpdb;
        $table = self::logs_table();
        $limit = max(1, $limit);
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY occurred_at DESC, id DESC LIMIT %d", $table, $limit), ARRAY_A) ?: [];
    }
}


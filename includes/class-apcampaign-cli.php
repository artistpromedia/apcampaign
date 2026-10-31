<?php
/**
 * apCampaign WP-CLI commands.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_CLI')) {
    final class APCampaign_CLI
    {
        public function install(): void
        {
            APCampaign_Schema::install();
            WP_CLI::success('apCampaign schema installed.');
        }

        public function contract(): void
        {
            WP_CLI::print_value([
                'component' => APCampaign_Contract::component(),
                'domain' => APCampaign_Contract::domain(),
            ], ['format' => 'json']);
        }

        public function counts(): void
        {
            WP_CLI::print_value(APCampaign_Lifecycle::counts(), ['format' => 'json']);
        }

        public function smoke_lifecycle(): void
        {
            APCampaign_Schema::install();
            $run_key = strtolower(wp_generate_password(8, false, false));

            $campaign = APCampaign_Lifecycle::create_campaign([
                'campaign_key' => 'campaign_' . $run_key,
                'name' => 'Campaign Intelligence Smoke',
                'composer_mode' => 'block',
                'audience_key' => 'publisher_readers',
                'subject_line' => 'New guide for smarter publisher campaigns',
                'preheader_text' => 'A practical campaign example with a clear CTA.',
                'from_name' => 'Publisher Team',
                'from_email' => 'news@example.test',
            ]);
            $campaign_id = (int) $campaign['campaign_id'];

            $design = [
                'blocks' => [
                    ['id' => 'heading_1', 'type' => 'heading', 'options' => ['text' => 'Smarter Campaigns Start Here']],
                    ['id' => 'text_1', 'type' => 'text', 'options' => ['text' => 'Use campaign intelligence, spam checks, and preflight gates before a send reaches the queue.']],
                    ['id' => 'button_1', 'type' => 'button', 'options' => ['text' => 'Read the Guide', 'url' => 'https://example.test/guide']],
                ],
            ];
            $snapshot = APCampaign_Lifecycle::save_content_snapshot($campaign_id, [
                'snapshot_key' => 'snapshot_' . $run_key,
                'composer_mode' => 'block',
                'design' => $design,
            ]);
            if (!empty($snapshot['error']) || empty($snapshot['snapshot_id'])) {
                WP_CLI::error($snapshot['error'] ?? 'Could not save campaign content.');
            }
            $snapshot_id = (int) $snapshot['snapshot_id'];

            $template = APCampaign_Lifecycle::create_template([
                'template_key' => 'template_' . $run_key,
                'name' => 'Guide Announcement',
                'composer_mode' => 'block',
                'design' => $design,
                'category' => 'announcement',
            ]);
            if (!empty($template['error']) || empty($template['template_id'])) WP_CLI::error('Could not save template.');
            $list = APCampaign_Lifecycle::create_contact_list([
                'list_key' => 'readers_' . $run_key,
                'name' => 'Smoke Reader List',
                'description' => 'Synthetic smoke-test list. No production data.',
            ]);
            $list_id = (int) $list['contact_list_id'];
            $contact_one = APCampaign_Lifecycle::upsert_contact($list_id, ['email' => 'reader1@example.test', 'first_name' => 'Reader', 'company' => 'Example Publisher']);
            $contact_two = APCampaign_Lifecycle::upsert_contact($list_id, ['email' => 'reader2@example.test', 'first_name' => 'Publisher', 'company' => 'Example Publisher']);
            $segment = APCampaign_Lifecycle::create_segment_from_list($list_id, 'Smoke All Segment', ['behavior' => 'all']);
            $audience = APCampaign_Lifecycle::create_audience_snapshot_from_list($campaign_id, $list_id, ['behavior' => 'all']);
            $intelligence = APCampaign_Lifecycle::run_intelligence($campaign_id, $snapshot_id);
            $preflight = APCampaign_Lifecycle::run_preflight($campaign_id, $snapshot_id);
            $send_job = APCampaign_Lifecycle::queue_send_job($campaign_id, $snapshot_id, (int) $audience['audience_snapshot_id']);
            $suppression = APCampaign_Lifecycle::suppress_email('complaint@example.test', 'complaint', 'smoke');

            WP_CLI::print_value([
                'run_key' => $run_key,
                'campaign' => $campaign,
                'snapshot' => $snapshot,
                'template' => $template,
                'contact_list' => $list,
                'contacts' => [$contact_one, $contact_two],
                'segment' => $segment,
                'audience_snapshot' => $audience,
                'intelligence' => $intelligence,
                'preflight' => $preflight,
                'send_job' => $send_job,
                'suppression' => $suppression,
                'counts' => APCampaign_Lifecycle::counts(),
                'standalone' => true,
                'crm_required' => false,
                'private_artistpro_data_used' => false,
                'block_editor_source_of_truth' => 'design_json',
                'sidebar_controls_rule' => 'controls_edit_selected_block_model_only',
            ], ['format' => 'json']);
        }
    }
}

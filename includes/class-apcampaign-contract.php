<?php
/**
 * apCampaign domain contract.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Contract')) {
    final class APCampaign_Contract
    {
        /**
         * @return array<string,mixed>
         */
        public static function component(): array
        {
            return [
                'key' => 'apcampaign',
                'name' => 'apCampaign',
                'layer' => 'standalone_plugin',
                'status' => 'production_release',
                'version' => APCAMPAIGN_VERSION,
                'responsibility' => 'Shared Communications Engine for the Arts Operations Platform: planning, authoring, preflight, scheduling, queueing, tracking, suppression, and mailer handoff.',
                'stands_alone' => true,
                'depends_on' => [],
                'optional_integrations' => ['apCRM', 'apMailer', 'apAI', 'apForms', 'apPageTurn', 'apPolls', 'apSurveys', 'apSales', 'apSMS', 'apPortal', 'apImage', 'shared_settings_registry'],
                'private_data_rule' => self::private_data_rule(),
                'theme_boundary' => self::theme_boundary_contract(),
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function domain(): array
        {
            return [
                'owns' => [
                    'campaigns',
                    'campaign_content_snapshots',
                    'campaign_templates',
                    'audience_snapshots',
                    'campaign_intelligence_reports',
                    'spam_score_reports',
                    'preflight_reports',
                    'send_jobs',
                    'send_job_recipients',
                    'tracking_events',
                    'suppression_records',
                    'webhook_events',
                    'customer_history_imports',
                    'customer_history_metrics',
                    'settings_profiles',
                ],
                'does_not_own' => [
                    'private ArtistPro/MMR/SBO historical data for public product use',
                    'CRM contact truth',
                    'subscriber truth',
                    'form submission truth',
                    'survey/poll truth',
                    'AI provider credentials',
                    'mail transport provider secrets',
                    'payment truth',
                    'portal authentication',
                    'WordPress theme behavior',
                ],
                'composer_modes' => self::composer_modes(),
                'campaign_lifecycle' => self::campaign_lifecycle(),
                'intelligence_contract' => self::intelligence_contract(),
                'preflight_contract' => self::preflight_contract(),
                'mailer_contract' => self::mailer_contract(),
                'integration_contract' => self::integration_contract(),
                'configuration_contract' => self::configuration_contract(),
                'hard_rules' => self::hard_rules(),
                'platform_invariants' => ['Every application uses the same suppression, preflight, sender-identity, queue, and delivery-evidence rules.', 'Application vocabulary may specialize communications but may not bypass consent or sending safeguards.'],
                'table_plan' => self::table_plan(),
            ];
        }

        /**
         * @return array<int,string>
         */
        public static function composer_modes(): array
        {
            return ['block', 'raw_html', 'imported_html'];
        }

        /**
         * @return array<int,string>
         */
        public static function campaign_lifecycle(): array
        {
            return [
                'draft',
                'ready_for_review',
                'approved',
                'scheduled',
                'preflight_failed',
                'queued',
                'sending',
                'sent',
                'paused',
                'cancelled',
                'failed',
                'archived',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function intelligence_contract(): array
        {
            return [
                'public_product_name' => 'Campaign Intelligence',
                'first_use_mode' => 'best_practice_and_deliverability_prediction',
                'customer_history_mode' => 'customer_owned_history_prediction',
                'private_benchmark_mode' => 'site_private_only',
                'signals' => [
                    'subject_quality',
                    'preheader_quality',
                    'content_specificity',
                    'cta_clarity',
                    'link_density',
                    'image_text_balance',
                    'audience_fit',
                    'deliverability_risk',
                    'customer_owned_historical_metrics_when_available',
                ],
                'outputs' => [
                    'predicted_open_rate',
                    'predicted_click_rate',
                    'confidence',
                    'subject_score',
                    'content_score',
                    'cta_score',
                    'overall_score',
                    'findings',
                    'suggestions',
                    'data_sources_used',
                ],
                'forbidden_sources' => [
                    'ArtistPro private campaign history in public product',
                    'MMR private campaign history in public product',
                    'SBO private campaign history in public product',
                    'another SaaS tenant private campaign history',
                    'any customer private data without that customer owning/importing it',
                ],
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function private_data_rule(): array
        {
            return [
                'rule' => 'No private ArtistPro, MMR, SBO, or customer tenant campaign data may ship, seed, train, benchmark, demonstrate, or power public apCampaign behavior.',
                'public_product' => 'Uses built-in Campaign Intelligence rules and each customer-owned history only.',
                'saas' => 'Tenant data is isolated. Cross-tenant private benchmarking is forbidden.',
                'internal_pubmotor' => 'May attach a private benchmark provider to this install only.',
                'demo_data' => 'Must be synthetic, public, or explicitly licensed.',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function preflight_contract(): array
        {
            return [
                'checks' => [
                    'renderable_html',
                    'required_subject',
                    'required_sender_identity',
                    'unsubscribe_and_address_footer_available',
                    'image_url_safety',
                    'tracked_link_resolution',
                    'suspicious_url_detection',
                    'shortcode_or_php_error_marker_detection',
                    'spam_score_threshold',
                    'suppression_policy_available',
                ],
                'hard_fail_thresholds' => [
                    'missing_subject',
                    'missing_sender',
                    'unrenderable_html',
                    'tracked_first_party_link_failure',
                    'unsafe_image_url',
                    'spam_score_below_configured_floor',
                ],
                'soft_warning_examples' => [
                    'external_link_403',
                    'external_link_429',
                    'temporary_external_timeout',
                    'low_confidence_prediction',
                ],
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function mailer_contract(): array
        {
            return [
                'preferred_transport' => 'apMailer when present',
                'standalone_transport' => 'WordPress wp_mail fallback with delivery evidence',
                'provider_secrets' => 'Credential refs only. apCampaign does not own provider secrets.',
                'handoff_context' => ['campaign_id', 'send_job_id', 'source_plugin', 'message_class', 'sender_identity_ref'],
            ];
        }

        /**
         * @return array<string,string>
         */
        public static function integration_contract(): array
        {
            return [
                'apCRM' => 'May provide contacts, account timelines, preferences, and shared settings; apCampaign stores send snapshots, not CRM truth.',
                'apMailer' => 'Owns delivery provider routing, credentials, fallbacks, and transport evidence when present.',
                'apAI' => 'May provide AI provider routing; apCampaign remains usable with no AI configured.',
                'apForms' => 'May receive landing-page/form campaign conversion context.',
                'apPageTurn' => 'May hand off issue announcement HTML to a campaign draft.',
                'apPolls' => 'May hand off poll invitations or result announcement campaigns.',
                'apSurveys' => 'May hand off survey invitation campaigns.',
                'apSales' => 'May hand off advertiser campaign activity evidence.',
                'apSMS' => 'May receive campaign failure alerts later.',
                'apPortal' => 'May expose advertiser/client campaign proofing and approvals later.',
                'apImage' => 'May provide managed campaign image URLs and transformation metadata.',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function configuration_contract(): array
        {
            return [
                'resolution_order' => ['shared_pubmotor_or_apcrm_settings', 'apcampaign_local_settings', 'safe_defaults'],
                'managed_settings_behavior' => [
                    'If shared suite settings own AI or mail profiles, local UI should show those fields as managed.',
                    'Standalone apCampaign configures local defaults for campaign intelligence, preflight, tracking, and mail fallback.',
                ],
                'default_spam_floor' => 70,
                'default_link_check_limit' => 25,
                'history_policy' => 'customer_owned_history_only',
            ];
        }

        /**
         * @return array<int,string>
         */
        public static function hard_rules(): array
        {
            return [
                'apCampaign must work on plain WordPress without apCRM, apMailer, apAI, or PubMotor.',
                'Campaign creative truth is the content snapshot, not a browser-normalized DOM fragment.',
                'Block editor campaigns use structured design_json as source of truth; rendered HTML is a cache/snapshot.',
                'Raw HTML campaigns preserve supplied HTML while still passing through compliance, tracking, and preflight contracts.',
                'Spam score, AI prediction, and preflight must be shared services used by both block and raw composers.',
                'Pre-send gates must not fail open silently.',
                'No ArtistPro/MMR/SBO private campaign data may be used by public product or SaaS tenants.',
                'Customer history intelligence may use only that customer-owned history or imports.',
                'Transport credentials belong to apMailer/shared secret systems, not apCampaign records.',
                'Themes may style screens, but must not own campaign state, preflight, queueing, tracking, suppression, or intelligence behavior.',
            ];
        }

        /**
         * @return array<string,string>
         */
        public static function table_plan(): array
        {
            return [
                'campaigns' => 'apcampaign_campaigns',
                'content_snapshots' => 'apcampaign_content_snapshots',
                'templates' => 'apcampaign_templates',
                'audience_snapshots' => 'apcampaign_audience_snapshots',
                'intelligence_reports' => 'apcampaign_intelligence_reports',
                'spam_reports' => 'apcampaign_spam_reports',
                'preflight_reports' => 'apcampaign_preflight_reports',
                'send_jobs' => 'apcampaign_send_jobs',
                'send_job_recipients' => 'apcampaign_send_job_recipients',
                'tracking_events' => 'apcampaign_tracking_events',
                'suppressions' => 'apcampaign_suppressions',
                'webhook_events' => 'apcampaign_webhook_events',
                'history_imports' => 'apcampaign_history_imports',
                'history_metrics' => 'apcampaign_history_metrics',
                'settings_profiles' => 'apcampaign_settings_profiles',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function theme_boundary_contract(): array
        {
            return [
                'themes_are_presentation_only' => true,
                'plugin_owned_surfaces' => ['data', 'schema', 'routes', 'permissions', 'workflow_state', 'business_rules', 'intelligence', 'preflight', 'queueing', 'tracking'],
                'theme_owned_surfaces' => ['visual_rendering', 'CSS', 'template_slot_markup'],
                'hard_rules' => [
                    'No send, spam, preflight, queue, tracking, unsubscribe, suppression, or intelligence logic may live in a WordPress theme.',
                    'Theme failures may make a screen ugly; they must not make campaign state wrong.',
                ],
            ];
        }
    }
}

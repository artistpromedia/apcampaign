<?php
/**
 * apCampaign runtime hooks.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Plugin')) {
    final class APCampaign_Plugin
    {
        private static ?self $instance = null;

        public static function instance(): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function boot(): void
        {
            if (class_exists('APCampaign_App')) {
                APCampaign_App::boot();
            }
            add_action('wp_ajax_apcampaign_spam_score', [$this, 'ajax_spam_score']);
            if (class_exists('APCampaign_Intelligence')) { add_action('wp_ajax_apcampaign_intelligence', [$this, 'ajax_intelligence']); }
            add_action('wp_ajax_apcampaign_render_block_design', [$this, 'ajax_render_block_design']);
            if ((defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE)) {
                add_action('wp_ajax_nopriv_apcampaign_spam_score', [$this, 'ajax_spam_score']);
                if (class_exists('APCampaign_Intelligence')) { add_action('wp_ajax_nopriv_apcampaign_intelligence', [$this, 'ajax_intelligence']); }
                add_action('wp_ajax_nopriv_apcampaign_render_block_design', [$this, 'ajax_render_block_design']);
            }
            if (class_exists('APCampaign_Admin')) {
                APCampaign_Admin::boot();
            }
            APCampaign_Delivery::boot();
        }

        public function ajax_spam_score(): void
        {
            $this->check_ajax_access();
            wp_send_json_success(APCampaign_Spam_Score::score(
                sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
                (string) wp_unslash($_POST['html'] ?? ''),
                sanitize_text_field(wp_unslash($_POST['preheader'] ?? ''))
            ));
        }

        public function ajax_intelligence(): void
        {
            $this->check_ajax_access();
            wp_send_json_success(APCampaign_Intelligence::score_campaign([
                'subject_line' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
                'preheader_text' => sanitize_text_field(wp_unslash($_POST['preheader'] ?? '')),
                'audience_key' => sanitize_key(wp_unslash($_POST['audience_key'] ?? '')),
                'html' => (string) wp_unslash($_POST['html'] ?? ''),
            ]));
        }

        public function ajax_render_block_design(): void
        {
            $this->check_ajax_access();
            $design = json_decode((string) wp_unslash($_POST['design_json'] ?? '{}'), true);
            if (!is_array($design)) {
                wp_send_json_error(['message' => 'Invalid design JSON']);
            }
            wp_send_json_success([
                'html' => APCampaign_Renderer::render_from_design($design),
                'renderer_version' => APCAMPAIGN_VERSION,
            ]);
        }

        private function check_ajax_access(): void
        {
            $demo_access = (defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE);
            if (!$demo_access && !(bool) apply_filters('apcampaign_current_user_can_access', current_user_can('edit_posts'), get_current_user_id())) {
                wp_send_json_error(['message' => 'Permission denied'], 403);
            }
            $raw_nonce = $_POST['nonce'] ?? '';
            if (!is_string($raw_nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($raw_nonce)), 'apcampaign_app')) {
                wp_send_json_error(['message' => 'Security check failed'], 403);
            }
            foreach (['audience_key', 'design_json', 'html', 'preheader', 'subject'] as $key) {
                if (isset($_POST[$key]) && !is_scalar($_POST[$key])) {
                    wp_send_json_error(['message' => 'Invalid request value.'], 400);
                }
            }
        }
    }
}

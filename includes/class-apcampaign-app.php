<?php
/**
 * Standalone apCampaign application surface.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_App')) {
    final class APCampaign_App
    {
        private const QUERY_VAR = 'apcampaign_app';
        private const ACTION_VAR = 'apcampaign_action';
        private const CAMPAIGN_VAR = 'apcampaign_id';
        private const NONCE_ACTION = 'apcampaign_app';

        public static function boot(): void
        {
            add_action('init', [self::class, 'register_routes']);
            add_filter('query_vars', [self::class, 'register_query_vars']);
            add_action('template_redirect', [self::class, 'maybe_render']);
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
            add_action('wp_ajax_apcampaign_app_save_campaign', [self::class, 'ajax_save_campaign']);
            add_action('wp_ajax_apcampaign_app_run_preflight', [self::class, 'ajax_run_preflight']);
            add_action('wp_ajax_apcampaign_app_test_send', [self::class, 'ajax_test_send']);
            add_action('wp_ajax_apcampaign_app_templates', [self::class, 'ajax_templates']);
            add_action('wp_ajax_apcampaign_app_save_template', [self::class, 'ajax_save_template']);
            add_action('wp_ajax_apcampaign_app_delete_template', [self::class, 'ajax_delete_template']);
            add_action('wp_ajax_apcampaign_app_archive', [self::class, 'ajax_archive']);
            add_action('wp_ajax_apcampaign_app_settings', [self::class, 'ajax_settings']);
            add_action('wp_ajax_apcampaign_app_save_settings', [self::class, 'ajax_save_settings']);
            add_action('wp_ajax_apcampaign_app_schedule', [self::class, 'ajax_schedule']);
            add_action('wp_ajax_apcampaign_app_send', [self::class, 'ajax_send']);
            add_action('wp_ajax_apcampaign_review_delivery', [self::class, 'ajax_review_delivery']);
            add_action('wp_ajax_apcampaign_resume_reviewed', [self::class, 'ajax_resume_reviewed']);
            add_action('wp_ajax_apcampaign_cancel_preparation', [self::class, 'ajax_cancel_preparation']);
        }

        public static function register_routes(): void
        {
            add_rewrite_rule('^apcampaign/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=dashboard', 'top');
            add_rewrite_rule('^apcampaign/create/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=create', 'top');
            add_rewrite_rule('^apcampaign/create-simple/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=create-simple', 'top');
            add_rewrite_rule('^apcampaign/create-raw/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=create-raw', 'top');
            add_rewrite_rule('^apcampaign/document-guide/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=document-guide', 'top');
            add_rewrite_rule('^apcampaign/lists/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=lists', 'top');
            add_rewrite_rule('^apcampaign/mailer/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=mailer', 'top');
            add_rewrite_rule('^apcampaign/archived/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=archived', 'top');
            add_rewrite_rule('^apcampaign/edit/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=edit&' . self::CAMPAIGN_VAR . '=$matches[1]', 'top');
            add_rewrite_rule('^apcampaign/view/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::ACTION_VAR . '=view&' . self::CAMPAIGN_VAR . '=$matches[1]', 'top');
        }

        public static function register_query_vars(array $vars): array
        {
            $vars[] = self::QUERY_VAR;
            $vars[] = self::ACTION_VAR;
            $vars[] = self::CAMPAIGN_VAR;
            return $vars;
        }

        public static function url(string $path = ''): string
        {
            $path = trim($path, '/');
            if ((string) get_option('permalink_structure', '') !== '') {
                return home_url('/apcampaign/' . ($path !== '' ? $path . '/' : ''));
            }

            $action = $path !== '' ? $path : 'dashboard';
            $campaign_id = 0;
            if (preg_match('#^(edit|view)/(\d+)$#', $path, $matches)) {
                $action = $matches[1];
                $campaign_id = (int) $matches[2];
            }

            $args = [
                self::QUERY_VAR => '1',
                self::ACTION_VAR => $action,
            ];
            if ($campaign_id > 0) {
                $args[self::CAMPAIGN_VAR] = $campaign_id;
            }
            return add_query_arg($args, home_url('/'));
        }

        public static function enqueue_assets(): void
        {
            if (!self::is_app_request()) {
                return;
            }
            wp_enqueue_media();
            wp_enqueue_style('apcampaign-grapesjs', APCAMPAIGN_PLUGIN_URL . 'assets/vendor/grapesjs/grapes.min.css', [], '0.23.2');
            $app_css_version = APCAMPAIGN_VERSION . '-' . (string) filemtime(APCAMPAIGN_PLUGIN_DIR . 'assets/app.css');
            $app_js_version = APCAMPAIGN_VERSION . '-' . (string) filemtime(APCAMPAIGN_PLUGIN_DIR . 'assets/app.js');
            wp_enqueue_style('apcampaign-app', APCAMPAIGN_PLUGIN_URL . 'assets/app.css', ['apcampaign-grapesjs'], $app_css_version);
            wp_enqueue_script('apcampaign-grapesjs', APCAMPAIGN_PLUGIN_URL . 'assets/vendor/grapesjs/grapes.min.js', [], '0.23.2', true);
            wp_enqueue_script('apcampaign-app', APCAMPAIGN_PLUGIN_URL . 'assets/app.js', ['apcampaign-grapesjs'], $app_js_version, true);
            wp_localize_script('apcampaign-app', 'apCampaignApp', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'mediaEnabled' => self::can_upload(),
                'wordTemplateUrl' => (string) apply_filters(
                    'apcampaign_document_template_url',
                    APCAMPAIGN_PLUGIN_URL . 'assets/templates/apcampaign-email-document-template.docx'
                ),
            ]);
        }

        public static function maybe_render(): void
        {
            if (!self::is_app_request()) {
                return;
            }
            if (!(defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE) && !is_user_logged_in()) {
                auth_redirect();
            }
            if (!self::can_access()) {
                wp_die(esc_html__('You do not have access to apCampaign.', 'apcampaign'), 403);
            }

            $action = sanitize_key((string) get_query_var(self::ACTION_VAR));
            status_header(200);
            nocache_headers();
            echo '<!doctype html><html ';
            language_attributes();
            echo '><head>';
            echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            $app_title = (string) apply_filters('apcampaign_app_title', __('apCampaign', 'apcampaign'));
            echo '<title>' . esc_html($app_title) . '</title>';
            wp_head();
            $body_classes = (array) apply_filters('apcampaign_app_body_classes', ['apcampaign-body', 'apcampaign-theme-dark']);
            $body_classes = array_values(array_filter(array_map('sanitize_html_class', $body_classes)));
            echo '</head><body class="' . esc_attr(implode(' ', $body_classes)) . '">';
            if ((defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE)) {
                echo '<div class="apcampaign-demo-notice" role="status">Live product demo — uploads, saving, testing, scheduling, and sending are disabled.</div>';
            }
            echo '<main class="apcampaign-app" data-apcampaign-app>';
            echo wp_kses_post(apply_filters('apcampaign_app_sidebar_html', self::render_sidebar()));
            echo '<section class="apcampaign-main">';
            echo wp_kses_post(self::render_utility_nav());
            if ($action === 'create') {
                echo wp_kses_post(self::render_create_choice());
            } elseif ($action === 'create-simple') {
                echo wp_kses(self::render_composer(0, 'block'), self::allowed_app_markup());
            } elseif ($action === 'create-raw') {
                echo wp_kses(self::render_composer(0, 'raw_html'), self::allowed_app_markup());
            } elseif ($action === 'document-guide') {
                echo wp_kses_post(self::render_document_guide());
            } elseif ($action === 'edit') {
                echo wp_kses(self::render_composer((int) get_query_var(self::CAMPAIGN_VAR)), self::allowed_app_markup());
            } elseif ($action === 'view') {
                echo wp_kses(self::render_sent_view((int) get_query_var(self::CAMPAIGN_VAR)), self::allowed_app_markup());
            } elseif ($action === 'lists') {
                echo wp_kses_post(self::render_lists_screen());
            } elseif ($action === 'mailer') {
                echo wp_kses_post(self::render_mailer_screen());
            } elseif ($action === 'archived') {
                echo wp_kses_post(self::render_archived_campaigns());
            } else {
                echo wp_kses(self::render_dashboard(), self::allowed_app_markup());
            }
            echo '</section></main>';
            wp_footer();
            echo '</body></html>';
            exit;
        }

        public static function ajax_save_campaign(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            $mode = sanitize_key((string) wp_unslash($_POST['composer_mode'] ?? 'block'));
            $design = json_decode((string) wp_unslash($_POST['design_json'] ?? '{"blocks":[]}'), true);
            if (!is_array($design)) {
                $design = ['blocks' => []];
            }
            $raw_html = (string) wp_unslash($_POST['raw_html'] ?? '');
            $rendered_html = (string) wp_unslash($_POST['rendered_html'] ?? '');
            $subject = sanitize_text_field(wp_unslash($_POST['subject_line'] ?? ''));
            $preheader = sanitize_text_field(wp_unslash($_POST['preheader_text'] ?? ''));
            $from_name = sanitize_text_field(wp_unslash($_POST['from_name'] ?? ''));
            $from_email = sanitize_email(wp_unslash($_POST['from_email'] ?? ''));
            $name = sanitize_text_field(wp_unslash($_POST['name'] ?? 'Untitled Campaign'));
            $audience_key = sanitize_key(wp_unslash($_POST['audience_key'] ?? ''));

            $campaign_fields = null;
            if ($campaign_id < 1) {
                $created = APCampaign_Lifecycle::create_campaign([
                    'name' => $name,
                    'status' => 'draft',
                    'composer_mode' => $mode,
                    'audience_key' => $audience_key,
                    'subject_line' => $subject,
                    'preheader_text' => $preheader,
                    'from_name' => $from_name,
                    'from_email' => $from_email,
                    'reply_to_email' => $from_email,
                ]);
                if (!empty($created['error']) || empty($created['campaign_id'])) wp_send_json_error(['message' => 'Could not create campaign.'], 500);
                $campaign_id = (int) $created['campaign_id'];
            } else {
                if (!self::campaign($campaign_id)) wp_send_json_error(['message' => 'Campaign not found.'], 404);
                $campaign_fields = [
                    'name' => $name,
                    'status' => 'draft',
                    'composer_mode' => $mode,
                    'audience_key' => $audience_key,
                    'subject_line' => $subject,
                    'preheader_text' => $preheader,
                    'from_name' => $from_name,
                    'from_email' => $from_email,
                    'reply_to_email' => $from_email,
                ];
            }

            $snapshot = APCampaign_Lifecycle::save_content_snapshot($campaign_id, [
                'snapshot_type' => 'draft',
                'composer_mode' => $mode,
                'design' => $design,
                'raw_html' => $raw_html,
                'rendered_html' => $rendered_html,
            ], $campaign_fields);
            if (!empty($snapshot['error']) || empty($snapshot['snapshot_id'])) {
                wp_send_json_error(['message' => $snapshot['error'] ?? 'Could not save campaign content.'], 500);
            }
            wp_send_json_success([
                'campaign_id' => $campaign_id,
                'snapshot' => $snapshot,
                'edit_url' => self::url('edit/' . $campaign_id),
            ]);
        }

        public static function ajax_run_preflight(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            $snapshot_id = self::current_snapshot_id($campaign_id);
            if ($campaign_id < 1 || $snapshot_id < 1) {
                wp_send_json_error(['message' => 'Save the campaign before running preflight.'], 400);
            }
            $preflight = APCampaign_Lifecycle::run_preflight($campaign_id, $snapshot_id);
            $intel = class_exists('APCampaign_Intelligence') ? APCampaign_Lifecycle::run_intelligence($campaign_id, $snapshot_id) : null;
            wp_send_json_success(['preflight' => $preflight, 'intelligence' => $intel]);
        }

        public static function ajax_test_send(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            $to = sanitize_email(wp_unslash($_POST['to'] ?? ''));
            if ($campaign_id < 1 || !is_email($to)) {
                wp_send_json_error(['message' => 'Campaign and valid test recipient are required.'], 400);
            }
            $campaign = self::campaign($campaign_id);
            $snapshot = self::current_snapshot($campaign_id);
            if (!$campaign || !$snapshot) {
                wp_send_json_error(['message' => 'Save the campaign before sending a test.'], 400);
            }
            $html = APCampaign_Renderer::wrap_document((string) $snapshot['rendered_html'], (string) $campaign['subject_line']);
            do_action('apcampaign_before_mail');
            try {
            $sent = apply_filters('apcampaign_allow_mail', true) && wp_mail($to, (string) $campaign['subject_line'], $html, [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . (string) $campaign['from_name'] . ' <' . (string) $campaign['from_email'] . '>',
            ]);
            } finally { do_action('apcampaign_after_mail'); }
            $has_apmailer = class_exists('APMailer_Lifecycle');
            $smtp_profile = $has_apmailer ? APMailer_Lifecycle::default_smtp_profile() : null;
            $has_smtp_route = $has_apmailer && is_array($smtp_profile) && APMailer_Lifecycle::smtp_route_ready($smtp_profile);
            wp_send_json_success([
                'sent' => (bool) $sent,
                'delivery_status' => $sent ? 'accepted_by_wordpress' : 'rejected_by_wordpress',
                'transport' => $has_smtp_route ? 'apMailer generic SMTP route' : ($has_apmailer ? 'apMailer WordPress bridge' : 'WordPress mail'),
                'provider_configured' => $has_smtp_route,
                'provider_delivery_confirmed' => false,
            ]);
        }

        public static function ajax_templates(): void
        {
            self::ajax_guard();
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $requested_mode = sanitize_key((string) wp_unslash($_POST['composer_mode'] ?? 'block'));
            $requested_mode = $requested_mode === 'raw_html' ? 'raw_html' : 'block';
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT id, template_key, name, description, composer_mode, design_json, raw_html, category, status, updated_at FROM %i WHERE status = 'active' AND composer_mode = %s ORDER BY updated_at DESC LIMIT 50", $t['templates'],
                    $requested_mode
                ),
                ARRAY_A
            );
            if ($wpdb->last_error !== '' || !is_array($rows)) wp_send_json_error(['message' => 'Could not load saved templates.'], 500);
            $templates = array_map(static function (array $row): array {
                $design = json_decode((string) ($row['design_json'] ?? '{"blocks":[]}'), true);
                if (!is_array($design)) {
                    $design = ['blocks' => []];
                }
                return [
                    'source' => 'template',
                    'id' => (int) $row['id'],
                    'template_key' => (string) $row['template_key'],
                    'name' => (string) $row['name'],
                    'description' => (string) $row['description'],
                    'composer_mode' => (string) $row['composer_mode'],
                    'design' => $design,
                    'raw_html' => (string) ($row['raw_html'] ?? ''),
                    'category' => (string) $row['category'],
                    'updated_at' => (string) $row['updated_at'],
                ];
            }, $rows);

            $sent_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT c.id AS campaign_id, c.name, c.subject_line, c.status, c.composer_mode, c.updated_at, s.design_json, s.raw_html
                FROM %i c
                INNER JOIN %i s ON s.campaign_id = c.id AND s.is_current = 1
                WHERE c.composer_mode = %s AND c.status IN ('sent', 'completed', 'send_complete')
                ORDER BY c.updated_at DESC
                LIMIT 30",
                    $t['campaigns'], $t['content_snapshots'], $requested_mode
                ),
                ARRAY_A
            );
            if ($wpdb->last_error !== '' || !is_array($sent_rows)) wp_send_json_error(['message' => 'Could not load sent designs.'], 500);
            $sent_designs = array_map(static function (array $row) use ($requested_mode): array {
                $design = json_decode((string) ($row['design_json'] ?? '{"blocks":[]}'), true);
                if (!is_array($design)) {
                    $design = ['blocks' => []];
                }
                return [
                    'source' => 'sent_design',
                    'id' => (int) $row['campaign_id'],
                    'template_key' => 'sent_' . (int) $row['campaign_id'],
                    'name' => (string) ($row['name'] ?: $row['subject_line'] ?: __('Sent Campaign', 'apcampaign')),
                    'description' => (string) ($row['subject_line'] ?? ''),
                    'composer_mode' => $requested_mode,
                    'design' => $design,
                    'raw_html' => (string) ($row['raw_html'] ?? ''),
                    'category' => 'sent',
                    'updated_at' => (string) $row['updated_at'],
                    'status' => (string) $row['status'],
                ];
            }, $sent_rows);

            wp_send_json_success(['templates' => $templates, 'sent_designs' => $sent_designs]);
        }

        public static function ajax_save_template(): void
        {
            self::ajax_guard();
            $mode = sanitize_key((string) wp_unslash($_POST['composer_mode'] ?? 'block'));
            $design = json_decode((string) wp_unslash($_POST['design_json'] ?? '{"blocks":[]}'), true);
            if (!is_array($design)) {
                $design = ['blocks' => []];
            }
            $raw_html = (string) wp_unslash($_POST['raw_html'] ?? '');
            $rendered_html = (string) wp_unslash($_POST['rendered_html'] ?? '');
            $name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));
            if ($name === '') {
                $name = sanitize_text_field(wp_unslash($_POST['name'] ?? 'Campaign Template'));
            }

            $template = APCampaign_Lifecycle::create_template([
                'name' => $name,
                'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? 'Saved from apCampaign composer.')),
                'composer_mode' => $mode,
                'design' => $design,
                'raw_html' => $raw_html,
                'rendered_html' => $rendered_html,
                'category' => 'user',
                'status' => 'active',
            ]);
            if (!empty($template['error']) || empty($template['template_id'])) {
                wp_send_json_error(['message' => 'Could not save template.'], 500);
            }
            wp_send_json_success($template);
        }

        public static function ajax_delete_template(): void
        {
            self::ajax_guard();
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $template_key = sanitize_text_field(wp_unslash($_POST['template_key'] ?? ''));
            if ($template_key === '') {
                wp_send_json_error(['message' => 'Template key is required.'], 400);
            }
            $updated = $wpdb->update(
                $t['templates'],
                ['status' => 'deleted', 'updated_at' => current_time('mysql')],
                ['template_key' => $template_key],
                ['%s', '%s'],
                ['%s']
            );
            if ($updated === false) {
                wp_send_json_error(['message' => 'Template could not be deleted.'], 500);
            }
            wp_send_json_success(['deleted' => true, 'template_key' => $template_key]);
        }

        public static function ajax_archive(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            $campaign = self::campaign($campaign_id);
            if (!$campaign) {
                wp_send_json_error(['message' => 'Campaign not found.'], 404);
            }
            if (!self::update_campaign($campaign_id, ['status' => 'archived'])) wp_send_json_error(['message' => 'Could not archive campaign.'], 500);
            wp_send_json_success([
                'archived' => true,
                'campaign_id' => $campaign_id,
                'archive_url' => self::url('archived'),
            ]);
        }

        public static function ajax_settings(): void
        {
            self::ajax_guard();
            wp_send_json_success(['settings' => self::composer_settings()]);
        }

        public static function ajax_save_settings(): void
        {
            self::ajax_guard();
            update_option('apcampaign_default_from_name', sanitize_text_field(wp_unslash($_POST['default_from_name'] ?? '')));
            update_option('apcampaign_default_from_email', sanitize_email(wp_unslash($_POST['default_from_email'] ?? '')));
            update_option('apcampaign_spam_pass_score', max(0, min(100, absint($_POST['spam_pass_score'] ?? 70))));
            update_option('apcampaign_preflight_link_limit', max(1, min(200, absint($_POST['preflight_link_limit'] ?? 25))));
            update_option('apcampaign_retarget_owner_only', !empty($_POST['retarget_owner_only']) && (string) wp_unslash($_POST['retarget_owner_only']) !== '0' ? '1' : '0');
            update_option('apcampaign_physical_address', sanitize_textarea_field(wp_unslash($_POST['physical_address'] ?? '')));
            wp_send_json_success(['settings' => self::composer_settings()]);
        }

        public static function ajax_schedule(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            if ($campaign_id < 1 || !self::current_snapshot($campaign_id)) {
                wp_send_json_error(['message' => 'Save the campaign before scheduling.'], 400);
            }
            $raw_time = sanitize_text_field((string) wp_unslash($_POST['scheduled_at'] ?? ''));
            $scheduled = date_create_immutable($raw_time, wp_timezone());
            if (!$scheduled || $scheduled->getTimestamp() <= time() + 60) {
                wp_send_json_error(['message' => 'Choose a valid future date and time.'], 400);
            }
            $local_time = wp_date('Y-m-d H:i:s', $scheduled->getTimestamp(), wp_timezone());
            $prepared = self::prepare_send_job($campaign_id, $scheduled->getTimestamp());
            if (empty($prepared['success'])) {
                wp_send_json_error(['message' => (string) ($prepared['error'] ?? 'The campaign is not ready to schedule.'), 'details' => $prepared], 400);
            }
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $job_id = (int) $prepared['send_job_id'];
            $updated_campaign = $wpdb->update($t['campaigns'], [
                'status' => 'scheduled',
                'scheduled_at' => $local_time,
                'updated_at' => current_time('mysql'),
            ], ['id' => $campaign_id]);
            if ($updated_campaign !== 1) {
                $wpdb->update($t['send_jobs'], ['status'=>'failed', 'error_message'=>'Campaign schedule could not be saved.', 'updated_at'=>current_time('mysql')], ['id'=>$job_id, 'status'=>'scheduled']);
                wp_send_json_error(['message'=>'Could not save the campaign schedule. Check the dashboard before retrying.'], 500);
            }
            if (!APCampaign_Delivery::schedule($job_id, $scheduled->getTimestamp())) {
                $failed_job = $wpdb->update($t['send_jobs'], ['status'=>'failed', 'error_message'=>'WordPress could not schedule the send.', 'updated_at'=>current_time('mysql')], ['id'=>$job_id, 'status'=>'scheduled']);
                if ($failed_job === 1) $wpdb->update($t['campaigns'], ['status'=>'draft', 'scheduled_at'=>null, 'updated_at'=>current_time('mysql')], ['id'=>$campaign_id, 'status'=>'scheduled', 'scheduled_at'=>$local_time]);
                wp_send_json_error(['message'=>'WordPress could not schedule the send. Check the dashboard before retrying.'], 500);
            }
            wp_send_json_success([
                'scheduled' => true,
                'send_job_id' => (int) $prepared['send_job_id'],
                'scheduled_at' => $local_time,
                'message' => 'Campaign scheduled for ' . $local_time . '.',
            ]);
        }

        public static function ajax_send(): void
        {
            self::ajax_guard();
            $campaign_id = absint($_POST['campaign_id'] ?? 0);
            if ($campaign_id < 1 || !self::current_snapshot($campaign_id)) {
                wp_send_json_error(['message' => 'Save the campaign before sending.'], 400);
            }
            $prepared = self::prepare_send_job($campaign_id);
            if (empty($prepared['success'])) {
                wp_send_json_error(['message' => (string) ($prepared['error'] ?? 'The campaign is not ready to send.'), 'details' => $prepared], 400);
            }
            $result = APCampaign_Delivery::process_job((int) $prepared['send_job_id']);
            if (empty($result['success'])) {
                wp_send_json_error(['message' => (string) ($result['error'] ?? 'The send job failed safely.'), 'details' => $result], 500);
            }
            wp_send_json_success([
                'sent' => true,
                'send_job_id' => (int) $prepared['send_job_id'],
                'result' => $result,
                'message' => !empty($result['complete']) ? 'Campaign send completed.' : 'Campaign send started; remaining recipients are queued.',
            ]);
        }

        public static function ajax_review_delivery(): void
        {
            self::ajax_guard();
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Only a site administrator can review delivery.'], 403);
            }
            $job_id = absint($_POST['job_id'] ?? 0);
            $recipient_id = absint($_POST['recipient_id'] ?? 0);
            $outcome = sanitize_key((string) wp_unslash($_POST['outcome'] ?? ''));
            $result = APCampaign_Delivery::resolve_uncertain($job_id, $recipient_id, $outcome);
            if (empty($result['success'])) {
                wp_send_json_error(['message' => (string) ($result['error'] ?? 'Review could not be saved.')], 400);
            }
            wp_send_json_success(['message' => 'Review saved. The remaining queue will continue.']);
        }

        public static function ajax_resume_reviewed(): void
        {
            self::ajax_guard();
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Only a site administrator can resume a reviewed send.'], 403);
            }
            $result = APCampaign_Delivery::resume_reviewed_job(absint($_POST['job_id'] ?? 0));
            if (empty($result['success'])) {
                wp_send_json_error(['message' => (string) ($result['error'] ?? 'The send could not be resumed.')], 400);
            }
            wp_send_json_success(['message' => 'The remaining queue will continue.']);
        }

        public static function ajax_cancel_preparation(): void
        {
            self::ajax_guard();
            if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Only a site administrator can cancel a stalled preparation.'], 403);
            $result = APCampaign_Lifecycle::cancel_abandoned_preparation(absint($_POST['job_id'] ?? 0));
            if (empty($result['success'])) wp_send_json_error(['message' => (string) ($result['error'] ?? 'The preparation could not be cancelled.')], 400);
            wp_send_json_success(['message' => 'Incomplete preparation cancelled. You can prepare the campaign again.']);
        }

        private static function render_sidebar(): string
        {
            ob_start();
            ?>
            <aside class="apcampaign-sidebar">
                <a class="apcampaign-brand" href="<?php echo esc_url(self::url()); ?>">
                    <strong><?php echo esc_html__('apCampaign', 'apcampaign'); ?></strong>
                    <span><?php echo esc_html__('Campaign Studio', 'apcampaign'); ?></span>
                </a>
                <nav>
                    <a href="<?php echo esc_url(self::url()); ?>"><?php echo esc_html__('Dashboard', 'apcampaign'); ?></a>
                    <a href="<?php echo esc_url(self::url('create-simple')); ?>"><?php echo esc_html__('Drag & Drop', 'apcampaign'); ?></a>
                    <a href="<?php echo esc_url(self::url('create-raw')); ?>"><?php echo esc_html__('Raw HTML', 'apcampaign'); ?></a>
                    <a href="<?php echo esc_url(self::url('lists')); ?>"><?php echo esc_html__('Campaign Lists', 'apcampaign'); ?></a>
                    <a href="<?php echo esc_url(self::url('mailer')); ?>"><?php echo esc_html__('Mailer Settings', 'apcampaign'); ?></a>
                    <a href="<?php echo esc_url(self::url('archived')); ?>"><?php echo esc_html__('Archived Campaigns', 'apcampaign'); ?></a>
                </nav>
            </aside>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_lists_screen(): string
        {
            $lists = self::contact_lists();
            ob_start();
            ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html__('Unlimited native audiences', 'apcampaign'); ?></p>
                    <h1><?php echo esc_html__('Campaign Lists', 'apcampaign'); ?></h1>
                </div>
                <div class="apcampaign-header-actions">
                    <a class="apcampaign-button primary" href="<?php echo esc_url(self::url('create-simple')); ?>"><?php echo esc_html__('Create Campaign', 'apcampaign'); ?></a>
                </div>
            </header>
            <section class="apcampaign-panel">
                <div class="apcampaign-panel-head">
                    <div>
                        <p><?php echo esc_html__('Composer compatibility', 'apcampaign'); ?></p>
                        <h2><?php echo esc_html__('Current selectable lists', 'apcampaign'); ?></h2>
                    </div>
                </div>
                <?php if (!$lists) : ?>
                    <div class="apcampaign-empty"><?php echo esc_html__('No lists yet. Import or add contacts to create your first native apCampaign audience.', 'apcampaign'); ?></div>
                <?php else : ?>
                    <table class="apcampaign-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('List', 'apcampaign'); ?></th>
                                <th><?php echo esc_html__('Active Contacts', 'apcampaign'); ?></th>
                                <th><?php echo esc_html__('Status', 'apcampaign'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lists as $list) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($list['name'] ?? 'Untitled list')); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) ($list['active_count'] ?? 0))); ?></td>
                                    <td><?php echo esc_html((string) ($list['status'] ?? 'active')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_mailer_screen(): string
        {
            $status = self::dependency_status('apmailer');
            $settings = self::composer_settings();
            ob_start();
            ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html__('Delivery companion', 'apcampaign'); ?></p>
                    <h1><?php echo esc_html__('Mailer Settings', 'apcampaign'); ?></h1>
                </div>
                <div class="apcampaign-header-actions">
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url('create-raw')); ?>"><?php echo esc_html__('Raw HTML', 'apcampaign'); ?></a>
                    <a class="apcampaign-button primary" href="<?php echo esc_url(self::url('create-simple')); ?>"><?php echo esc_html__('Drag & Drop', 'apcampaign'); ?></a>
                </div>
            </header>
            <section class="apcampaign-panel apcampaign-companion-panel">
                <div class="apcampaign-companion-head">
                    <div>
                        <h2><?php echo esc_html__('Mailer status', 'apcampaign'); ?></h2>
                    </div>
                    <?php echo wp_kses_post(self::render_dependency_badge($status)); ?>
                </div>
                <?php echo wp_kses_post(self::render_dependency_guidance($status, 'apMailer')); ?>
            </section>
            <section class="apcampaign-panel">
                <div class="apcampaign-panel-head">
                    <div>
                        <p><?php echo esc_html__('Composer defaults', 'apcampaign'); ?></p>
                        <h2><?php echo esc_html__('Current sender fallback', 'apcampaign'); ?></h2>
                    </div>
                </div>
                <div class="apcampaign-companion-grid">
                    <article>
                        <span><?php echo esc_html__('Default From Name', 'apcampaign'); ?></span>
                        <strong><?php echo esc_html((string) $settings['default_from_name']); ?></strong>
                    </article>
                    <article>
                        <span><?php echo esc_html__('Default From Email', 'apcampaign'); ?></span>
                        <strong><?php echo esc_html((string) $settings['default_from_email']); ?></strong>
                    </article>
                    <article>
                        <span><?php echo esc_html__('Live Send Gate', 'apcampaign'); ?></span>
                        <strong><?php echo esc_html__('Requires list + mail route', 'apcampaign'); ?></strong>
                    </article>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_utility_nav(): string
        {
            $home_url = (string) apply_filters('apcampaign_app_home_url', 'https://artistprowp.com/apcampaign/');
            $home_label = (string) apply_filters('apcampaign_app_home_label', __('Home', 'apcampaign'));
            ob_start();
            ?>
            <nav class="apcampaign-utility-nav" aria-label="<?php echo esc_attr__('Campaign navigation', 'apcampaign'); ?>">
                <a href="<?php echo esc_url($home_url); ?>"><?php echo esc_html($home_label); ?></a>
                <a href="<?php echo esc_url(self::url()); ?>"><?php echo esc_html__('Campaigns', 'apcampaign'); ?></a>
                <a href="<?php echo esc_url(self::url('create')); ?>"><?php echo esc_html__('New Campaign', 'apcampaign'); ?></a>
                <button type="button" class="apcampaign-theme-toggle" data-apcampaign-theme-toggle aria-pressed="false" aria-label="<?php echo esc_attr__('Toggle light and dark mode', 'apcampaign'); ?>"></button>
            </nav>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_dashboard(): string
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $campaigns = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE status <> 'archived' ORDER BY updated_at DESC LIMIT 50", $t['campaigns']), ARRAY_A);
            $counts = APCampaign_Lifecycle::counts();
            $uncertain = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT j.id, j.campaign_id FROM %i j LEFT JOIN %i r ON r.send_job_id = j.id AND r.status = 'sending' WHERE j.status = 'review_required' OR (r.id IS NOT NULL AND j.updated_at < %s) ORDER BY j.id DESC LIMIT 20", $t['send_jobs'], $t['send_job_recipients'], wp_date('Y-m-d H:i:s', time() - 300, wp_timezone())), ARRAY_A);
            $review_recipients = current_user_can('manage_options') ? $wpdb->get_results($wpdb->prepare("SELECT r.id, r.email, r.send_job_id FROM %i r INNER JOIN %i j ON j.id = r.send_job_id WHERE r.status = 'sending' AND (j.status = 'review_required' OR j.updated_at < %s) ORDER BY r.id DESC LIMIT 20", $t['send_job_recipients'], $t['send_jobs'], wp_date('Y-m-d H:i:s', time() - 300, wp_timezone())), ARRAY_A) : [];
            $resumable = current_user_can('manage_options') ? $wpdb->get_results($wpdb->prepare("SELECT j.id, j.campaign_id FROM %i j LEFT JOIN %i r ON r.send_job_id = j.id AND r.status = 'sending' WHERE j.status = 'review_required' GROUP BY j.id, j.campaign_id HAVING COUNT(r.id) = 0 ORDER BY j.id DESC LIMIT 20", $t['send_jobs'], $t['send_job_recipients']), ARRAY_A) : [];
            $stalled = current_user_can('manage_options') ? $wpdb->get_results($wpdb->prepare("SELECT id, campaign_id FROM %i WHERE status = 'preparing' AND updated_at < %s ORDER BY id DESC LIMIT 20", $t['send_jobs'], wp_date('Y-m-d H:i:s', time() - 300, wp_timezone())), ARRAY_A) : [];
            $failed_sends = current_user_can('manage_options') ? $wpdb->get_results($wpdb->prepare("SELECT id, campaign_id, failed_count, error_message FROM %i WHERE status = 'failed' AND error_message <> '' ORDER BY id DESC LIMIT 10", $t['send_jobs']), ARRAY_A) : [];
            $summary = self::campaign_performance_summary();
            ob_start();
            ?>
            <?php if (!empty($stalled)) : ?>
                <div class="notice notice-warning" role="alert"><p><strong><?php echo esc_html__('A campaign preparation did not finish.', 'apcampaign'); ?></strong> <?php echo esc_html__('No messages can be sent by an incomplete preparation. Cancel it to prepare the campaign again.', 'apcampaign'); ?></p>
                <?php foreach ($stalled as $item) : ?>
                    <?php /* translators: 1: Campaign number, 2: Send number. */ ?>
                    <p><?php echo esc_html(sprintf(__('Campaign %1$d, send %2$d.', 'apcampaign'), (int) $item['campaign_id'], (int) $item['id'])); ?> <button type="button" data-apcampaign-cancel-preparation="<?php echo esc_attr((string) $item['id']); ?>"><?php echo esc_html__('Cancel incomplete preparation', 'apcampaign'); ?></button></p>
                <?php endforeach; ?></div>
            <?php endif; ?>
            <?php if (!empty($failed_sends)) : ?>
                <div class="notice notice-error"><p><strong><?php echo esc_html__('Some campaign sends need attention.', 'apcampaign'); ?></strong></p>
                <?php foreach ($failed_sends as $failed_send) : ?>
                    <?php /* translators: 1: Campaign number, 2: Send number, 3: Failure reason. */ ?>
                    <p><?php echo esc_html(sprintf(__('Campaign %1$d, send %2$d: %3$s', 'apcampaign'), (int) $failed_send['campaign_id'], (int) $failed_send['id'], (string) $failed_send['error_message'])); ?></p>
                <?php endforeach; ?></div>
            <?php endif; ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html((string) apply_filters(
                        'apcampaign_app_dashboard_eyebrow',
                        __('Standalone WordPress campaign system', 'apcampaign')
                    )); ?></p>
                    <h1><?php echo esc_html__('Campaign Dashboard', 'apcampaign'); ?></h1>
                </div>
                <div class="apcampaign-header-actions">
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url('create-raw')); ?>"><?php echo esc_html__('Raw HTML', 'apcampaign'); ?></a>
                    <a class="apcampaign-button primary" href="<?php echo esc_url(self::url('create-simple')); ?>"><?php echo esc_html__('Drag & Drop Email', 'apcampaign'); ?></a>
                </div>
            </header>
            <?php if (!empty($uncertain)) : ?>
                <div class="notice notice-warning" role="alert">
                    <strong><?php echo esc_html__('A campaign send needs review.', 'apcampaign'); ?></strong>
                    <?php echo esc_html__('A message may have reached the mail provider, but its result was not recorded. Check the provider logs and contact support before sending the campaign again.', 'apcampaign'); ?>
                    <?php foreach ($uncertain as $item) : ?>
                        <?php /* translators: Campaign and send identifiers. */ ?>
                        <span><?php echo esc_html(sprintf(__('Campaign %1$d, send %2$d.', 'apcampaign'), (int) $item['campaign_id'], (int) $item['id'])); ?></span>
                    <?php endforeach; ?>
                    <?php foreach ($resumable as $job) : ?>
                        <?php /* translators: Send identifier. */ ?>
                        <p><?php echo esc_html(sprintf(__('Send %d has no uncertain recipients. Resume the remaining queue.', 'apcampaign'), (int) $job['id'])); ?></p>
                        <button type="button" data-apcampaign-resume-job="<?php echo esc_attr((string) $job['id']); ?>"><?php echo esc_html__('Resume send', 'apcampaign'); ?></button>
                    <?php endforeach; ?>
                    <?php foreach ($review_recipients as $recipient) : ?>
                        <?php /* translators: Recipient email address. */ ?>
                        <p><?php echo esc_html(sprintf(__('Recipient: %s. Check your provider logs before choosing.', 'apcampaign'), (string) $recipient['email'])); ?></p>
                        <button type="button" data-apcampaign-review-job="<?php echo esc_attr((string) $recipient['send_job_id']); ?>" data-apcampaign-review-recipient="<?php echo esc_attr((string) $recipient['id']); ?>" data-apcampaign-review-outcome="accepted"><?php echo esc_html__('Provider accepted', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-review-job="<?php echo esc_attr((string) $recipient['send_job_id']); ?>" data-apcampaign-review-recipient="<?php echo esc_attr((string) $recipient['id']); ?>" data-apcampaign-review-outcome="rejected"><?php echo esc_html__('Provider rejected', 'apcampaign'); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <section class="apcampaign-kpis">
                <?php foreach (['campaigns' => 'Total Campaigns', 'send_jobs' => 'Sends', 'contact_lists' => 'Lists', 'contacts' => 'Contacts', 'preflight_reports' => 'Preflights'] as $key => $label) : ?>
                    <article><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html((string) ($counts[$key] ?? 0)); ?></strong></article>
                <?php endforeach; ?>
            </section>
            <section class="apcampaign-panel apcampaign-performance-panel">
                <div class="apcampaign-panel-head">
                    <div>
                        <p><?php echo esc_html__('Campaign performance', 'apcampaign'); ?></p>
                        <h2><?php echo esc_html__('Sent Campaign Summary', 'apcampaign'); ?></h2>
                    </div>
                    <a href="<?php echo esc_url(self::url('archived')); ?>"><?php echo esc_html__('Archived Campaigns', 'apcampaign'); ?></a>
                </div>
                <div class="apcampaign-performance-grid">
                    <article><span><?php echo esc_html__('Sent Campaigns', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $summary['sent_campaigns'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Emails Sent', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $summary['emails_sent'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Audience Total', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $summary['recipients_total'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Open Rate', 'apcampaign'); ?></span><strong><?php echo esc_html(self::format_rate((float) $summary['open_rate'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Click Rate', 'apcampaign'); ?></span><strong><?php echo esc_html(self::format_rate((float) $summary['click_rate'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Failures', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $summary['failures'])); ?></strong></article>
                </div>
            </section>
            <section class="apcampaign-panel">
                <h2><?php echo esc_html__('Recent Campaigns', 'apcampaign'); ?></h2>
                <div class="apcampaign-campaign-list">
                    <?php if (!$campaigns) : ?>
                        <article class="apcampaign-campaign-row"><p><?php echo esc_html__('No campaigns yet.', 'apcampaign'); ?></p></article>
                    <?php endif; ?>
                    <?php foreach ($campaigns as $campaign) : ?>
                        <?php echo wp_kses_post(self::render_campaign_row($campaign, false)); ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_archived_campaigns(): string
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $campaigns = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE status = 'archived' ORDER BY updated_at DESC LIMIT 200", $t['campaigns']), ARRAY_A);
            ob_start();
            ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html__('Campaign archive', 'apcampaign'); ?></p>
                    <h1><?php echo esc_html__('Archived Campaigns', 'apcampaign'); ?></h1>
                </div>
                <div class="apcampaign-header-actions">
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url()); ?>"><?php echo esc_html__('Dashboard', 'apcampaign'); ?></a>
                </div>
            </header>
            <section class="apcampaign-panel">
                <h2><?php echo esc_html__('Previously archived campaigns', 'apcampaign'); ?></h2>
                <div class="apcampaign-campaign-list">
                    <?php if (!$campaigns) : ?>
                        <article class="apcampaign-campaign-row"><p><?php echo esc_html__('No archived campaigns yet.', 'apcampaign'); ?></p></article>
                    <?php endif; ?>
                    <?php foreach ($campaigns as $campaign) : ?>
                        <?php echo wp_kses_post(self::render_campaign_row($campaign, true)); ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        /**
         * @param array<string,mixed> $campaign
         */
        private static function render_campaign_row(array $campaign, bool $archived): string
        {
            $campaign_id = (int) $campaign['id'];
            $stats = self::campaign_detail_stats($campaign_id);
            ob_start();
            ?>
            <article class="apcampaign-campaign-row" data-apcampaign-row="<?php echo esc_attr((string) $campaign_id); ?>">
                <div>
                    <h3><?php echo esc_html((string) $campaign['name']); ?></h3>
                    <p><?php echo esc_html((string) ($campaign['subject_line'] ?: __('No subject yet', 'apcampaign'))); ?></p>
                    <div class="apcampaign-row-metrics">
                        <span><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $campaign['status']))); ?></span>
                        <span><?php echo esc_html((string) $campaign['composer_mode']); ?></span>
                    </div>
                </div>
                <div class="apcampaign-row-actions">
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url('view/' . $campaign_id)); ?>"><?php echo esc_html__('View', 'apcampaign'); ?></a>
                    <div class="apcampaign-row-stats" aria-label="<?php echo esc_attr__('Campaign stats', 'apcampaign'); ?>">
                        <span><b><?php echo esc_html(number_format_i18n((int) $stats['sent'])); ?></b> <?php echo esc_html__('sent', 'apcampaign'); ?></span>
                        <span><b><?php echo esc_html(number_format_i18n((int) $stats['opened'])); ?></b> <?php echo esc_html__('opened', 'apcampaign'); ?></span>
                        <span><b><?php echo esc_html(number_format_i18n((int) $stats['clicked'])); ?></b> <?php echo esc_html__('clicked', 'apcampaign'); ?></span>
                        <span><b><?php echo esc_html(self::format_rate((float) $stats['open_rate'])); ?></b> <?php echo esc_html__('open', 'apcampaign'); ?></span>
                    </div>
                    <span class="apcampaign-row-updated"><?php echo esc_html__('Updated ', 'apcampaign') . esc_html((string) $campaign['updated_at']); ?></span>
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url('edit/' . $campaign_id)); ?>"><?php echo esc_html__('Edit', 'apcampaign'); ?></a>
                    <?php if (!$archived) : ?>
                        <?php if (has_action('wp_ajax_apcampaign_app_retarget')) : ?>
                        <button type="button" class="apcampaign-button" data-apcampaign-retarget="<?php echo esc_attr((string) $campaign_id); ?>"><?php echo esc_html__('Retarget', 'apcampaign'); ?></button>
                        <?php endif; ?>
                        <button type="button" class="apcampaign-button quiet" data-apcampaign-archive="<?php echo esc_attr((string) $campaign_id); ?>"><?php echo esc_html__('Archive', 'apcampaign'); ?></button>
                    <?php endif; ?>
                </div>
            </article>
            <?php
            return (string) ob_get_clean();
        }


        private static function render_document_guide(): string
        {
            ob_start();
            ?>
            <div class="apcampaign-panel" style="max-width:920px;margin:24px auto;padding:32px;">
                <a href="<?php echo esc_url(self::url('create-raw')); ?>">&larr; Back to Raw HTML Campaigns</a>
                <h1><?php echo esc_html__('Prepare an eBlast Document', 'apcampaign'); ?></h1>
                <p><?php echo esc_html__('Use the downloadable template to prepare campaign copy, images and button links in Microsoft Word or Google Docs. The importer converts the document into email-safe HTML and places it in a draft campaign for review.', 'apcampaign'); ?></p>
                <p><?php echo esc_html__('The template includes sample images and example copy, so you can upload it as-is first to see how the conversion works. Then replace the sample content with the final campaign content and upload again.', 'apcampaign'); ?></p>
                <p><strong><?php echo esc_html__('Important:', 'apcampaign'); ?></strong> <?php echo esc_html__('text inside square brackets is guidance for the person preparing the Word document. The brackets mark labels and instructions the importer knows how to ignore. Do not delete the bracketed labels or instruction structure. Replace the sample campaign words, links and images around those labels with your real content, then upload again. Bracketed instruction text is skipped by the importer and is not meant to appear in the generated HTML.', 'apcampaign'); ?></p>
                <p><a class="apcampaign-button" href="<?php echo esc_url(APCAMPAIGN_PLUGIN_URL . 'assets/templates/apcampaign-email-document-template.docx'); ?>" download><?php echo esc_html__('Download the Word template', 'apcampaign'); ?></a></p>
                <h2><?php echo esc_html__('Required document fields', 'apcampaign'); ?></h2>
                <ol>
                    <li><?php echo wp_kses_post(__('Complete <strong>Campaign Name</strong>, <strong>Subject Line</strong> and <strong>Preview Text</strong>.', 'apcampaign')); ?></li>
                    <li><?php echo esc_html__('Keep the square-bracket instruction labels in the template. They are there to guide the importer and the person preparing the document; they will not be published in the HTML.', 'apcampaign'); ?></li>
                    <li><?php echo wp_kses_post(__('Replace each sample image with the final full-quality image. In Word, select the sample image and use <strong>Picture Format &rarr; Change Picture</strong>, or delete it and choose <strong>Insert &rarr; Pictures</strong>.', 'apcampaign')); ?></li>
                    <li><?php echo esc_html__('Write headings and body copy in normal paragraphs.', 'apcampaign'); ?></li>
                    <li><?php echo esc_html__('Apply the destination hyperlink directly to each short CTA button line.', 'apcampaign'); ?></li>
                    <li><?php echo esc_html__('Add another image, heading, copy and CTA group when another section is needed.', 'apcampaign'); ?></li>
                </ol>
                <h2><?php echo esc_html__('Google Docs', 'apcampaign'); ?></h2>
                <p><?php echo wp_kses_post(__('Open the Google Doc and choose <strong>File &rarr; Download &rarr; Microsoft Word (.docx)</strong>, then import that downloaded file.', 'apcampaign')); ?></p>
                <h2><?php echo esc_html__('Making changes', 'apcampaign'); ?></h2>
                <p><?php echo esc_html__('Use the Word document as the source for the campaign. If copy, images, button text or links need to change, update the Word document and upload it again. The importer will regenerate the Raw HTML from the latest document.', 'apcampaign'); ?></p>
                <p><?php echo esc_html__('Manual edits in the Raw HTML box are for staff cleanup or emergency fixes only. Reuploading the Word document replaces the generated Raw HTML, including any manual HTML changes made after the previous upload.', 'apcampaign'); ?></p>
                <h2><?php echo esc_html__('More sections and custom designs', 'apcampaign'); ?></h2>
                <p><?php echo esc_html__('To add another section, copy the same simple pattern: image, section heading, section copy, button copy and button link. Keep sections stacked vertically for the best email results.', 'apcampaign'); ?></p>
                <p><?php echo esc_html__('For more elaborate layouts, use the campaign block designer or have a designer create the HTML. This Word importer is meant for clean, fast eBlasts, not complex custom design layouts.', 'apcampaign'); ?></p>
                <h2><?php echo esc_html__('Avoid these Word features', 'apcampaign'); ?></h2>
                <p><?php echo esc_html__('Do not use floating text boxes, columns, headers, footers or overlapping images. They do not translate reliably to email.', 'apcampaign'); ?></p>
                <h2><?php echo esc_html__('Before sending', 'apcampaign'); ?></h2>
                <p><?php echo esc_html__('Review the conversion report, check desktop and mobile previews, save the campaign as a draft and approve a test send. Importing a document never sends a campaign.', 'apcampaign'); ?></p>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_create_choice(): string
        {
            ob_start();
            ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html__('Choose a composer', 'apcampaign'); ?></p>
                    <h1><?php echo esc_html__('Create Campaign', 'apcampaign'); ?></h1>
                </div>
            </header>
            <section class="apcampaign-choice-grid">
                <a class="apcampaign-choice-card" href="<?php echo esc_url(self::url('create-simple')); ?>">
                    <strong><?php echo esc_html__('Drag & Drop', 'apcampaign'); ?></strong>
                    <span><?php echo esc_html__('Visual block builder with preview, preflight, test, schedule, and send controls.', 'apcampaign'); ?></span>
                </a>
                <a class="apcampaign-choice-card" href="<?php echo esc_url(self::url('create-raw')); ?>">
                    <strong><?php echo esc_html__('Raw HTML', 'apcampaign'); ?></strong>
                    <span><?php echo esc_html__('Paste complete email HTML, inspect live preview, run preflight, and send through the same lifecycle.', 'apcampaign'); ?></span>
                </a>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_composer(int $campaign_id = 0, ?string $forced_mode = null): string
        {
            $campaign = $campaign_id > 0 ? self::campaign($campaign_id) : null;
            $snapshot = $campaign_id > 0 ? self::current_snapshot($campaign_id) : null;
            $mode = $forced_mode ?: (string) ($campaign['composer_mode'] ?? 'block');
            $design = $snapshot ? json_decode((string) ($snapshot['design_json'] ?? '{"blocks":[]}'), true) : ['blocks' => []];
            if (!is_array($design)) {
                $design = ['blocks' => []];
            }
            $raw_html = (string) ($snapshot['raw_html'] ?? '');
            $lists = self::contact_lists();
            $is_raw = $mode === 'raw_html';
            $settings = self::composer_settings();
            ob_start();
            ?>
            <?php if (!$is_raw) : ?>
                <section class="apcampaign-mobile-block-notice">
                    <strong><?php echo esc_html__('Use the block editor on an iPad or computer', 'apcampaign'); ?></strong>
                    <p><?php echo esc_html__('The visual canvas needs a wider screen. On this phone, use Raw HTML for an urgent copy-and-paste edit.', 'apcampaign'); ?></p>
                    <a class="apcampaign-button primary" href="<?php echo esc_url(self::url('create-raw')); ?>"><?php echo esc_html__('Open Raw HTML', 'apcampaign'); ?></a>
                </section>
            <?php endif; ?>
            <form class="apcampaign-composer <?php echo $is_raw ? 'is-raw' : 'is-simple'; ?>" data-apcampaign-composer data-campaign-id="<?php echo esc_attr((string) $campaign_id); ?>">
                <header class="apcampaign-composer-toolbar">
                    <div class="apcampaign-toolbar-row apcampaign-toolbar-primary">
                        <label class="apcampaign-field apcampaign-field-name"><span><?php echo esc_html__('Campaign Name', 'apcampaign'); ?></span><input name="name" value="<?php echo esc_attr((string) ($campaign['name'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Internal campaign name...', 'apcampaign'); ?>"></label>
                        <label class="apcampaign-field apcampaign-field-subject"><span><?php echo esc_html__('Subject Line', 'apcampaign'); ?> <em><?php echo esc_html__('best under 60 characters', 'apcampaign'); ?></em></span><input name="subject_line" maxlength="120" value="<?php echo esc_attr((string) ($campaign['subject_line'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Clear, specific subject...', 'apcampaign'); ?>"></label>
                        <label class="apcampaign-field apcampaign-field-preview"><span><?php echo esc_html__('Preview Text', 'apcampaign'); ?> <em><?php echo esc_html__('best under 90 characters', 'apcampaign'); ?></em></span><input name="preheader_text" maxlength="160" value="<?php echo esc_attr((string) ($campaign['preheader_text'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Short inbox preview...', 'apcampaign'); ?>"></label>
                    </div>
                    <div class="apcampaign-toolbar-row apcampaign-toolbar-secondary">
                        <label><?php echo esc_html__('From:', 'apcampaign'); ?><input name="from_name" value="<?php echo esc_attr((string) ($campaign['from_name'] ?? $settings['default_from_name'])); ?>" placeholder="<?php echo esc_attr__('Name', 'apcampaign'); ?>"></label>
                        <input name="from_email" value="<?php echo esc_attr((string) ($campaign['from_email'] ?? $settings['default_from_email'])); ?>" placeholder="<?php echo esc_attr__('From email', 'apcampaign'); ?>">
                        <label><?php echo esc_html__('Send To:', 'apcampaign'); ?><select name="send_to_type"><option value="own_list"><?php echo esc_html__('Your List', 'apcampaign'); ?></option></select></label>
                        <label><?php echo esc_html__('List:', 'apcampaign'); ?><select name="audience_key">
                            <option value=""><?php echo esc_html__('-- Select --', 'apcampaign'); ?></option>
                            <?php foreach ($lists as $list) : ?>
                                <option value="<?php echo esc_attr('list_' . (int) $list['id']); ?>" <?php selected((string) ($campaign['audience_key'] ?? ''), 'list_' . (int) $list['id']); ?>><?php echo esc_html((string) $list['name'] . ' (' . (int) $list['active_count'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    </div>
                    <div class="apcampaign-actions">
                        <a class="apcampaign-action-link" href="<?php echo esc_url(self::url($is_raw ? 'create-raw' : 'create-simple')); ?>"><?php echo esc_html__('New', 'apcampaign'); ?></a>
                        <button type="button" data-apcampaign-templates><?php echo esc_html__('Templates', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-save-template><?php echo esc_html__('Save Template', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-download><?php echo esc_html__('Download', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-preview><?php echo esc_html__('Preview', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-spam><?php echo esc_html__('Spam', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-preflight><?php echo esc_html__('Predict', 'apcampaign'); ?></button>
                        <button type="submit"><?php echo esc_html__('Save Draft', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-test><?php echo esc_html__('Test', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-schedule class="blue"><?php echo esc_html__('Schedule', 'apcampaign'); ?></button>
                        <button type="button" data-apcampaign-send class="primary"><?php echo esc_html__('Send', 'apcampaign'); ?></button>
                    </div>
                </header>
                <input type="hidden" name="composer_mode" value="<?php echo esc_attr($mode); ?>">
                <textarea name="design_json" hidden><?php echo esc_textarea(wp_json_encode($design)); ?></textarea>
                <textarea name="rendered_html" hidden><?php echo esc_textarea((string) ($snapshot['rendered_html'] ?? '')); ?></textarea>
                <section class="apcampaign-studio">
                    <aside class="apcampaign-blocks <?php echo $is_raw ? 'hidden' : ''; ?>" data-apcampaign-blocks>
                        <h2><?php echo esc_html__('Content Blocks', 'apcampaign'); ?></h2>
                        <div class="apcampaign-tip compact"><strong><?php echo esc_html__('Blocks:', 'apcampaign'); ?></strong> <?php echo esc_html__('Drag or click into the editor.', 'apcampaign'); ?></div>
                        <h3><?php echo esc_html__('Blocks', 'apcampaign'); ?></h3>
                        <?php foreach (self::block_catalog() as $type => $block) : ?>
                            <div class="apcampaign-block-tile" role="button" tabindex="0" draggable="true" data-apcampaign-add-block="<?php echo esc_attr($type); ?>"><?php echo esc_html($block['label']); ?></div>
                        <?php endforeach; ?>
                    </aside>
                    <section class="apcampaign-canvas-wrap">
                        <?php if (!$is_raw) : ?>
                            <div class="apcampaign-onboarding compact" data-apcampaign-onboarding><strong><?php echo esc_html__('Composer ready', 'apcampaign'); ?></strong><span><?php echo esc_html__('Preview and Predict before scheduling.', 'apcampaign'); ?></span><button type="button" data-apcampaign-dismiss-tip>×</button></div>
                        <?php endif; ?>
                        <?php if ($is_raw) : ?>
                            <div class="apcampaign-raw-head"><span><?php echo esc_html__('HTML Editor', 'apcampaign'); ?></span><em data-apcampaign-char-count>0 chars</em><?php if (has_action('wp_ajax_apcampaign_app_import_docx')) : ?><button type="button" data-apcampaign-docx-trigger><?php echo esc_html__('Import Word eBlast', 'apcampaign'); ?></button><a href="<?php echo esc_url(self::url('document-guide')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Template & instructions', 'apcampaign'); ?></a><?php endif; ?><button type="button" data-apcampaign-clear><?php echo esc_html__('Clear', 'apcampaign'); ?></button></div>
                            <input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" data-apcampaign-docx-file hidden>
                            <div class="apcampaign-word-import-status" data-apcampaign-docx-status></div>
                        <?php endif; ?>
                        <div class="apcampaign-canvas <?php echo $mode === 'raw_html' ? 'hidden' : ''; ?>" data-apcampaign-canvas></div>
                        <textarea class="apcampaign-raw <?php echo $mode === 'block' ? 'hidden' : ''; ?>" name="raw_html" data-apcampaign-raw placeholder="<?php echo esc_attr__('Paste your full HTML email here. Include your complete HTML document when available. Use {unsubscribe_url} for the required unsubscribe link.', 'apcampaign'); ?>"><?php echo esc_textarea($raw_html); ?></textarea>
                    </section>
                    <aside class="apcampaign-inspector">
                        <h2><?php echo esc_html($is_raw ? __('Live Preview', 'apcampaign') : __('Block Settings', 'apcampaign')); ?></h2>
                        <div data-apcampaign-results>
                            <p><?php echo esc_html($is_raw ? __('Preview, Spam, or Predict when ready.', 'apcampaign') : __('Select content in the editor or run Predict when ready.', 'apcampaign')); ?></p>
                        </div>
                    </aside>
                </section>
                <section class="apcampaign-preview" data-apcampaign-preview-panel hidden>
                    <iframe sandbox="" title="<?php echo esc_attr__('Campaign preview', 'apcampaign'); ?>"></iframe>
                </section>
            </form>
            <?php
            return (string) ob_get_clean();
        }

        private static function render_sent_view(int $campaign_id): string
        {
            $campaign = self::campaign($campaign_id);
            $snapshot = self::current_snapshot($campaign_id);
            if (!$campaign || !$snapshot) {
                return '<section class="apcampaign-panel"><p>' . esc_html__('Campaign not found.', 'apcampaign') . '</p></section>';
            }
            $stats = self::campaign_detail_stats($campaign_id);
            $recipients = self::campaign_recipient_activity($campaign_id);
            ob_start();
            ?>
            <header class="apcampaign-header">
                <div>
                    <p><?php echo esc_html__('Campaign report', 'apcampaign'); ?></p>
                    <h1><?php echo esc_html((string) ($campaign['subject_line'] ?: $campaign['name'])); ?></h1>
                </div>
                <div class="apcampaign-header-actions">
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url()); ?>"><?php echo esc_html__('Dashboard', 'apcampaign'); ?></a>
                    <a class="apcampaign-button" href="<?php echo esc_url(self::url('edit/' . $campaign_id)); ?>"><?php echo esc_html__('Edit', 'apcampaign'); ?></a>
                </div>
            </header>
            <section class="apcampaign-panel apcampaign-report">
                <div class="apcampaign-row-metrics">
                    <span><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $campaign['status']))); ?></span>
                    <span><?php echo esc_html((string) $campaign['composer_mode']); ?></span>
                    <span><?php echo esc_html__('Updated ', 'apcampaign') . esc_html((string) $campaign['updated_at']); ?></span>
                </div>
                <div class="apcampaign-performance-grid apcampaign-report-grid">
                    <article><span><?php echo esc_html__('Recipients', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $stats['recipients'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Sent', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $stats['sent'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Opened', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $stats['opened'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Clicked', 'apcampaign'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $stats['clicked'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Open Rate', 'apcampaign'); ?></span><strong><?php echo esc_html(self::format_rate((float) $stats['open_rate'])); ?></strong></article>
                    <article><span><?php echo esc_html__('Click Rate', 'apcampaign'); ?></span><strong><?php echo esc_html(self::format_rate((float) $stats['click_rate'])); ?></strong></article>
                </div>
            </section>
            <section class="apcampaign-panel apcampaign-report">
                <h2><?php echo esc_html__('Recipient Activity', 'apcampaign'); ?></h2>
                <div class="apcampaign-report-table-wrap">
                    <table class="apcampaign-table apcampaign-report-table">
                        <thead><tr><th><?php echo esc_html__('Recipient', 'apcampaign'); ?></th><th><?php echo esc_html__('Status', 'apcampaign'); ?></th><th><?php echo esc_html__('Opens', 'apcampaign'); ?></th><th><?php echo esc_html__('Clicks', 'apcampaign'); ?></th><th><?php echo esc_html__('Last Activity', 'apcampaign'); ?></th></tr></thead>
                        <tbody>
                            <?php if (!$recipients) : ?>
                                <tr><td colspan="5"><?php echo esc_html__('No recipient activity has been recorded for this campaign yet.', 'apcampaign'); ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($recipients as $recipient) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $recipient['email']); ?></td>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $recipient['status']))); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $recipient['opens'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $recipient['clicks'])); ?></td>
                                    <td><?php echo esc_html((string) ($recipient['last_activity'] ?: __('No activity yet', 'apcampaign'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="apcampaign-panel apcampaign-sent-view">
                <h2><?php echo esc_html__('Email Preview', 'apcampaign'); ?></h2>
                <iframe sandbox="" srcdoc="<?php echo esc_attr(APCampaign_Renderer::wrap_document((string) $snapshot['rendered_html'], (string) $campaign['subject_line'])); ?>"></iframe>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        /**
         * @return array<string,array<string,mixed>>
         */
        private static function block_catalog(): array
        {
            return [
                'hero_image' => ['label' => 'Hero + Image', 'options' => []],
                'hero' => ['label' => 'Hero', 'options' => []],
                'heading' => ['label' => 'Heading', 'options' => ['text' => 'Email headline', 'level' => 1, 'color' => '#111827']],
                'image' => ['label' => 'Image', 'options' => ['src' => '', 'alt' => '', 'width' => 600]],
                'text' => ['label' => 'Text', 'options' => ['text' => 'Write your message here.', 'color' => '#374151']],
                'button' => ['label' => 'Button', 'options' => ['text' => 'Learn More', 'url' => 'https://example.com', 'background' => '#2563eb', 'color' => '#ffffff']],
                'teaser' => ['label' => 'Article Teaser', 'options' => []],
                'two_column' => ['label' => 'Two Columns', 'options' => []],
                'card' => ['label' => 'Card', 'options' => []],
                'quote' => ['label' => 'Quote', 'options' => []],
                'list' => ['label' => 'List', 'options' => []],
                'social' => ['label' => 'Social Links', 'options' => []],
                'divider' => ['label' => 'Divider', 'options' => []],
                'spacer' => ['label' => 'Spacer', 'options' => ['height' => 24]],
            ];
        }

        private static function campaign(int $campaign_id): ?array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $t['campaigns'], $campaign_id), ARRAY_A);
            return is_array($row) ? $row : null;
        }

        private static function current_snapshot(int $campaign_id): ?array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE campaign_id = %d AND is_current = 1 ORDER BY id DESC LIMIT 1", $t['content_snapshots'], $campaign_id), ARRAY_A);
            return is_array($row) ? $row : null;
        }

        private static function current_snapshot_id(int $campaign_id): int
        {
            $snapshot = self::current_snapshot($campaign_id);
            return $snapshot ? (int) $snapshot['id'] : 0;
        }

        /**
         * @return array<string,int|float>
         */
        private static function campaign_detail_stats(int $campaign_id): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $job = $wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(total_recipients),0) AS recipients, COALESCE(SUM(sent_count),0) AS sent, COALESCE(SUM(failed_count),0) AS failed FROM %i WHERE campaign_id = %d", $t['send_jobs'],
                $campaign_id
            ), ARRAY_A) ?: [];
            $events = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN event_type IN ('open','opened') THEN IF(recipient_id > 0, CONCAT('r:', recipient_id), CONCAT('e:', email)) END) AS opened,
                    COUNT(DISTINCT CASE WHEN event_type IN ('click','clicked') THEN IF(recipient_id > 0, CONCAT('r:', recipient_id), CONCAT('e:', email)) END) AS clicked
                FROM %i
                WHERE campaign_id = %d",
                $t['tracking_events'], $campaign_id
            ), ARRAY_A) ?: [];
            $recipients = max((int) ($job['recipients'] ?? 0), (int) ($job['sent'] ?? 0));
            $opened = (int) ($events['opened'] ?? 0);
            $clicked = (int) ($events['clicked'] ?? 0);

            return [
                'recipients' => $recipients,
                'sent' => (int) ($job['sent'] ?? 0),
                'failed' => (int) ($job['failed'] ?? 0),
                'opened' => $opened,
                'clicked' => $clicked,
                'open_rate' => $recipients > 0 ? ($opened / $recipients) * 100 : 0.0,
                'click_rate' => $recipients > 0 ? ($clicked / $recipients) * 100 : 0.0,
            ];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function campaign_recipient_activity(int $campaign_id): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            return $wpdb->get_results($wpdb->prepare(
                "SELECT r.email, r.status,
                    SUM(CASE WHEN e.event_type IN ('open','opened') THEN 1 ELSE 0 END) AS opens,
                    SUM(CASE WHEN e.event_type IN ('click','clicked') THEN 1 ELSE 0 END) AS clicks,
                    MAX(e.occurred_at) AS last_activity
                FROM %i r
                LEFT JOIN %i e ON e.recipient_id = r.id OR (e.tracking_token <> '' AND e.tracking_token = r.tracking_token)
                WHERE r.campaign_id = %d
                GROUP BY r.id, r.email, r.status
                ORDER BY last_activity DESC, r.email ASC
                LIMIT 500",
                $t['send_job_recipients'], $t['tracking_events'], $campaign_id
            ), ARRAY_A) ?: [];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        /**
         * @return array<string,int|float>
         */
        private static function campaign_performance_summary(): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $campaign_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status IN ('sent','completed','send_complete')", $t['campaigns']));
            $jobs = $wpdb->get_row($wpdb->prepare("SELECT
                    COALESCE(SUM(total_recipients),0) AS recipients_total,
                    COALESCE(SUM(sent_count),0) AS emails_sent,
                    COALESCE(SUM(failed_count),0) AS failures
                FROM %i", $t['send_jobs']), ARRAY_A) ?: [];
            $events = $wpdb->get_row($wpdb->prepare("SELECT
                    COUNT(DISTINCT CASE WHEN event_type IN ('open','opened') THEN IF(recipient_id > 0, CONCAT('r:', recipient_id), CONCAT('e:', email)) END) AS unique_opens,
                    COUNT(DISTINCT CASE WHEN event_type IN ('click','clicked') THEN IF(recipient_id > 0, CONCAT('r:', recipient_id), CONCAT('e:', email)) END) AS unique_clicks
                FROM %i", $t['tracking_events']), ARRAY_A) ?: [];
            $history = $wpdb->get_row($wpdb->prepare("SELECT
                    COUNT(*) AS imported_campaigns,
                    COALESCE(SUM(sent_count),0) AS imported_sent,
                    COALESCE(SUM(sent_count * open_rate),0) AS weighted_open,
                    COALESCE(SUM(sent_count * click_rate),0) AS weighted_click
                FROM %i", $t['history_metrics']), ARRAY_A) ?: [];

            $job_sent = (int) ($jobs['emails_sent'] ?? 0);
            $job_recipients = (int) ($jobs['recipients_total'] ?? 0);
            $imported_sent = (int) ($history['imported_sent'] ?? 0);
            $emails_sent = $job_sent + $imported_sent;
            $recipients_total = max($job_recipients, $job_sent) + $imported_sent;
            $history_open_rate = $imported_sent > 0 ? (float) ($history['weighted_open'] ?? 0) / $imported_sent : 0.0;
            $history_click_rate = $imported_sent > 0 ? (float) ($history['weighted_click'] ?? 0) / $imported_sent : 0.0;
            $open_rate = $emails_sent > 0 ? (((int) ($events['unique_opens'] ?? 0) + ($imported_sent * ($history_open_rate / 100))) / $emails_sent) * 100 : 0.0;
            $click_rate = $emails_sent > 0 ? (((int) ($events['unique_clicks'] ?? 0) + ($imported_sent * ($history_click_rate / 100))) / $emails_sent) * 100 : 0.0;

            return [
                'sent_campaigns' => $campaign_count + (int) ($history['imported_campaigns'] ?? 0),
                'emails_sent' => $emails_sent,
                'recipients_total' => $recipients_total,
                'open_rate' => $open_rate,
                'click_rate' => $click_rate,
                'failures' => (int) ($jobs['failures'] ?? 0),
            ];
        }

        private static function format_rate(float $rate): string
        {
            return number_format_i18n(max(0.0, $rate), 1) . '%';
        }

        private static function update_campaign(int $campaign_id, array $data): bool
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            $data['updated_at'] = current_time('mysql');
            $updated = $wpdb->update($t['campaigns'], $data, ['id' => $campaign_id]);
            if ($updated === false) return false;
            if ($updated > 0) return true;
            $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE id = %d', $t['campaigns'], $campaign_id));
            return $wpdb->last_error === '' && (int) $exists === $campaign_id;
        }

        private static function contact_lists(): array
        {
            global $wpdb;
            $t = APCampaign_Schema::tables();
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE status = 'active' ORDER BY name ASC LIMIT 200", $t['contact_lists']), ARRAY_A) ?: [];
        }

        private static function composer_settings(): array
        {
            return [
                'default_from_name' => (string) get_option('apcampaign_default_from_name', get_bloginfo('name')),
                'default_from_email' => (string) get_option('apcampaign_default_from_email', get_option('admin_email')),
                'spam_pass_score' => (int) get_option('apcampaign_spam_pass_score', 70),
                'preflight_link_limit' => (int) get_option('apcampaign_preflight_link_limit', 25),
                'retarget_owner_only' => (bool) get_option('apcampaign_retarget_owner_only', false),
                'physical_address' => (string) get_option('apcampaign_physical_address', ''),
            ];
        }

        /**
         * @return array<string,mixed>
         */
        private static function prepare_send_job(int $campaign_id, ?int $scheduled_timestamp = null): array
        {
            if (!APCampaign_Delivery::compliance_ready()) {
                return ['success' => false, 'error' => 'Add the organization physical mailing address in Mailer Settings before sending.'];
            }
            $campaign = self::campaign($campaign_id);
            $snapshot_id = self::current_snapshot_id($campaign_id);
            if (!$campaign || $snapshot_id < 1) {
                return ['success' => false, 'error' => 'Campaign content is missing.'];
            }
            if (!preg_match('/^list_(\d+)$/', (string) ($campaign['audience_key'] ?? ''), $matches)) {
                return ['success' => false, 'error' => 'Select a campaign list before sending.'];
            }
            $audience = APCampaign_Lifecycle::create_audience_snapshot_from_list($campaign_id, (int) $matches[1], ['behavior' => 'all']);
            if (empty($audience['audience_snapshot_id']) || empty($audience['recipient_count'])) {
                return ['success' => false, 'error' => 'The selected list has no active, unsuppressed recipients.'];
            }
            return APCampaign_Lifecycle::queue_send_job(
                $campaign_id,
                $snapshot_id,
                (int) $audience['audience_snapshot_id'],
                $scheduled_timestamp === null ? [] : ['scheduled_timestamp' => $scheduled_timestamp]
            );
        }

        /**
         * @return array{state:string,label:string,detail:string,url:string}
         */
        private static function dependency_status(string $dependency): array
        {
            if (self::suite_context_active()) {
                return [
                    'state' => 'suite',
                    'label' => __('Suite managed', 'apcampaign'),
                    'detail' => __('This setting is managed by PubMotor Suite.', 'apcampaign'),
                    'url' => home_url('/crm/'),
                ];
            }

            if ($dependency === 'apmailer' && (defined('APMAILER_VERSION') || class_exists('APMailer_Contract'))) {
                return [
                    'state' => 'active',
                    'label' => __('apMailer active', 'apcampaign'),
                    'detail' => __('apMailer is installed and active.', 'apcampaign'),
                    'url' => admin_url('plugins.php'),
                ];
            }

            if ($dependency === 'aplists' && (defined('APLISTS_VERSION') || class_exists('APLists_Contract'))) {
                return [
                    'state' => 'active',
                    'label' => __('apLists active', 'apcampaign'),
                    'detail' => __('apLists is installed and active.', 'apcampaign'),
                    'url' => admin_url('plugins.php'),
                ];
            }

            return [
                'state' => 'missing',
                'label' => $dependency === 'apmailer' ? __('apMailer needed', 'apcampaign') : __('apLists needed', 'apcampaign'),
                'detail' => $dependency === 'apmailer'
                    ? __('Install and activate apMailer with standalone apCampaign before live sending.', 'apcampaign')
                    : __('Install and activate apLists with standalone apCampaign before building live audiences.', 'apcampaign'),
                'url' => admin_url('plugins.php'),
            ];
        }

        private static function suite_context_active(): bool
        {
            return defined('AP2_CRM_VERSION') || class_exists('AP2_CRM_Module_Registry');
        }

        /**
         * @param array{state:string,label:string,detail:string,url:string} $status
         */
        private static function render_dependency_badge(array $status): string
        {
            $state = sanitize_html_class($status['state']);
            return '<span class="apcampaign-status-badge is-' . esc_attr($state) . '">' . esc_html($status['label']) . '</span>';
        }

        /**
         * @param array{state:string,label:string,detail:string,url:string} $status
         */
        private static function render_dependency_guidance(array $status, string $name): string
        {
            ob_start();
            ?>
            <div class="apcampaign-companion-guidance">
                <p><?php echo esc_html($status['detail']); ?></p>
                <?php if ($status['state'] === 'suite') : ?>
                    <a class="apcampaign-button" href="<?php echo esc_url($status['url']); ?>"><?php echo esc_html__('Open PubMotor Suite', 'apcampaign'); ?></a>
                <?php elseif ($status['state'] === 'active') : ?>
                    <?php /* translators: %s: companion plugin name. */ ?>
                    <a class="apcampaign-button" href="<?php echo esc_url($status['url']); ?>"><?php echo esc_html(sprintf(__('%s plugin active', 'apcampaign'), $name)); ?></a>
                <?php else : ?>
                    <?php /* translators: %s: companion plugin name. */ ?>
                    <a class="apcampaign-button primary" href="<?php echo esc_url($status['url']); ?>"><?php echo esc_html(sprintf(__('Activate %s', 'apcampaign'), $name)); ?></a>
                <?php endif; ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        /** Allow only the controls needed by the campaign editor and sandboxed preview. */
        private static function allowed_app_markup(): array
        {
            $allowed = wp_kses_allowed_html('post');
            $global = ['id' => true, 'class' => true, 'style' => true, 'title' => true, 'data-*' => true, 'aria-*' => true, 'role' => true, 'hidden' => true];
            $allowed['button'] = array_merge($global, ['type' => true, 'disabled' => true]);
            $allowed['textarea'] = array_merge($allowed['textarea'], $global, ['name' => true, 'placeholder' => true, 'rows' => true, 'cols' => true, 'disabled' => true, 'required' => true, 'readonly' => true]);
            $allowed['form'] = array_merge($global, ['method' => true, 'action' => true, 'enctype' => true, 'novalidate' => true]);
            $allowed['input'] = array_merge($global, ['type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'maxlength' => true, 'min' => true, 'max' => true, 'step' => true, 'checked' => true, 'disabled' => true, 'required' => true, 'readonly' => true, 'multiple' => true, 'accept' => true]);
            $allowed['select'] = array_merge($global, ['name' => true, 'multiple' => true, 'disabled' => true, 'required' => true]);
            $allowed['option'] = array_merge($global, ['value' => true, 'selected' => true, 'disabled' => true]);
            $allowed['iframe'] = array_merge($global, ['srcdoc' => true, 'sandbox' => true, 'tabindex' => true, 'width' => true, 'height' => true]);
            return $allowed;
        }

        private static function can_access(): bool
        {
            if ((defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE)) {
                return true;
            }
            return (bool) apply_filters(
                'apcampaign_current_user_can_access',
                current_user_can('edit_posts'),
                get_current_user_id()
            );
        }

        private static function can_upload(): bool
        {
            if ((defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE)) {
                return false;
            }
            return (bool) apply_filters(
                'apcampaign_current_user_can_upload',
                current_user_can('upload_files'),
                get_current_user_id()
            );
        }

        private static function ajax_guard(): void
        {
            if ((defined('APCAMPAIGN_DEMO_MODE') && APCAMPAIGN_DEMO_MODE)) {
                wp_send_json_error(['message' => 'This action is disabled in the public demo.'], 403);
            }
            if (!self::can_access()) {
                wp_send_json_error(['message' => 'Permission denied'], 403);
            }
            $raw_nonce = $_POST['nonce'] ?? '';
            if (!is_string($raw_nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($raw_nonce)), self::NONCE_ACTION)) {
                wp_send_json_error(['message' => 'Security check failed'], 403);
            }
            foreach (['campaign_id', 'job_id', 'recipient_id', 'spam_pass_score', 'preflight_link_limit', 'audience_key', 'composer_mode', 'default_from_email', 'default_from_name', 'description', 'design_json', 'from_email', 'from_name', 'name', 'outcome', 'physical_address', 'preheader_text', 'raw_html', 'rendered_html', 'retarget_owner_only', 'scheduled_at', 'subject_line', 'template_key', 'template_name', 'to'] as $key) {
                if (isset($_POST[$key]) && !is_scalar($_POST[$key])) {
                    wp_send_json_error(['message' => 'Invalid request value.'], 400);
                }
            }
        }

        private static function is_app_request(): bool
        {
            return (string) get_query_var(self::QUERY_VAR) === '1';
        }
    }
}

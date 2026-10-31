<?php
/**
 * apCampaign preflight checks.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Preflight')) {
    final class APCampaign_Preflight
    {
        /**
         * @param array<string,mixed> $campaign
         * @return array<string,mixed>
         */
        public static function run(array $campaign): array
        {
            $failures = [];
            $warnings = [];
            $checks = [];
            $subject = trim((string) ($campaign['subject_line'] ?? ''));
            $from_email = trim((string) ($campaign['from_email'] ?? ''));
            $html = (string) ($campaign['rendered_html'] ?? $campaign['html'] ?? '');

            if ($subject === '') {
                $failures[] = self::record('missing_subject', 'Subject line is required before sending.');
            } else {
                $checks[] = 'subject_present';
            }

            if ($from_email === '' || !is_email($from_email)) {
                $failures[] = self::record('missing_sender', 'A valid sender email is required before sending.');
            } else {
                $checks[] = 'sender_present';
            }

            if (trim(wp_strip_all_tags($html)) === '' && stripos($html, '<img') === false) {
                $failures[] = self::record('empty_html', 'Campaign HTML has no readable content or images.');
            } else {
                $checks[] = 'html_has_content';
            }

            foreach (self::image_url_failures($html) as $failure) {
                $failures[] = $failure;
            }

            foreach (self::content_marker_failures($html) as $failure) {
                $failures[] = $failure;
            }

            $spam = APCampaign_Spam_Score::score($subject, $html, (string) ($campaign['preheader_text'] ?? ''));
            $floor = absint(get_option('apcampaign_spam_floor', 70));
            if ((int) $spam['score'] < $floor) {
                $failures[] = self::record('spam_score_below_floor', 'Spam score is below configured send floor.', ['score' => $spam['score'], 'floor' => $floor]);
            } else {
                $checks[] = 'spam_score_passed';
            }

            // Unsubscribe and tracking URLs use the site's home address. A local
            // test host cannot be reached by subscribers and can raise spam scores.
            $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
            if ($host === 'localhost' || preg_match('/\.(localhost|local|test|invalid)$/', $host) || filter_var($host, FILTER_VALIDATE_IP)) {
                $warnings[] = self::record('nonpublic_tracking_host', 'Your site address appears to be a local, test, or numeric host. Email links and unsubscribe links may be unreachable or treated as spam; test again from your public site.');
            }

            $links = self::extract_links($html);
            $limit = absint(get_option('apcampaign_preflight_link_limit', 25));
            $checked = 0;
            foreach ($links as $link) {
                if ($checked >= $limit) {
                    $warnings[] = self::record('link_check_limit_reached', 'Not every link was checked because the configured limit was reached.', ['limit' => $limit]);
                    break;
                }
                $checked++;
                if (!preg_match('#^https?://#i', $link)) {
                    $failures[] = self::record('invalid_link', 'Link is not an absolute http/https URL.', ['url' => $link]);
                }
            }

            return [
                'success' => empty($failures),
                'status' => empty($failures) ? 'passed' : 'failed',
                'checked' => count($checks) + $checked,
                'checks' => $checks,
                'failures' => $failures,
                'warnings' => $warnings,
                'spam_score' => $spam,
            ];
        }

        /**
         * @return array<int,string>
         */
        private static function extract_links(string $html): array
        {
            if (!preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                return [];
            }
            return array_values(array_unique(array_map(static fn($url): string => html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches[1])));
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function image_url_failures(string $html): array
        {
            $failures = [];
            if (!preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                return [];
            }
            foreach ($matches[1] as $src) {
                $src = (string) $src;
                if (strpos($src, 'blob:') === 0) {
                    $failures[] = self::record('blob_image_url', 'Temporary blob image URL cannot be sent.', ['src' => substr($src, 0, 80)]);
                } elseif (strpos($src, 'data:') === 0) {
                    $failures[] = self::record('data_image_url', 'Inline data image URLs should be uploaded before sending.', ['src' => substr($src, 0, 40)]);
                } elseif (!preg_match('#^https://#i', $src)) {
                    $failures[] = self::record('non_https_image_url', 'Images must use absolute HTTPS URLs.', ['src' => substr($src, 0, 120)]);
                }
            }
            return $failures;
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function content_marker_failures(string $html): array
        {
            $markers = [
                '[apform' => 'Unrendered apForms shortcode marker found.',
                'Fatal error' => 'PHP fatal error marker found in HTML.',
                'Warning:' => 'PHP warning marker found in HTML.',
                'Parse error' => 'PHP parse error marker found in HTML.',
                'Error establishing a database connection' => 'Database error page marker found in HTML.',
            ];
            $failures = [];
            foreach ($markers as $marker => $message) {
                if (stripos($html, $marker) !== false) {
                    $failures[] = self::record('bad_marker', $message, ['marker' => $marker]);
                }
            }
            return $failures;
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        private static function record(string $code, string $message, array $data = []): array
        {
            return ['code' => $code, 'message' => $message, 'data' => $data];
        }
    }
}

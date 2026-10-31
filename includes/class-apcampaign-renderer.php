<?php
/**
 * apCampaign email renderer.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Renderer')) {
    final class APCampaign_Renderer
    {
        /**
         * @param array<string,mixed> $design
         */
        public static function render_from_design(array $design): string
        {
            $blocks = $design['blocks'] ?? [];
            if (!is_array($blocks) || empty($blocks)) {
                return '';
            }

            $html = '';
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $html .= self::render_block((string) ($block['type'] ?? ''), (array) ($block['options'] ?? []));
            }

            return $html;
        }

        /**
         * @param array<string,mixed> $block
         */
        public static function render_editor_block(array $block): string
        {
            $type = sanitize_key((string) ($block['type'] ?? ''));
            $id = sanitize_key((string) ($block['id'] ?? wp_generate_uuid4()));
            $html = self::render_block($type, (array) ($block['options'] ?? []));

            return sprintf(
                '<div class="apcampaign-email-block" data-block-id="%s" data-model-id="%s">%s</div>',
                esc_attr($type),
                esc_attr($id),
                $html
            );
        }

        /**
         * @param array<string,mixed> $options
         */
        public static function render_block(string $type, array $options): string
        {
            switch ($type) {
                case 'heading':
                    return self::heading($options);
                case 'text':
                    return self::text($options);
                case 'image':
                    return self::image($options);
                case 'button':
                    return self::button($options);
                case 'divider':
                    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding:16px 0;"><hr style="border:0;border-top:1px solid #e5e7eb;"></td></tr></table>';
                case 'spacer':
                    $height = max(8, min(120, absint($options['height'] ?? 24)));
                    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="' . esc_attr((string) $height) . '" style="line-height:' . esc_attr((string) $height) . 'px;">&nbsp;</td></tr></table>';
                case 'html':
                    return self::sanitize_email_html((string) ($options['html'] ?? ''));
                default:
                    return '';
            }
        }

        /**
         * @param array<string,mixed> $options
         */
        private static function heading(array $options): string
        {
            $text = (string) ($options['text'] ?? 'Heading');
            $level = min(3, max(1, absint($options['level'] ?? 2)));
            $size = [1 => 32, 2 => 24, 3 => 20][$level];
            $color = self::safe_hex((string) ($options['color'] ?? '#111827'), '#111827');

            return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="font-family:Arial,sans-serif;font-size:' . $size . 'px;line-height:1.25;font-weight:700;color:' . esc_attr($color) . ';padding:8px 0;">' . esc_html($text) . '</td></tr></table>';
        }

        /**
         * @param array<string,mixed> $options
         */
        private static function text(array $options): string
        {
            $text = wp_kses_post((string) ($options['text'] ?? ''));
            $color = self::safe_hex((string) ($options['color'] ?? '#374151'), '#374151');

            return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="font-family:Arial,sans-serif;font-size:16px;line-height:1.55;color:' . esc_attr($color) . ';padding:8px 0;">' . wpautop($text) . '</td></tr></table>';
        }

        /**
         * @param array<string,mixed> $options
         */
        private static function image(array $options): string
        {
            $src = esc_url_raw((string) ($options['src'] ?? ''));
            if ($src === '') {
                return '';
            }
            $alt = esc_attr((string) ($options['alt'] ?? ''));
            $width = max(80, min(1200, absint($options['width'] ?? 600)));

            return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" style="padding:10px 0;"><img src="' . esc_url($src) . '" alt="' . $alt . '" width="' . esc_attr((string) $width) . '" style="display:block;max-width:100%;height:auto;border:0;"></td></tr></table>';
        }

        /**
         * @param array<string,mixed> $options
         */
        private static function button(array $options): string
        {
            $text = (string) ($options['text'] ?? 'Learn More');
            $url = esc_url_raw((string) ($options['url'] ?? '#'));
            $bg = self::safe_hex((string) ($options['background'] ?? '#2563eb'), '#2563eb');
            $color = self::safe_hex((string) ($options['color'] ?? '#ffffff'), '#ffffff');

            return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"><tr><td bgcolor="' . esc_attr($bg) . '" style="border-radius:4px;"><a href="' . esc_url($url) . '" style="display:inline-block;padding:12px 20px;font-family:Arial,sans-serif;font-size:16px;font-weight:700;color:' . esc_attr($color) . ';text-decoration:none;">' . esc_html($text) . '</a></td></tr></table>';
        }

        public static function wrap_document(string $body, string $subject = ''): string
        {
            if (stripos($body, '<html') !== false || stripos($body, '<!doctype') !== false) {
                return $body;
            }

            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($subject) . '</title></head><body style="margin:0;padding:0;background:#f3f4f6;"><center><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" style="padding:24px 12px;"><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;"><tr><td style="padding:24px;">' . $body . '</td></tr></table></td></tr></table></center></body></html>';
        }

        public static function sanitize_email_html(string $html): string
        {
            $allowed = wp_kses_allowed_html('post');
            $allowed['table'] = ['role' => true, 'width' => true, 'cellspacing' => true, 'cellpadding' => true, 'border' => true, 'style' => true, 'align' => true];
            $allowed['tr'] = ['style' => true];
            $allowed['td'] = ['style' => true, 'align' => true, 'width' => true, 'height' => true, 'bgcolor' => true];
            $allowed['center'] = ['style' => true];
            $allowed['img']['width'] = true;
            $allowed['img']['height'] = true;
            $allowed['a']['target'] = true;
            return wp_kses($html, $allowed);
        }

        private static function safe_hex(string $value, string $fallback): string
        {
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
        }
    }
}

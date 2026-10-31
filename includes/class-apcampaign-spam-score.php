<?php
/**
 * apCampaign spam scoring.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Spam_Score')) {
    final class APCampaign_Spam_Score
    {
        /**
         * @return array<string,mixed>
         */
        public static function score(string $subject, string $html = '', string $preheader = ''): array
        {
            $score = 100;
            $issues = [];
            $passes = [];
            $subject = trim($subject);
            $html = (string) $html;

            if ($subject === '') {
                $score -= 25;
                $issues[] = self::issue('missing_subject', 'Missing Subject Line', 'Emails without subject lines are almost always filtered or ignored.', 'high', 25, 'Add a clear subject line.');
            } else {
                $length = strlen($subject);
                if ($length > 60) {
                    $score -= 5;
                    $issues[] = self::issue('subject_too_long', 'Subject Line Too Long', 'Keep subject lines under about 50-60 characters for better inbox display.', 'low', 5, 'Shorten the subject line.');
                } elseif ($length < 10) {
                    $score -= 5;
                    $issues[] = self::issue('subject_too_short', 'Subject Line Too Short', 'Very short subjects often look unfinished or suspicious.', 'medium', 5, 'Add specific value or context.');
                } else {
                    $passes[] = 'Subject line length is reasonable.';
                }

                $letters = preg_replace('/[^a-zA-Z]/', '', $subject);
                $caps = preg_replace('/[^A-Z]/', '', $subject);
                if ($letters !== '' && strlen($caps) / max(1, strlen($letters)) > 0.5 && strlen($subject) > 5) {
                    $score -= 15;
                    $issues[] = self::issue('excessive_caps', 'Excessive Capitalization', 'ALL CAPS subject lines are a strong spam and trust risk.', 'high', 15, 'Use normal title or sentence capitalization.', ['autofix' => ucwords(strtolower($subject))]);
                }

                if (preg_match('/[!?]{2,}/', $subject)) {
                    $score -= 10;
                    $issues[] = self::issue('excessive_punctuation', 'Excessive Punctuation', 'Repeated punctuation can look spammy.', 'medium', 10, 'Use one punctuation mark.');
                }

                $spam_words = self::spam_words();
                $found = [];
                $penalty = 0;
                $lower = strtolower($subject);
                foreach ($spam_words as $word => $weight) {
                    if (strpos($lower, $word) !== false) {
                        $found[] = $word;
                        $penalty += $weight;
                    }
                }
                if ($found) {
                    $penalty = min($penalty, 25);
                    $score -= $penalty;
                    $issues[] = self::issue('subject_trigger_words', 'Spam Trigger Words in Subject', 'Found: "' . implode('", "', array_slice($found, 0, 5)) . '".', $penalty > 15 ? 'high' : 'medium', $penalty, 'Replace trigger words with specific, audience-relevant language.');
                } else {
                    $passes[] = 'No obvious spam trigger words in subject.';
                }

                if (preg_match('/\$\d+/', $subject)) {
                    $score -= 8;
                    $issues[] = self::issue('money_in_subject', 'Dollar Amount in Subject', 'Money references in subject lines can increase filtering risk.', 'medium', 8, 'Move pricing into the body where possible.');
                }
            }

            $plain = trim(wp_strip_all_tags($html));
            if ($html === '' && $preheader === '') {
                $score -= 30;
                $issues[] = self::issue('no_content', 'No Email Content', 'Campaigns need content before sending.', 'high', 30, 'Add body content.');
            } elseif ($html !== '') {
                $image_count = preg_match_all('/<img\b/i', $html);
                if (strlen($plain) < 50 && $image_count > 0) {
                    $score -= 20;
                    $issues[] = self::issue('image_heavy', 'Image-Heavy Email', 'Image-only campaigns are more likely to be blocked and less accessible.', 'high', 20, 'Add real text content alongside images.');
                } elseif (strlen($plain) > 100) {
                    $passes[] = 'Text-to-image balance looks usable.';
                }

                $link_count = preg_match_all('/<a\s+[^>]*href=/i', $html);
                $social_count = preg_match_all('/href=["\'][^"\']*(facebook|instagram|youtube|linkedin|pinterest|tiktok|x\.com|twitter)/i', $html);
                $meaningful_links = max(0, $link_count - $social_count);
                if ($meaningful_links > 15) {
                    $score -= 10;
                    $issues[] = self::issue('too_many_links', 'Too Many Links', "Found {$meaningful_links} content links.", 'medium', 10, 'Consolidate around the most important calls to action.');
                } elseif ($meaningful_links > 0) {
                    $passes[] = 'Link count is reasonable.';
                }

                if (preg_match('/<img[^>]+src\s*=\s*["\']["\']/', $html)) {
                    $score -= 5;
                    $issues[] = self::issue('empty_image_src', 'Missing Image Sources', 'Some image tags have empty source attributes.', 'low', 5, 'Upload or remove placeholder images.');
                }

                if (preg_match('/href=["\'][^"\']*\d{1,3}(\.\d{1,3}){3}/', $html)) {
                    $score -= 15;
                    $issues[] = self::issue('ip_links', 'IP Address in Links', 'Links to raw IP addresses are a major trust risk.', 'high', 15, 'Use verified domain names.');
                }

                foreach (['bit.ly', 'goo.gl', 'tinyurl', 't.co', 'ow.ly', 'is.gd'] as $shortener) {
                    if (stripos($html, $shortener) !== false) {
                        $score -= 10;
                        $issues[] = self::issue('url_shortener', 'URL Shortener Detected', "Found {$shortener}.", 'medium', 10, 'Use full destination URLs.');
                        break;
                    }
                }

                $passes[] = 'Compliance footer can be injected at send time.';
            }

            $score = max(0, min(100, $score));

            return [
                'score' => $score,
                'grade' => self::grade($score),
                'issues' => $issues,
                'passes' => $passes,
            ];
        }

        /**
         * @return array<string,int>
         */
        private static function spam_words(): array
        {
            return [
                'free' => 8,
                'act now' => 10,
                'limited time' => 8,
                'urgent' => 8,
                'winner' => 10,
                'congratulations' => 10,
                'click here' => 8,
                'buy now' => 8,
                'order now' => 8,
                'call now' => 8,
                'guarantee' => 5,
                'risk free' => 8,
                'special offer' => 6,
                'cash' => 6,
                'earn money' => 10,
                'make money' => 10,
                'casino' => 15,
                'lottery' => 15,
                '100% free' => 15,
                'dear friend' => 10,
                'while supplies last' => 8,
            ];
        }

        /**
         * @param array<string,mixed> $extra
         * @return array<string,mixed>
         */
        private static function issue(string $code, string $title, string $description, string $severity, int $penalty, string $fix, array $extra = []): array
        {
            return array_merge([
                'code' => $code,
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'penalty' => $penalty,
                'fix' => $fix,
            ], $extra);
        }

        private static function grade(int $score): string
        {
            if ($score >= 90) {
                return 'A';
            }
            if ($score >= 80) {
                return 'B';
            }
            if ($score >= 70) {
                return 'C';
            }
            if ($score >= 60) {
                return 'D';
            }
            return 'F';
        }
    }
}

<?php

namespace App\Support;

/**
 * Besuchszähler ohne Cookies (habitat-feedback-betrieb#8): zählt Aufrufe der HTML-Seiten als Summe je Tag,
 * Seite und Herkunft (Tabelle besuche_tag, sql/028). Gespeichert wird nichts über die Person — keine IP,
 * kein Cookie, kein Hash, nur der Hostname der verweisenden Seite in groben Gruppen.
 */
final class Besuche
{
    // Teilstring im User-Agent => Name. Reihenfolge zählt: der erste Treffer gewinnt.
    private const BOTS = [
        'googlebot' => 'google', 'google-inspectiontool' => 'google', 'adsbot-google' => 'google',
        'chrome-lighthouse' => 'lighthouse', 'bingbot' => 'bing', 'applebot' => 'apple', 'duckduckbot' => 'duckduckgo',
        'yandex' => 'yandex', 'baiduspider' => 'baidu', 'petalbot' => 'petal', 'ahrefsbot' => 'ahrefs',
        'semrushbot' => 'semrush', 'mj12bot' => 'majestic', 'dotbot' => 'moz', 'gptbot' => 'openai',
        'chatgpt' => 'openai', 'claudebot' => 'anthropic', 'claude-user' => 'anthropic', 'perplexity' => 'perplexity',
        'ccbot' => 'commoncrawl', 'bytespider' => 'bytedance', 'facebookexternalhit' => 'vorschau',
        'whatsapp' => 'vorschau', 'telegrambot' => 'vorschau', 'discordbot' => 'vorschau', 'slackbot' => 'vorschau',
        'twitterbot' => 'vorschau', 'linkedinbot' => 'vorschau', 'curl' => 'werkzeug', 'wget' => 'werkzeug',
        'python' => 'werkzeug', 'go-http-client' => 'werkzeug', 'java/' => 'werkzeug', 'okhttp' => 'werkzeug',
        'headless' => 'werkzeug', 'uptime' => 'monitor', 'monitor' => 'monitor',
    ];

    // Hostname der verweisenden Seite => Gruppe. Endet der Schlüssel auf einen Punkt, reicht ein Teilstring
    // (google.de, news.google.com, android-app://com.google...), sonst muss der Host genau passen (t.co, x.com).
    private const HERKUNFT = [
        'google.' => 'google', 'bing.' => 'bing', 'duckduckgo.' => 'duckduckgo', 'ecosia.' => 'ecosia',
        'yahoo.' => 'yahoo', 'qwant.' => 'qwant', 'startpage.' => 'startpage', 'facebook.' => 'facebook',
        'instagram.' => 'instagram', 'reddit.' => 'reddit', 'linkedin.' => 'linkedin', 't.co' => 'twitter',
        'x.com' => 'twitter', 'chatgpt.' => 'chatgpt', 'perplexity.' => 'perplexity',
    ];

    public static function zaehlen(string $pfad): void
    {
        try {
            $tag = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            Db::get()->prepare(
                'INSERT INTO besuche_tag (tag, pfad, herkunft, aufrufe) VALUES (:tag, :pfad, :herkunft, 1)
                 ON DUPLICATE KEY UPDATE aufrufe = aufrufe + 1'
            )->execute(['tag' => $tag, 'pfad' => substr($pfad, 0, 64),
                        'herkunft' => self::herkunft($_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['HTTP_REFERER'] ?? '')]);
        } catch (\Throwable) {
            // Zählen darf die Seite nie stören (fehlende Tabelle, DB weg): still weiter.
        }
    }

    public static function herkunft(string $userAgent, string $referer): string
    {
        $ua = strtolower($userAgent);
        if ($ua === '') {
            return 'bot:leer';
        }
        foreach (self::BOTS as $teil => $name) {
            if (str_contains($ua, $teil)) {
                return 'bot:' . $name;
            }
        }
        if (preg_match('/bot\b|crawl|spider|slurp|scan/', $ua)) {
            return 'bot:sonstige';
        }
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return 'direkt';
        }
        $host = preg_replace('/^www\./', '', $host);
        if ($host === 'habitat-feedback.de' || $host === strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''))) {
            return 'intern';
        }
        foreach (self::HERKUNFT as $teil => $gruppe) {
            if ($host === $teil || (str_ends_with($teil, '.') && str_contains($host, $teil))) {
                return $gruppe;
            }
        }
        return substr($host, 0, 64);
    }
}

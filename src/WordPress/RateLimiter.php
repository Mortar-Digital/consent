<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

/**
 * Fixed-window limiter: a per-client limit plus a site-wide ceiling that
 * protects the database if per-client keys can be rotated. Clients are keyed
 * by a keyed hash of their address (IPv6 by /64, since one host usually owns
 * the whole prefix), so no address is ever written to the database.
 *
 * With a persistent object cache, counters use atomic increments; otherwise
 * they fall back to transients, which can overshoot slightly under
 * concurrent requests.
 */
final class RateLimiter
{
    private const GROUP = 'mortar_consent_rl';

    public function __construct(
        private readonly string $prefix,
        private readonly int $perClient,
        private readonly int $global,
        private readonly int $windowSeconds,
    ) {
    }

    public function attempt(string $client): bool
    {
        $window = intdiv(time(), $this->windowSeconds);
        $clientKey = $this->prefix . substr(hash_hmac('sha256', self::bucket($client) . '|' . $window, wp_salt('nonce')), 0, 32);
        $globalKey = $this->prefix . 'all_' . $window;

        // Per client first, so one noisy client cannot use up the site-wide
        // budget and lock everyone else out of logging.
        if ($this->increment($clientKey) > $this->perClient) {
            return false;
        }

        return $this->increment($globalKey) <= $this->global;
    }

    /** Collapses IPv6 addresses to their /64 prefix; IPv4 is used as is. */
    public static function bucket(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }
        $packed = inet_pton($ip);

        return $packed === false ? $ip : bin2hex(substr($packed, 0, 8)) . '::/64';
    }

    /**
     * The address the web server saw. Behind a proxy, configure the server's
     * real-IP module (the Helm chart's nginx.trustedProxies) rather than
     * trusting forwarded headers here.
     */
    public static function clientIp(): string
    {
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
        $filtered = apply_filters('mortar_consent_client_ip', filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown');

        return is_string($filtered) && $filtered !== '' ? $filtered : 'unknown';
    }

    private function increment(string $key): int
    {
        if (wp_using_ext_object_cache()) {
            wp_cache_add($key, 0, self::GROUP, $this->windowSeconds);
            $count = wp_cache_incr($key, 1, self::GROUP);

            return $count === false ? PHP_INT_MAX : $count;
        }
        $stored = get_transient($key);
        $count = (is_numeric($stored) ? (int) $stored : 0) + 1;
        set_transient($key, $count, $this->windowSeconds);

        return $count;
    }
}

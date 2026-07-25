<?php

namespace App;

/**
 * Anonymizes IP addresses to protect user privacy
 */
class IpAnonymizer
{
    /**
     * Anonymize an IPv4 address by masking the last octet
     * Example: 192.168.1.100 -> 192.168.1.xxx
     */
    public static function anonymizeIpv4(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }
        return $ip;
    }

    /**
     * Anonymize an IPv6 address by masking the last 64 bits
     */
    public static function anonymizeIpv6(string $ip): string
    {
        $parts = explode(':', $ip);
        if (count($parts) >= 4) {
            // Mask last 4 groups
            for ($i = max(0, count($parts) - 4); $i < count($parts); $i++) {
                $parts[$i] = 'xxxx';
            }
            return implode(':', $parts);
        }
        return $ip;
    }

    /**
     * Detect IP version and anonymize accordingly
     */
    public static function anonymize(string $ip): string
    {
        if (strpos($ip, ':') !== false) {
            return self::anonymizeIpv6($ip);
        }
        return self::anonymizeIpv4($ip);
    }
}

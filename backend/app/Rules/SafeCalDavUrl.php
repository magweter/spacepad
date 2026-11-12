<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A CalDAV server address the hosted service may open a connection to.
 *
 * The URL is the one place a customer makes our server connect to an address of their
 * choosing, so on the hosted service it has to be a public host over https: nothing on
 * our own network, no loopback, no link-local (that is where cloud instance metadata
 * lives), and no credentials smuggled into the URL. The host is resolved here, so a name
 * that points at a private address is refused as well.
 *
 * Self-hosted installs talk to calendar servers on their own LAN all the time, so there
 * the rule only asks for a well-formed http(s) URL.
 */
class SafeCalDavUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($parts = parse_url($value)) === false) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must be an http or https URL.');

            return;
        }

        if (config('settings.is_self_hosted')) {
            return;
        }

        if ($scheme !== 'https') {
            $fail('The :attribute must use https.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('Put the user name and password in their own fields, not in the :attribute.');

            return;
        }

        foreach ($this->resolve($host) as $ip) {
            if (! self::isPublicAddress($ip)) {
                $fail('The :attribute must point at a publicly reachable calendar server.');

                return;
            }
        }
    }

    /**
     * Every address the host stands for. An IP literal is its own answer; a name that
     * does not resolve is reported as unreachable rather than let through unchecked.
     *
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        $addresses = array_values(array_filter($addresses));

        // Some resolvers answer gethostbyname but not dns_get_record for /etc/hosts entries.
        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        // Unresolvable is treated as a private address: refused, never guessed at.
        return $addresses === [] ? ['127.0.0.1'] : $addresses;
    }

    public static function isPublicAddress(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}

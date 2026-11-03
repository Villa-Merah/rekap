<?php

namespace App\Services;

class OmniClient
{
    public static function ready(): bool
    {
        $client = config('omni-central-auth.client');
        if (! config('omni-central-auth.enabled') || ! is_array($client)) {
            return false;
        }
        foreach (['server_url', 'client_id', 'client_secret', 'redirect_uri', 'signing_key'] as $field) {
            if (! is_string($client[$field] ?? null) || trim($client[$field]) === '') {
                return false;
            }
        }

        return filter_var($client['server_url'], FILTER_VALIDATE_URL) !== false
            && parse_url($client['server_url'], PHP_URL_SCHEME) === 'https'
            && filter_var($client['redirect_uri'], FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($client['redirect_uri'], PHP_URL_SCHEME), ['https', 'http'], true);
    }

    public static function serverOrigin(): string
    {
        $url = config('omni-central-auth.client.server_url');
        $port = parse_url($url, PHP_URL_PORT);

        return 'https://'.parse_url($url, PHP_URL_HOST).($port && $port !== 443 ? ':'.$port : '');
    }
}

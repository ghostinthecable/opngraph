<?php
/**
 * OPNsense API Configuration;
 * Set your OPNsense host, API key, and API secret here.
 * The API key+secret are generated in OPNsense under System > Access > Users > [user] > API keys.
 */

return [
    // OPNsense host — use HTTPS, no trailing slash
    'host' => 'https://{opnsense_ip_or_fqdn}',

    // API key (acts as the username for Basic Auth)
    'api_key' => '{key_here}',

    // API secret (acts as the password for Basic Auth)
    'api_secret' => '{secret_here}',

    // Skip SSL verification (set true only if using self-signed certs)
    'verify_ssl' => false,

    // How many log lines to fetch per poll (max 2000)
    'log_limit' => 500,

    // Poll interval in milliseconds for the frontend
    'poll_interval' => 5000,
];

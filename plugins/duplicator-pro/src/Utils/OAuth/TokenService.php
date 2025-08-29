<?php

namespace Duplicator\Utils\OAuth;

use Duplicator\Utils\ExpireOptions;
use Duplicator\Utils\OAuth\TokenEntity;

/**
 * This class is responsible for handling communication with the OAuth2 servers.
 */
class TokenService
{
    const BACKOFF_KEY = 'oauth_backoff_';

    /** @var int Storage type identifier */
    protected int $storage_type;

    /**
     * Create a new instance of the service.
     *
     * @param int $storage_type Storage type identifier
     */
    public function __construct($storage_type)
    {
        $this->storage_type = (int) $storage_type;
    }

    /**
     * Get a list of servers capable of handling the OAuth2 requests.
     *
     * @return string[]
     */
    private static function getServerCandidates(): array
    {
        return [
            DUPLICATOR_PRO_PRIMARY_OAUTH_SERVER,
            DUPLICATOR_PRO_SECONDARY_OAUTH_SERVER,
        ];
    }

    /**
     * Get the server to use for OAuth2 requests.
     *
     * @param bool $check Should we check if the server is online.
     *
     * @return string
     */
    private static function getServer(bool $check = false)
    {
        $candidates = self::getServerCandidates();

        if (! $check) {
            return $candidates[0];
        }

        foreach ($candidates as $candidate) {
            if (self::checkServer($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Check if the server is online.
     *
     * @param string $server Server URL.
     *
     * @return bool
     */
    private static function checkServer($server): bool
    {
        if (self::shouldBackOff($server)) {
            return false;
        }

        $url = sprintf('%s/check', $server);

        $response = wp_remote_get($url, ['timeout' => 5]);
        self::maybeBackoffNextTime($server, $response);
        if (is_wp_error($response)) {
            return false;
        }

        return true;
    }

    /**
     * Get the redirect uri for the current provider.
     *
     * @return string
     */
    public function getRedirectUri(): string
    {
        return sprintf('%s/oauth/%s/connect', self::getServer(), $this->storage_type);
    }

    /**
     * Refresh the token from the server.
     *
     * @param TokenEntity $token The token entity to be refreshed.
     *
     * @return void
     * @throws \Exception
     */
    public function refreshToken(TokenEntity $token): void
    {
        // We must check if the server is live before we try to refresh the token.
        $server = self::getServer(true);
        if (empty($server)) {
            throw new \Exception('No server is available to refresh token, please try again later.');
        }
        $url = sprintf('%s/oauth/%s/refresh', $server, $this->storage_type);

        $response = wp_remote_post($url, [
            'timeout' => 5,
            'body'    => [
                'refresh_token' => $token->getRefreshToken(),
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return;
        }

        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $message = "Failed to refresh token with error: {$data['error']}";
            if (isset($data['error_description'])) {
                $message .= " - {$data['error_description']}";
            }
            throw new \Exception($message);
        }

        $token->updateProperties($data);
    }

    /**
     * Record a backoff for the next time if needed.
     *
     * @param string                         $server   Server URL.
     * @param array<string, mixed>|\WP_Error $response The response from the server.
     *
     * @return void
     */
    private static function maybeBackoffNextTime($server, $response): void
    {
        $code = (int) wp_remote_retrieve_response_code($response);

        // If the response code is 0, 429 or between 500 and 599 we should backoff.
        if ($code === 0 || $code === 429 || ($code >= 500 && $code <= 599)) {
            ExpireOptions::set(self::BACKOFF_KEY . $server, true, MINUTE_IN_SECONDS);
        }
    }

    /**
     * Check if we have to stop trying to connect to the server.
     *
     * @param string $server Server URL.
     *
     * @return bool
     */
    private static function shouldBackOff($server): bool
    {
        return (bool) ExpireOptions::get(self::BACKOFF_KEY . $server, false);
    }
}

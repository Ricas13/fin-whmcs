<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Discord;

use CaptainFin\Whmcs\Integrations\Http\HttpException;
use CaptainFin\Whmcs\Integrations\Http\JsonHttpClient;
use CaptainFin\Whmcs\Integrations\IntegrationResult;

final class DiscordRoleAdapter
{
    private JsonHttpClient $http;

    public function __construct(string $botToken, string $apiBase = 'https://discord.com/api/v10')
    {
        $botToken = trim($botToken);
        if ($botToken === '' || preg_match('/[\r\n]/', $botToken)) {
            throw new \InvalidArgumentException('Discord bot token is required.');
        }
        $this->http = new JsonHttpClient($apiBase, [
            'Authorization' => 'Bot ' . $botToken,
            'Accept' => 'application/json',
        ]);
    }

    public function observe(string $guildId, string $userId, string $roleId): array
    {
        self::snowflake($guildId, 'guild');
        self::snowflake($userId, 'user');
        self::snowflake($roleId, 'role');

        try {
            $member = $this->http->request('/guilds/' . $guildId . '/members/' . $userId);
        } catch (HttpException $error) {
            if ($error->status() === 404) {
                return ['member_exists' => false, 'has_role' => false];
            }
            throw $error;
        }

        if (!is_array($member)) {
            throw new \RuntimeException('Discord returned an invalid guild member response.');
        }
        $roles = array_map('strval', is_array($member['roles'] ?? null) ? $member['roles'] : []);
        return ['member_exists' => true, 'has_role' => in_array($roleId, $roles, true), 'roles' => $roles];
    }

    public function ensureRole(string $guildId, string $userId, string $roleId): array
    {
        $observed = $this->observe($guildId, $userId, $roleId);
        if (!empty($observed['has_role'])) {
            return IntegrationResult::unchanged($observed);
        }
        if (empty($observed['member_exists'])) {
            throw new \RuntimeException('Discord user is not a member of the configured guild.');
        }

        $this->http->request('/guilds/' . $guildId . '/members/' . $userId . '/roles/' . $roleId, 'PUT');
        $after = $this->observe($guildId, $userId, $roleId);
        if (empty($after['has_role'])) {
            throw new \RuntimeException('Discord role grant returned without observed convergence.');
        }
        return IntegrationResult::changed($after);
    }

    public function removeRole(string $guildId, string $userId, string $roleId): array
    {
        $observed = $this->observe($guildId, $userId, $roleId);
        if (empty($observed['member_exists']) || empty($observed['has_role'])) {
            return IntegrationResult::unchanged($observed);
        }

        $this->http->request('/guilds/' . $guildId . '/members/' . $userId . '/roles/' . $roleId, 'DELETE');
        $after = $this->observe($guildId, $userId, $roleId);
        if (!empty($after['has_role'])) {
            throw new \RuntimeException('Discord role removal returned without observed convergence.');
        }
        return IntegrationResult::changed($after);
    }

    private static function snowflake(string $value, string $label): void
    {
        if (!preg_match('/^\d{5,30}$/', $value)) {
            throw new \InvalidArgumentException('Invalid Discord ' . $label . ' ID.');
        }
    }
}

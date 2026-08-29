<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Infrastructure\Database;

use CaptainFin\Whmcs\Policy\ProductPolicy;
use DateTimeImmutable;
use WHMCS\Database\Capsule;

final class ProductPolicyRepository
{
    private const TABLE = 'mod_captainfin_product_policies';

    public function upsertFromWhmcsParams(array $params): array
    {
        $productId = (int) ($params['pid'] ?? 0);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('WHMCS product id is required for policy persistence.');
        }

        $policy = ProductPolicy::fromWhmcsParams($params);
        $json = json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode CAPTAiNFiN product policy.');
        }
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('product_id', $productId)->first();

        if ($existing === null) {
            Capsule::table(self::TABLE)->insert([
                'product_id' => $productId,
                'version' => 1,
                'policy_json' => $json,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ((string) $existing->policy_json !== $json) {
            Capsule::table(self::TABLE)->where('product_id', $productId)->update([
                'version' => (int) $existing->version + 1,
                'policy_json' => $json,
                'updated_at' => $now,
            ]);
        }

        return $policy;
    }

    public function get(int $productId): ?array
    {
        $row = Capsule::table(self::TABLE)->where('product_id', $productId)->first();
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row->policy_json, true);
        return is_array($decoded) ? $decoded : null;
    }
}

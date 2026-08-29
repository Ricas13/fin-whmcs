<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Diagnostics;

use WHMCS\Database\Capsule;

final class OperationDiagnosticsRepository
{
    private const TABLE = 'mod_captainfin_operations';

    public function summary(): array
    {
        $rows = Capsule::table(self::TABLE)
            ->select('state', Capsule::raw('COUNT(*) AS total'))
            ->groupBy('state')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->state] = (int) $row->total;
        }

        return [
            'counts' => $counts,
            'unresolved' => array_sum(array_intersect_key($counts, array_flip(['planned', 'remote_applied', 'failed']))),
            'manual_attention' => (int) ($counts['manual_attention'] ?? 0),
        ];
    }

    /** @return object[] */
    public function recentProblems(int $limit = 50): array
    {
        return Capsule::table(self::TABLE)
            ->whereIn('state', ['planned', 'remote_applied', 'failed', 'manual_attention'])
            ->orderByDesc('updated_at')
            ->limit(max(1, min(200, $limit)))
            ->get([
                'id', 'service_id', 'operation_type', 'state', 'attempts', 'last_error',
                'retry_after', 'remote_ref', 'updated_at',
            ])
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations;

interface RemoteIdentityAdapter
{
    /** @return array<string,mixed>|null */
    public function observe(?string $remoteId, array $identityHints = []): ?array;

    /**
     * Converge one external identity to the supplied desired state.
     * Implementations must return the observed remote state after convergence.
     *
     * @return array<string,mixed>
     */
    public function ensure(array $desired, ?string $remoteId = null): array;

    /**
     * Remove/disable entitlement. A successful return means the adapter has
     * observed the expected removed/disabled state, not merely sent a request.
     */
    public function remove(?string $remoteId, array $identityHints = []): void;
}

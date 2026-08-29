<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Stremio;

use CaptainFin\Whmcs\Integrations\RemoteIdentityAdapter;

/**
 * Concrete implementation is intentionally separated from the lifecycle so
 * the application-specific Stremio backend can be ported from fin-fusion
 * without teaching WHMCS lifecycle code about its credential/storage details.
 */
interface StremioAccessAdapter extends RemoteIdentityAdapter
{
    public function changePassword(string $remoteId, string $newPassword): void;
}

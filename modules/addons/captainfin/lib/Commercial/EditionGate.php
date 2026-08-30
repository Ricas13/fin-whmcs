<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Commercial;

final class EditionGate
{
    private Edition $edition;

    public function __construct(?Edition $edition = null)
    {
        $this->edition = $edition ?? Edition::current();
    }

    public function edition(): Edition
    {
        return $this->edition;
    }

    public function assertProviderAllowed(string $provider): void
    {
        if ($this->edition->allowsProvider($provider)) {
            return;
        }

        throw new EditionException(sprintf(
            '%s does not include %s support. Install or license the matching CAPTAiNFiN edition or Media Suite.',
            $this->edition->displayName(),
            ucfirst($provider)
        ));
    }

    public function assertLifecycleAllowed(string $operationType, string $provider): void
    {
        if ($this->edition->allowsProvider($provider)) {
            return;
        }

        // Safety operations must remain possible after an edition downgrade or
        // licence change so remote access can always be suspended or removed.
        if (in_array($operationType, ['suspend', 'terminate'], true)) {
            return;
        }

        $this->assertProviderAllowed($provider);
    }
}

<?php

declare(strict_types=1);

// This file intentionally starts tiny. Concrete WHMCS-owned primitives are
// added only when a runtime test needs them so the harness never becomes a
// second implementation of WHMCS.

if (!defined('WHMCS')) {
    define('WHMCS', true);
}

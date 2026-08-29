<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Policy;

use CaptainFin\Whmcs\Policy\LibraryPolicy;
use PHPUnit\Framework\TestCase;

final class LibraryPolicyTest extends TestCase
{
    public function testIncludeExcludeAndAdminOverride(): void
    {
        $catalog = ['Movies', 'TV Shows', 'Kids'];

        $include = LibraryPolicy::entitlement($catalog, 'include', ['Movies']);
        self::assertSame(['Movies'], LibraryPolicy::visible($include, null));

        $exclude = LibraryPolicy::entitlement($catalog, 'exclude', ['Kids']);
        self::assertSame(['Movies', 'TV Shows'], LibraryPolicy::visible($exclude, null));

        $override = LibraryPolicy::entitlement($catalog, 'include', ['Movies'], ['TV Shows' => true, 'Movies' => false]);
        self::assertSame(['TV Shows'], LibraryPolicy::visible($override, null));
    }

    public function testCustomerSelectionOnlyNarrowsEntitlement(): void
    {
        $rows = LibraryPolicy::entitlement(['Movies', 'TV Shows', 'Admin'], 'include', ['Movies', 'TV Shows']);
        $visible = LibraryPolicy::visible($rows, ['TV Shows', 'Admin']);

        self::assertSame(['TV Shows'], $visible);
    }

    public function testMissingServerLibraryNarrowsAndReportsInsteadOfGrantingEverything(): void
    {
        $resolved = LibraryPolicy::resolve(
            ['Movies', 'Kids'],
            [['id' => 'm1', 'name' => 'Movies'], ['id' => 't1', 'name' => 'TV Shows']]
        );

        self::assertSame(['m1'], $resolved['enabledFolders']);
        self::assertSame(['Kids'], $resolved['missing']);
    }
}

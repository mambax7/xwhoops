<?php

declare(strict_types=1);

namespace Xmf\Module\Helper;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Test stand-in for Xmf\Module\Helper\Permission.
 *
 * The provider consults this before registering Whoops. The real one needs a booted XOOPS
 * and a database; the contract answers a permission question, not a storage one, so a
 * fixture whose verdict a test can set is enough -- and keeps the gate runnable in CI.
 */
if (! class_exists(Permission::class, false)) {
    class Permission
    {
        public function __construct(string $dirname = '')
        {
        }

        /**
         * Verdict comes from a global a test sets, so the test never has to name -- and
         * static-analysis never has to resolve -- this shadowing class. Defaults to
         * granted, which is the interesting (registration) path.
         */
        public function checkPermission(string $name, int $itemId = 0, bool $trueForAdmin = true): bool
        {
            return (bool) ($GLOBALS['__xwhoops_test_permission_granted'] ?? true);
        }
    }
}

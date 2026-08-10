<?php

declare(strict_types=1);

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Minimal stand-in for the core XoopsPreloadItem class.
 *
 * The module's preload only needs a parent to exist; a real one would drag in a XOOPS
 * boot. Declared in a file rather than via eval() so the contract test carries no eval()
 * -- which a security scanner flags, and which a fixture never needs.
 */
if (! class_exists('\XoopsPreloadItem', false)) {
    class XoopsPreloadItem
    {
    }
}

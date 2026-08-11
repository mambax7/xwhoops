<?php declare(strict_types=1);

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

use Xmf\Module\Admin;
use Xmf\Request;

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    Richard Griffith <richard@geekwright.com>
 */
require __DIR__ . '/admin_header.php';

$moduleAdmin = Admin::getInstance();

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (! file_exists($autoloader)) {
    $moduleAdmin->addConfigWarning(_MI_XWHOOPS_NEEDS_COMPOSER);
}


// example - bounces around and into an error
// will show xWhoops25 page if user has permission
$op = Request::getString('do');
if ('example' === $op) {
    if (! \defined('XOOPS_DEBUG') || ! XOOPS_DEBUG) {
        redirect_header(
            'index.php',
            3,
            \defined('_MA_XWHOOPS_DEBUG_DISABLED') ? _MA_XWHOOPS_DEBUG_DISABLED : 'XOOPS debug is disabled.'
        );
        exit;
    }

    require_once __DIR__ . '/ExampleClass.php';
    number1();
}

function number1(): void
{
    number3('test message');
}

function number2(ExampleClass $ec): void
{
    // Not assigned: flawedMethod() returns void and never returns at all -- it throws,
    // which is the entire point of this call chain.
    $ec->flawedMethod();
}

function number3(string $msg): void
{
    number2(new ExampleClass($msg));
}

$moduleAdmin->displayNavigation('index.php');
$moduleAdmin->displayIndex();

require __DIR__ . '/admin_footer.php';

<?php declare(strict_types=1);
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$modversion = [];

$modversion['version']       = '2.0.0-Beta2';
$modversion['module_status'] = 'Beta2';
$modversion['status']        = 'Beta2';
$modversion['release_date']  = '2026/08/08';
$modversion['name']          = _MI_XWHOOPS_NAME;
$modversion['description']   = _MI_XWHOOPS_DESCRIPTION;
$modversion['author']        = 'Richard Griffith';
$modversion['nickname']      = 'geekwright';
$modversion['credits']       = 'The XOOPS Project';
$modversion['dirname']       = basename(__DIR__);
$modversion['website']       = 'https://github.com/XoopsModules27x/xwhoops';
$modversion['license']       = 'GNU GPL 2 or later';
$modversion['license_url']   = 'https://www.gnu.org/licenses/gpl-2.0.html';
$modversion['official']      = 0;
$modversion['image']         = 'assets/images/logoModule.png';
$modversion['min_xoops']     = '2.7.0';
$modversion['min_php']       = '8.2.0';
$modversion['hasMain']       = false;

// Error-screen ownership for the XOOPS 2.7.3 provider seam. Answering
// core.debug.errorscreen is only half of being a provider: core offers the seat to one
// declared owner, and a module that never claims it is never offered it. No-ops on a
// core without the seam.
$modversion['onInstall']   = 'include/errorscreen.php';
$modversion['onUpdate']    = 'include/errorscreen.php';
$modversion['onUninstall'] = 'include/errorscreen.php';

// Admin things
$modversion['hasAdmin']    = true;
$modversion['system_menu'] = true;
$modversion['adminindex']  = 'admin/index.php';
$modversion['adminmenu']   = 'admin/menu.php';

// Manage extension
$modversion['extension']          = true;
$modversion['extension_module'][] = 'system';

// ------------------- Help files ------------------- //
$modversion['help']        = 'page=help';
$modversion['helpsection'] = [
    ['name' => _MI_XWHOOPS_OVERVIEW, 'link' => 'page=help'],
    ['name' => _MI_XWHOOPS_DISCLAIMER, 'link' => 'page=disclaimer'],
    ['name' => _MI_XWHOOPS_LICENSE, 'link' => 'page=license'],
    ['name' => _MI_XWHOOPS_SUPPORT, 'link' => 'page=support'],
];

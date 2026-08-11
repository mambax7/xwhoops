<?php declare(strict_types=1);

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Error-screen ownership hooks for the XOOPS 2.7.3 provider seam.
 *
 * Answering core.debug.errorscreen is only half of being a provider. Core offers the seat
 * to ONE declared owner, and a module that never claims it is never offered it -- it sits
 * installed, correct, and silent. These hooks are the claiming half.
 *
 * On a core without the seam every function here is a no-op, so the module installs and
 * behaves exactly as it always has.
 *
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Grant use_xwhoops to the webmaster group, once, at install.
 *
 * The preload checks this permission before it will render, and until 2.0.0-Beta3 nothing
 * ever created a row for it: xoops_version.php declares no permission block and there was
 * no install hook. So checkPermission() answered false on every fresh install and the
 * module reported 'disabled' for the lifetime of the site, naming a permission the
 * administrator had never been shown. The permission is a real choice; the DEFAULT was
 * the bug.
 *
 * Two properties this deliberately has:
 *
 *  - It seeds ONLY when no row exists for this permission on this module. An update must
 *    never re-grant a permission an administrator has revoked, and a revoked permission is
 *    stored as the ABSENCE of that group's row -- indistinguishable from "never granted"
 *    except by whether any row exists at all. So the test is on the whole permission, not
 *    on the webmaster row, and it is the difference between seeding a default and
 *    overriding a decision.
 *  - It is not fatal, and it does not fail the install. A site whose group table refuses
 *    the insert still gets a working module and a message pointing at the permissions
 *    page.
 *
 * THREE outcomes, not two. 'exists' and 'failed' are both "no row was created", and a
 * boolean collapses them -- which made a reinstall over a retained permission row warn
 * that the grant had failed when it had simply already been done. The caller must be able
 * to tell "nothing to do" from "something went wrong".
 *
 * @return string 'created' | 'exists' | 'failed'
 */
function xwhoops_seedUsePermission(\XoopsModule $module): string
{
    if (! \function_exists('xoops_getHandler') || ! \class_exists('CriteriaCompo')) {
        return 'failed';
    }

    $mid = (int) $module->getVar('mid');
    if ($mid <= 0) {
        return 'failed';
    }

    $permHandler = xoops_getHandler('groupperm');

    // getCount() is guarded as well as addRight(). Both are called below, xoops_getHandler()
    // is declared as returning mixed, and checking only the one you happen to think of
    // first is how this passed review and failed static analysis.
    if (! \is_object($permHandler)
        || ! \method_exists($permHandler, 'getCount')
        || ! \method_exists($permHandler, 'addRight')) {
        return 'failed';
    }

    $criteria = new \CriteriaCompo(new \Criteria('gperm_modid', (string) $mid));
    $criteria->add(new \Criteria('gperm_name', 'use_xwhoops'));
    if ((int) $permHandler->getCount($criteria) > 0) {
        return 'exists';
    }

    $adminGroup = \defined('XOOPS_GROUP_ADMIN') ? (int) \constant('XOOPS_GROUP_ADMIN') : 1;

    return $permHandler->addRight('use_xwhoops', 0, $adminGroup, $mid) ? 'created' : 'failed';
}

/**
 * Claim the error screen, unless another provider already holds it.
 *
 * First installed wins, and core enforces that against an ORDINARY claim -- this cannot
 * take a seat another module is sitting in by asking normally. A deliberate handover uses
 * core's $force, which the update hook below does after establishing the holder has
 * stopped; module code is trusted code, so the guard stops an accident rather than an
 * attacker. When the seat IS taken the installation still succeeds:
 * refusing would mean you could not keep two providers installed and switch between them.
 *
 * @return bool true — installation succeeds either way
 */
function xoops_module_install_xwhoops(\XoopsModule $module): bool
{
    // BEFORE the seam guard below, not after. The permission gates the pre-seam
    // registration path too, so a 2.7.0-2.7.2 install needs it just as much -- and that
    // guard returns early.
    //
    // Only 'failed' is worth a message. 'exists' means a reinstall found the permission
    // still configured from last time, which is the desired outcome and not news.
    if ('failed' === xwhoops_seedUsePermission($module)) {
        $module->setMessage(
            'NOTE: could not grant the use_xwhoops permission automatically. '
            . 'Grant it at Admin → xWhoops → Permissions, or Whoops will stay dormant.'
        );
    }

    if (! \function_exists('xoops_recordErrorScreenOwner')) {
        return true;
    }

    $dirname = (string) $module->getVar('dirname', 'n');

    if (xoops_recordErrorScreenOwner($dirname)) {
        $module->setMessage(
            'This module now owns the error screen. It takes effect once '
            . 'xoops_data/data/debug.php exists and is enabled — Admin → Preferences → '
            . 'Debug Mode alone does not activate it.'
        );

        return true;
    }

    // A refused claim has two distinct causes, and they need different advice. A
    // non-empty recorded owner means the seat is genuinely taken; an EMPTY one means the
    // WRITE failed -- xoops_data/data unwritable, most likely -- and telling the admin
    // that '' already owns the screen would send them to the wrong problem.
    $held = \function_exists('xoops_getRecordedErrorScreenOwner')
        ? xoops_getRecordedErrorScreenOwner()
        : '';

    if ('' === $held) {
        $module->setMessage(
            'WARNING: could not record the error-screen owner. Check that xoops_data/data '
            . 'is writable, then update this module to claim the screen.'
        );

        return true;
    }

    $heldSafe = \htmlspecialchars($held, \ENT_QUOTES);
    $module->setMessage(
        "WARNING: '" . $heldSafe . "' already claims the error screen, and installing this "
        . 'module has not changed that. To hand the screen over: deactivate ' . $heldSafe
        . ' and update this module, or uninstall ' . $heldSafe . ' and reinstall this module, '
        . "or pin it with 'error_screen' => '" . \htmlspecialchars($dirname, \ENT_QUOTES)
        . "' in xoops_data/data/debug.php."
    );

    return true;
}

/**
 * Take the error screen from a holder that is gone or inactive.
 *
 * The deliberate handover. Core refuses an ordinary claim while another token is recorded
 * -- correctly, since an update must not quietly take a seat from a provider that is still
 * running -- so the transfer needs someone to establish that the holder has stopped. That
 * is a question about the module table, which core's bootstrap cannot answer and should
 * not try to; it belongs here, where the module handler exists and an administrator is
 * watching.
 *
 * @return bool true — the update itself succeeds either way
 */
function xoops_module_update_xwhoops(\XoopsModule $module): bool
{
    // The migration path for every site that installed a pre-Beta3 xwhoops and has been
    // looking at 'disabled' ever since. Safe to run on every update: it creates a row only
    // when the permission has NO rows at all, so an administrator who revoked it keeps
    // their decision.
    $seeded = xwhoops_seedUsePermission($module);
    if ('created' === $seeded) {
        $module->setMessage('Granted the use_xwhoops permission to the webmaster group.');
    } elseif ('failed' === $seeded) {
        $module->setMessage(
            'NOTE: could not grant the use_xwhoops permission automatically. '
            . 'Grant it at Admin → xWhoops → Permissions, or Whoops will stay dormant.'
        );
    }

    if (! \function_exists('xoops_recordErrorScreenOwner')) {
        return true;
    }

    $dirname = (string) $module->getVar('dirname', 'n');
    $held = \function_exists('xoops_getRecordedErrorScreenOwner')
        ? xoops_getRecordedErrorScreenOwner()
        : '';

    if ('' === $held || $held === $dirname) {
        // Free, or already ours. Recording again is also how a module gets the seat back
        // after the previous holder was uninstalled. The result is checked: an ignored
        // false here reported "owns the error screen" over a write that never happened.
        if (xoops_recordErrorScreenOwner($dirname)) {
            $module->setMessage('This module owns the error screen.');
        } else {
            $module->setMessage(
                'WARNING: could not record the error-screen owner. Check that '
                . 'xoops_data/data is writable and update this module again.'
            );
        }

        return true;
    }

    $holder = null;
    $handler = \function_exists('xoops_getHandler') ? xoops_getHandler('module') : null;
    if (\is_object($handler) && \method_exists($handler, 'getByDirname')) {
        $holder = $handler->getByDirname($held);
    }

    $holderIsRunning = \is_object($holder)
        && \method_exists($holder, 'isactive')
        && (bool) $holder->isactive();

    if ($holderIsRunning) {
        $safeHeld = \htmlspecialchars($held, \ENT_QUOTES);
        $module->setMessage(
            "The error screen still belongs to '" . $safeHeld . "', which is installed and "
            . 'active, so this update has not taken it. Deactivate ' . $safeHeld
            . ' and update this module again, or pin the owner in xoops_data/data/debug.php.'
        );

        return true;
    }

    if (xoops_recordErrorScreenOwner($dirname, true)) {
        $module->setMessage(
            "Took the error screen from '" . \htmlspecialchars($held, \ENT_QUOTES)
            . "', which is no longer active."
        );
    } else {
        $module->setMessage(
            'WARNING: could not record the error-screen owner. Check that xoops_data/data '
            . 'is writable and update this module again.'
        );
    }

    return true;
}

/**
 * Release the seat on the way out — but only if we hold it.
 *
 * Uninstalling is the deliberate act that frees the screen. DEACTIVATING is not: a site
 * that switches this module off for an afternoon should find its setup intact when it
 * switches back on.
 *
 */
function xoops_module_uninstall_xwhoops(\XoopsModule $module): bool
{
    if (\function_exists('xoops_releaseErrorScreenOwner')
        && ! xoops_releaseErrorScreenOwner((string) $module->getVar('dirname', 'n'))) {
        $module->setMessage(
            'WARNING: could not release the recorded error-screen owner. Check that '
            . 'xoops_data/data is writable, then remove "error_screen_owner" from '
            . 'xoops_data/data/debug-runtime.json by hand.'
        );
    }

    return true;
}

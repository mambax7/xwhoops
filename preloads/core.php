<?php declare(strict_types=1);

//namespace XoopsModules\Xwhoops;

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\SystemFacade;
use Xmf\Module\Helper\Permission;

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    Richard Griffith <richard@geekwright.com>
 */
class XwhoopsCorePreload extends \XoopsPreloadItem
{
    private const AUTOLOADER_PATH = '/vendor/autoload.php';

    /**
     * Kept in step with include/errorscreen.php, which seeds this permission at install.
     * If you rename either, rename both — a mismatch is silent and looks exactly like an
     * administrator having revoked the permission.
     */
    private const PERMISSION_NAME = 'use_xwhoops';
    private const PERMISSION_ITEM_ID = 0;

    /** The error-screen owner token this module answers for: its own dirname. */
    public const OWNER = 'xwhoops';

    /** Older spellings still honoured, from before the token became the dirname. */
    public const LEGACY_OWNERS = ['whoops'];

    /**
     * Answers core.debug.errorscreen on XOOPS 2.7.3 and later.
     *
     * Registration moved here from eventCoreIncludeCommonAuthSuccess for two reasons.
     *
     * PHP has one error handler and one exception handler and whoever registers LAST owns
     * them. Auth-success runs in the middle of the bootstrap, so anything registering
     * later silently displaced this module -- and this module silently displaced anything
     * that had registered earlier. Which one a site ended up with came down to module
     * weight and mid, so reinstalling something unrelated could change your error screen
     * with nothing anywhere reporting it. The seam fires as the last statement of
     * include/common.php, and offers the seat to exactly one declared owner.
     *
     * The second reason is honesty. Registering outside the seam meant Whoops could hold
     * the handlers while XOOPS_ERROR_SCREEN_STATUS reported that XoopsLogger had them --
     * a diagnostic saying the opposite of what was true.
     *
     * @param array{owner?: string, developer_request?: bool, report?: callable} $args
     */
    public static function eventCoreDebugErrorscreen($args): void
    {
        $owner = (string) ($args['owner'] ?? '');
        if (self::OWNER !== $owner && ! \in_array($owner, self::LEGACY_OWNERS, true)) {
            return;
        }

        // Core hands the reporting channel to the provider it is offering the seat to.
        // Without it this module cannot say what it did, and registering silently is
        // worse than not registering: the published constants would describe a screen
        // nobody is showing.
        $report = $args['report'] ?? null;
        if (! \is_callable($report)) {
            return;
        }

        // Whoops exposes source code, request data and environment details. Core passes
        // its answer and does not enforce it, because a provider may legitimately render
        // a production-safe page; one that exposes internals must refuse.
        if (! (bool) ($args['developer_request'] ?? false)) {
            $report('disabled', 'Whoops is dormant: this request is not from an authenticated site administrator.');

            return;
        }

        // The module's own permission, on top of the core gate. This is a genuine second
        // question -- core answers "may diagnostics be exposed to whoever is making this
        // request", this answers "has the site granted this user use_xwhoops" -- and a
        // site may legitimately withhold Whoops from an administrator who qualifies.
        //
        // It only became a genuine question once the permission was SEEDED. Until
        // 2.0.0-Beta3 nothing granted it: xoops_version.php declared none and no install
        // hook created a row, so checkPermission() answered false on every fresh install
        // and this module reported 'disabled' forever, naming a permission the
        // administrator had never been told existed. It is seeded to the webmaster group
        // at install now -- see xoops_module_install_xwhoops() in include/errorscreen.php.
        //
        // ORDER MATTERS, and it is not arbitrary. This check sits BEFORE
        // initializeAutoloader(), so a refusal here means filp/whoops is never loaded and
        // never registers with Composer\InstalledVersions. DebugBar's diagnostics page
        // read that registry and reported "Whoops library: Not installed" on a site where
        // it was installed and fine. DebugBar now checks the module vendor on disk before
        // saying that, but the general lesson stands for anything that inspects us: a
        // provider standing down is invisible except through the seam's own constants.
        if (! self::hasModulePermission()) {
            $report('disabled', 'Whoops is dormant: the use_xwhoops permission is not granted to this user. Grant it at Admin → xWhoops → Permissions.');

            return;
        }

        if (! self::initializeAutoloader()) {
            $report('missing', "xwhoops vendor/autoload.php is missing — run 'composer install' inside modules/xwhoops.");

            return;
        }

        try {
            self::registerWhoops();
        } catch (\Throwable $e) {
            $report('error', 'Whoops could not be initialised: ' . $e->getMessage());

            return;
        }

        $report('active', 'Whoops owns the exception and shutdown handlers.');
    }

    /**
     * Legacy entry point, for a core that predates the error-screen seam.
     *
     * On 2.7.3 and later this must do nothing: the seam offers the seat at the end of the
     * bootstrap and registering here as well would take the handlers whether or not this
     * module is the declared owner -- the exact behaviour being replaced.
     */
    public static function eventCoreIncludeCommonAuthSuccess(): void
    {
        if (\function_exists('xoops_activateErrorScreen')) {
            return;
        }

        if (! self::shouldRegisterWhoops()) {
            return;
        }

        // Permission BEFORE the autoloader, matching the seam path above. It used to be
        // checked inside initializeWhoops(), after the autoloader had already run, so a
        // user without the permission still loaded xwhoops's vendor tree and registered it
        // with Composer's package registry -- work done on behalf of a request that was
        // about to be refused, and a side effect visible to anything reading that registry.
        if (! self::hasModulePermission()) {
            return;
        }

        // Skip Whoops registration cleanly if the autoloader is missing.
        if (! self::initializeAutoloader()) {
            return;
        }
        self::registerWhoops();
    }

    /**
     * Whoops exposes source code, request data, and environment details.
     * Keep it disabled unless XOOPS debug is enabled and an authenticated
     * site admin is viewing the request.
     *
     * Pre-seam path only. isAdmin() with a mid answers "admin of THIS module", which is
     * why 2.7.3 introduced xoops_isDeveloperRequest() and why the seam passes its answer
     * instead: one test, the same one every diagnostic tool in the stack uses.
     */
    private static function shouldRegisterWhoops(): bool
    {
        if (! \defined('XOOPS_DEBUG') || ! XOOPS_DEBUG) {
            return false;
        }

        $xoopsUser = $GLOBALS['xoopsUser'] ?? null;
        if (! $xoopsUser instanceof \XoopsUser) {
            return false;
        }

        $moduleHandler = xoops_getHandler('module');
        if (! $moduleHandler instanceof \XoopsModuleHandler) {
            return false;
        }

        $module = $moduleHandler->getByDirname(self::OWNER);
        if (! $module instanceof \XoopsModule) {
            return false;
        }

        return $xoopsUser->isAdmin((int) $module->getVar('mid'));
    }

    /**
     * Load the Whoops vendored autoloader. Returns true on success.
     *
     * The original implementation threw a RuntimeException here, which fatals
     * every page load via `eventCoreIncludeCommonAuthSuccess`, including the
     * admin pages the user would need to disable or repair this module. Emit
     * a non-fatal warning instead, and let the caller report the failure.
     *
     * @return bool true if the autoloader was loaded
     */
    private static function initializeAutoloader(): bool
    {
        $autoloader = \dirname(__DIR__) . self::AUTOLOADER_PATH;

        if (! \file_exists($autoloader)) {
            \trigger_error(
                'xwhoops: vendor/autoload.php missing — '
                . "run 'composer install' inside modules/xwhoops "
                . 'or reinstall the module from a release tarball. '
                . 'Whoops error handler is disabled until then.',
                \E_USER_WARNING
            );

            return false;
        }

        require_once $autoloader;

        return true;
    }

    /**
     * The module's own permission, checked separately from the developer gate.
     *
     * Core's gate answers "may diagnostics be exposed to whoever is making this request";
     * this answers "has the site granted this user use_xwhoops". Both must hold, and they
     * are different questions, so a site can withhold Whoops from an administrator who
     * would otherwise qualify.
     *
     * Seeded to the webmaster group at install, so the default answer is yes and an
     * administrator who wants it withheld goes and withholds it — rather than the module
     * being silently inert until somebody guesses that a permission they were never shown
     * is the reason.
     */
    private static function hasModulePermission(): bool
    {
        if (! \class_exists(Permission::class)) {
            return false;
        }

        $permissionHelper = new Permission(self::OWNER);

        return $permissionHelper->checkPermission(self::PERMISSION_NAME, self::PERMISSION_ITEM_ID, false);
    }

    private static function registerWhoops(): void
    {
        // Registration LAST, deliberately -- do not "tidy" this back.
        //
        // register() is the moment Whoops takes PHP's exception and shutdown handlers.
        // Everything before it can still fail harmlessly; everything after it cannot.
        // This method used to register first and configure afterwards, so a throw in
        // addDataTableCallback() left Whoops holding the handlers while this module
        // reported 'error' to the seam -- and because the failure was caught HERE, core's
        // own catch never ran and had nothing to react to.
        //
        // XOOPS 2.7.3 now hands the error and exception handlers back when a provider
        // reports 'error'. It cannot hand back a shutdown function: nothing in PHP can
        // unregister one. So the residue is ours to close, and the way to close it is to
        // have nothing left that can fail by the time we register.
        // Whoops must never take the ERROR handler in the first place.
        //
        // This module owns the exception and shutdown handlers only -- its README, install
        // notes, both changelogs and its own status message have said so since before the
        // seam existed. Whoops' Run::register() takes all three (Run.php 216-218), and its
        // error handler turns every notice, warning and deprecation into an
        // ErrorException and renders a full-page screen, so one harmless E_WARNING during
        // the boot replaces the page you asked for. XoopsLogger and DebugBar want those.
        //
        // Popping the frame afterwards with restore_error_handler() looks equivalent and
        // is not: Run::unregister(), which its own __destruct() calls, restores BOTH
        // handlers on the way out (Run.php 231-241) -- so it would pop a frame it no
        // longer owned, because this module had already taken back the one Whoops
        // installed. Refusing the handler at the facade keeps Whoops' register/unregister
        // pairing balanced by construction, which is the difference between "works today"
        // and "correct".
        $system = new class () extends SystemFacade {
            /**
             * DO NOT put a type on $types.
             *
             * The signature is copied from Whoops\Util\SystemFacade verbatim, where the
             * parameter is untyped. Adding `int|string` here NARROWS an inherited
             * parameter, which PHP rejects at class-declaration time -- a fatal on every
             * request, from a change that reads like tidying. Static-analysis and
             * refactoring tools propose exactly this; refuse it.
             *
             * The return types below are the opposite case: the parent declares none, and
             * a child may add one. They match the parent's own docblock.
             *
             * @param int|string $types
             */
            public function setErrorHandler(callable $handler, $types = 'use-php-defaults'): ?callable
            {
                return null;
            }

            public function restoreErrorHandler(): void
            {
                // Intentionally empty: Whoops never held the error handler (see
                // setErrorHandler above), so there is nothing for it to restore -- and a
                // real restore here would pop a frame belonging to somebody else.
            }
        };

        $whoops = new Run($system);
        $handler = new PrettyPageHandler();
        $handler->setPageTitle('XOOPS Debug');

        $handler->addDataTableCallback(
            \defined('_LOGGER_QUERIES') ? \_LOGGER_QUERIES : 'Queries',
            static fn (): array => self::getLoggerQueries()
        );

        $whoops->pushHandler($handler);
        $whoops->register();
    }

    /**
     * @return array|string[]
     */
    private static function getLoggerQueries(): array
    {
        $logger = \XoopsLogger::getInstance();

        if (false === $logger->renderingEnabled) {
            return ['XoopsLogger' => 'off'];
        }

        $queries = [];
        $count = 1;

        foreach ($logger->queries as $query) {
            $queries[$count] = self::formatQuery($query, $count);
            $count++;
        }

        return $queries;
    }

    private static function formatQuery(array $query, int $count): string
    {
        $error = (null === $query['errno'] ? '' : $query['errno'] . ' ') . ($query['error'] ?? '');
        $queryTime = isset($query['query_time']) ? \sprintf('%0.6f', $query['query_time']) : '';
        $queryKey = $count . ' - ' . ('' !== $queryTime ? $queryTime : 'No Time');

        if (null !== $query['errno']) {
            $queryKey = $count . ' - Error';
        }

        return $queryKey . ': ' . \htmlentities((string) $query['sql'], \ENT_QUOTES | \ENT_HTML5) . ' ' . $error;
    }
}

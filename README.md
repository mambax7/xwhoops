![alt XOOPS CMS](https://xoops.org/images/logoXoopsPhp81.png)

## xWhoops module for [XOOPS CMS 2.7.0+](https://xoops.org)


[![XOOPS CMS Module](https://img.shields.io/badge/XOOPS%20CMS-Module-blue.svg)](https://xoops.org)
[![Software License](https://img.shields.io/badge/license-GPL-brightgreen.svg?style=flat)](http://www.gnu.org/licenses/gpl-2.0.html)

[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/XoopsModules27x/xwhoops.svg?style=flat)](https://scrutinizer-ci.com/g/XoopsModules27x/xwhoops/?branch=main)
[![Latest Pre-Release](https://img.shields.io/github/tag/XoopsModules27x/xwhoops.svg?style=flat)](https://github.com/XoopsModules27x/xwhoops/tags/)
[![Latest Version](https://img.shields.io/github/release/XoopsModules27x/xwhoops.svg?style=flat)](https://github.com/XoopsModules27x/xwhoops/releases/)

---

## What it is

xWhoops brings **[Whoops](https://github.com/filp/whoops)** error display to XOOPS.

When an unhandled error occurs, instead of a generic failure page you get a screen naming
the exception, the stack frames that led to it, the source for whichever frame you click,
and the state of the request that triggered it.

Outside of the control panel there is no user interface. It "just works" when needed.

## What it does for you

**Turns "something went wrong" into "this line went wrong."** Unhandled errors are the
ones with the least information attached and the most guesswork involved. This is the
module that gives them a stack trace, the surrounding code, and the request that caused
them.

**Lets you walk the call stack.** Click any frame and see its code. The failure is often
several frames above where the exception surfaced, and clicking is faster than reading a
trace and opening files by hand.

**Shows the request beside the error.** Parameters, session data and server state sit in
the same screen, which is where most environment-specific bugs are visible.

**Shows your SQL.** With XoopsLogger enabled, the queries that ran appear in the
Environment & details section.

**Leaves your ordinary debug output intact.** xWhoops takes the exception and shutdown
handlers only, and hands the error handler straight back — so notices, warnings and
deprecations still reach XoopsLogger and the DebugBar module. You are not trading one set
of diagnostics for another.

**Cannot leak to your visitors.** Whoops exposes source code, request data and environment
details, so it is disabled unless debugging is on *and* the viewer is an authenticated site
administrator *and* the xWhoops permission has been granted. Administrators control which
groups qualify.

> **Do not enable this module's diagnostic output on a production site.**

## How it works

On XOOPS 2.7.3 and later, core publishes a rule for who owns PHP's error and exception
handlers: a site declares an owner, and that one module registers at the very end of the
boot — last, because whoever calls `set_error_handler()` last wins. This module answers for
the token `xwhoops`, its own dirname.

On 2.7.2 and earlier there is no such rule, and the module registers during
`eventCoreIncludeCommonAuthSuccess`, once the authenticated user is available for the
admin and permission checks. One file covers both; on the newer core the legacy path stands
down so the module cannot register twice.

---

## Requirements

- XOOPS 2.7.0 or later. Ownership behaviour described below applies from 2.7.3.
- PHP 8.2 or later.
- `filp/whoops`, which is **not** bundled in the repository.

## Installation

xWhoops is distributed as the Composer package `xoops/xwhoops`.

### Recommended: Composer

If your XOOPS 2.7 site is managed as a Composer project — its root `composer.json` requires [`xoops/module-installer-plugin`](https://github.com/XOOPS/module-installer-plugin) and sets `extra.xoops_modules` — run from the site root:

```bash
composer require xoops/xwhoops
```

This stages the module, together with its `filp/whoops` dependency, into `htdocs/modules/xwhoops`. Then:

- install the *xWhoops* module in the system administration module page
- grant access by selecting groups in the permissions section
- switch debugging on (see below)

One-time site setup (the module-installer-plugin, `extra.xoops_modules`, and `allow-plugins`) is described in the [module-installer-plugin guide](https://github.com/XOOPS/module-installer-plugin/blob/master/docs/composer-module-distribution.md).

### Manual / developer

The module depends on `filp/whoops`, which is **not** bundled in the repository, so a plain file drop-in requires one Composer step to fetch it:

- download or clone this repository into your `htdocs/modules/` directory as `xwhoops`
- open a terminal in that directory and run `composer install`
- install and configure the module as above

## Switching it on

**XOOPS 2.7.2 and earlier** — enable `XOOPS_DEBUG` in a development or controlled
troubleshooting environment. That is all.

**XOOPS 2.7.3 and later** — the error screen is activated by `xoops_data/data/debug.php`
instead. Copy `debug.dist.php` beside it and set `'enabled' => true`. Installing the module
already claimed the error screen, so nothing else is normally needed.

> Admin → Preferences → Debug Mode no longer activates the error screen, deliberately:
> writing a file on the server is a stronger credential than holding an admin session, and
> an error screen shows source and request data. With the module installed but no
> `debug.php`, XOOPS reports the status `dormant` rather than staying silent.

`docs/TUTORIAL.md` walks through the whole thing with a worked example.

## The Whoops display

The Whoops display contains 4 main sections.
- *top left* is the error that was encountered
- *lower left* shows the stack frames, the trace of the path of processing that resulted in the error
- *top right* shows the code for the currently selected stack frame item. Select a new stack frame to see the related code
- *lower right* shows environment information such as request parameters, session information, etc.

Note: if the XoopsLogger is enabled, MySQL queries will be shown in the Environment & details section.

## When Whoops does not appear (2.7.3+)

The site tells you why. `XOOPS_ERROR_SCREEN_STATUS` and `_MESSAGE` are published on every
request: `active`, `dormant` (no enabled `debug.php`), `disabled` (the module ran and chose
not to register — the message says why), `missing` (`composer install` has not been run),
`error` (the provider failed to start, and core handed the handlers back to XoopsLogger),
`unclaimed` (the configured owner is not an active module), `contested` (more than one module
answered one token, so the handlers went back to XoopsLogger), or `core`.

## Alongside xTracy

Both provide an error screen and PHP has one pair of handlers, so exactly one can own it.
Whichever was installed first keeps it. To hand it over: deactivate the holder and update
the module you want, uninstall the holder and reinstall the one you want, or pin your
choice with `'error_screen' => 'xwhoops'` in `debug.php`. Nothing is ever taken silently,
and deactivating the owner does not pass the screen to another module.

---

### Please visit us on https://xoops.org

Current and upcoming "next generation" versions of XOOPS CMS are crafted on GitHub at: https://github.com/XOOPS

# Changelog

**xWhoops brings [Whoops](https://github.com/filp/whoops) error display to XOOPS.** An
unhandled error becomes a screen naming the exception, with the stack frames that led
there, the source for whichever frame you click, and the state of the request that
triggered it. It takes the exception and shutdown handlers only — notices and warnings
still reach XoopsLogger and DebugBar — and shows only to an authenticated administrator
whose group holds the `use_xwhoops` permission. For developers and troubleshooting; not for
a live site. See `README.md` for what it does and `docs/TUTORIAL.md` for a walkthrough.

## 2.0.0-Beta2 - 2026-08-08

Adopts the error-screen provider seam introduced in XOOPS 2.7.3.

### Fixed

- Whoops is never handed the error handler at all, so ordinary debug output survives.
  Whoops' `register()` takes all three handlers — error, exception and shutdown
  (`Run.php:216-218`) — and this module never gave one back, although its README, install
  notes, both changelogs and its own status message have said "the exception and shutdown
  handlers only" since before the seam existed. The consequence was not cosmetic: every
  notice, warning and deprecation became an `ErrorException` and was rendered as a full
  Whoops screen, so a harmless `E_WARNING` during the boot replaced the page you asked for.
  XoopsLogger and DebugBar now receive what they always received, and uncaught exceptions
  and fatals are unaffected — those arrive through the exception and shutdown handlers,
  both still Whoops'.

  The mechanism is a `Whoops\Util\SystemFacade` subclass passed to `Run::__construct()`
  whose `setErrorHandler()` and `restoreErrorHandler()` do nothing. Calling
  `restore_error_handler()` after `register()` looks equivalent and is not:
  `Run::unregister()` — which Whoops' own `__destruct()` calls — restores **both**
  handlers on the way out (`Run.php:231-241`), so it would pop a frame this module had
  already taken back and leave the error handler one level below where the request
  started. Refusing the handler at the facade keeps Whoops' register/unregister pairing
  balanced by construction, which is the difference between "works today" and "correct".

- `admin/index.php` no longer assigns the result of `ExampleClass::flawedMethod()`, which
  returns `void` and in fact never returns at all — it throws, which is the entire point of
  the Example demo. Harmless, and wrong. Found by PHPStan, which is the argument for
  clearing its errors rather than baselining them.

- The three module lifecycle hooks in `include/errorscreen.php` take `\XoopsModule` natively
  rather than an untyped `$module`. They previously carried a comment claiming the loose
  signature protected a recoverable branch; a reviewer checked and it did not — the next
  statement dereferences `$module`, core passes a real `XoopsModule`, and bundled core
  modules already type these hooks. The comment was removed rather than reworded, because a
  note asserting a safety property the code does not have is worse than no note: it stops
  the next person adding a truthful type on grounds that were never real.

### Added

- A provider contract test (`tests/Unit/ErrorScreenContractTest.php`) covering the
  obligations the 2.7.3 seam places on a provider: refuse when `developer_request` is
  false, refuse when the flag is absent entirely, stay silent about a token that is not
  ours, answer the documented legacy spelling, register nothing when core sends no
  reporting channel, and keep the owner token equal to the module dirname. The developer
  gate is advisory by core's design, so this module's refusal is the only thing between
  Whoops and an anonymous visitor; prose cannot hold that and a test can. It runs without
  a booted XOOPS, because a gate that needs a database is a gate nobody runs.

  Its `tearDown()` **pops** the handler stack rather than pushing the original handler
  back on top. `set_error_handler()` adds a frame and `restore_error_handler()` removes
  one, so the tidier-looking `set_error_handler($original)` leaves the stack a frame
  deeper than PHPUnit saw at the start — which PHPUnit 11 correctly reports as a leaked
  handler. With this module's `failOnRisky="true"`, all six cases were marked risky and
  the run went red: a teardown written to be neat was the only thing stopping the CI gate
  this file exists to be.

### Changed

- Static analysis now sees the XOOPS APIs this module calls. `stubs/xoops.stub` gained
  `XoopsModule::setMessage()` and `isactive()`, `XoopsModuleHandler`, `xoops_getHandler()`,
  `redirect_header()`, `Xmf\Request`, and the three `xoops_*ErrorScreenOwner` functions
  XOOPS 2.7.3 publishes. Declaring the seam functions in the stub is **not** permission to
  drop the `function_exists()` guards around them — the module still runs unchanged on
  2.7.2, and the stub says so where it declares them.

  Chosen over regenerating `phpstan-baseline.neon`, which would have been quicker: nearly
  all of the errors sat in files this release adds or rewrites, so a fresh baseline would
  have frozen the *new* error-screen ownership API as permanently unanalysable — the one
  part a future provider is most likely to get wrong.

- Registration is now the LAST thing `registerWhoops()` does. `$whoops->register()` is the
  moment Whoops takes PHP's exception and shutdown handlers, and `addDataTableCallback()`
  used to run after it — so a throw while wiring the Queries panel left Whoops holding the
  handlers while this module reported `error`. Because that failure is caught here rather
  than propagating, core's own catch never ran and had nothing to react to. XOOPS 2.7.3
  hands the error and exception handlers back on an `error` report, but nothing in PHP can
  unregister a shutdown function, so the only way to close the rest is to have nothing left
  that can fail by the time we register.

- Registration moved from `eventCoreIncludeCommonAuthSuccess` to
  `eventCoreDebugErrorscreen`, so xWhoops takes PHP's handlers only when the site has
  declared it the owner. Auth-success runs in the middle of the boot, and PHP gives the
  handlers to whoever registers last — so anything registering later silently displaced
  this module, and this module silently displaced anything registering earlier. Which one
  a site ended up with came down to module weight and id, meaning reinstalling something
  unrelated could change your error screen with nothing anywhere reporting it.
- The legacy entry point is kept and returns immediately when the seam is present, so one
  file works on 2.7.2 and 2.7.3 alike and cannot register twice on the newer core.
- The developer check now uses the core's shared answer rather than
  `isAdmin($mid)`, which resolves against whichever module the request happens to be
  inside — context-dependence being the property `xoops_isDeveloperRequest()` was
  introduced to remove. The module's own `use_xwhoops` permission is still checked
  separately: "may diagnostics be exposed to this request" and "has this site granted this
  group xWhoops" are different questions.

### Added

- Claims the error screen at install, releases it at uninstall, and takes it from a holder
  that is gone or inactive at update. Deactivating deliberately does **not** release it, so
  a module switched off for an afternoon leaves the setup intact.
- Reports its outcome to core in every branch, including the ones where it decides not to
  register — `active`, `disabled` with the reason, `missing`, `error`. Core publishes it as
  `XOOPS_ERROR_SCREEN_STATUS`. Previously Whoops could hold the handlers while the site's
  own diagnostics reported that XoopsLogger had them; a diagnostic saying the opposite of
  what is true is worse than no diagnostic.

### Note

- On XOOPS 2.7.3+, the error screen is activated by `xoops_data/data/debug.php` only.
  Admin → Preferences → Debug Mode no longer activates it, deliberately: writing a file on
  the server is a stronger credential than holding an admin session. A site running Debug
  Mode alone will see the status `dormant` rather than silence.

## 2.0.0-Beta1 - 2026-07-02

- Targeted the XOOPS 2.7 line: require XOOPS 2.7.0+ and PHP 8.2+.
- Published as the Composer package `xoops/xwhoops`, installable with `composer require xoops/xwhoops` via the [XOOPS module-installer-plugin](https://github.com/XOOPS/module-installer-plugin).
- Corrected the `composer.json` license identifier to `GPL-2.0-or-later` and added package metadata (keywords, homepage, authors, support).
- Rewrote the README for the 2.7 line and Composer-first installation.

## 1.0.1 - 2026-04-25

- Guarded Whoops registration behind `XOOPS_DEBUG`, authenticated site-admin access, and the module permission.
- Prevented the admin example error path from firing when XOOPS debug is disabled.
- Added strict typing and a direct-access guard to the scaffold example class.
- Declared the PHP 8.2 runtime requirement in `composer.json`.
- Documented production safety requirements in README and SECURITY.

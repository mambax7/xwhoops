# xWhoops — a walkthrough

## What xWhoops is, and why you would want it

xWhoops brings [Whoops](https://github.com/filp/whoops) error display to XOOPS.

An unhandled error normally gives you the least information of any failure and the most
guesswork. xWhoops turns it into a screen that names the exception, shows **the stack
frames that led there, the source for whichever frame you click, and the state of the
request** that triggered it.

What that buys you, concretely:

- **A walkable call stack.** Click a frame, see its code. Faster than reading a trace and
  opening files by hand, and the real mistake is often several frames above the throw.
- **The request beside the error.** Parameters, session data, server state — where most
  environment-specific bugs are visible.
- **Your SQL too**, if XoopsLogger is enabled.
- **Your ordinary debug output survives.** xWhoops takes the exception and shutdown
  handlers only and hands the error handler straight back, so notices, warnings and
  deprecations still reach XoopsLogger and the DebugBar module. You are not trading one set
  of diagnostics for another.
- **It cannot leak.** Whoops shows source, request data and environment, so it appears only
  when debugging is on, the viewer is an authenticated site administrator, and the
  `use_xwhoops` permission has been granted to their group.

**Who it is for:** developers and administrators diagnosing failures on a development or
controlled troubleshooting site. Not for a live one.

---

The rest of this document is a working tutorial: install it, switch it on, break something
on purpose, and read what comes back. This assumes a XOOPS development site you are willing
to break.

Where the instructions differ between XOOPS versions, both are given: 2.7.3 introduced an
error-screen ownership rule that changes how this module is switched on.

---

## 1. Install the module

Extract the archive into `modules/`, rename the directory to `xwhoops`, then — before
installing it in XOOPS — fetch its library:

```
cd modules/xwhoops
composer install
```

xWhoops vendors Whoops inside itself, unlike xTracy which expects Tracy in `xoops_lib`.
If you skip this step the module still installs and runs; it simply reports that its
autoloader is missing instead of failing in some obscure way later.

Now install it from Administration → Modules.

## 2. Grant the permission

Administration → xWhoops → Permissions, and select the groups allowed to see Whoops.
Webmasters are the usual answer. Nobody outside those groups will ever see a Whoops
screen, whatever else is configured.

This is the module's own permission, and it is checked **in addition to** the site's
developer test — the two answer different questions: "may diagnostics be exposed to
whoever is making this request" and "has this site granted this group xWhoops". A site can
withhold Whoops from an administrator who would otherwise qualify.

## 3. Switch debugging on

**On XOOPS 2.7.2 and earlier** — turn on Admin → Preferences → Debug Mode. That is all;
xWhoops registers itself during the boot.

**On XOOPS 2.7.3 and later** — the error screen is activated by
`xoops_data/data/debug.php` instead. If that file does not exist, copy the template beside
it:

```
cp xoops_data/data/debug.dist.php xoops_data/data/debug.php
```

and make sure of one line:

```php
'enabled' => true,
```

Installing the module already claimed the error screen, so nothing else is normally
needed: `error_screen` ships as `'auto'`, meaning "the first error-screen module
installed".

**Why the change?** Writing a file on the server is a stronger credential than holding an
admin session, and an error screen shows source code, file paths and request data. The
stronger exposure asks for the stronger credential. Debug Mode keeps the meaning it has
had for twenty years: XoopsLogger renders its log into the page.

## 4. See it work

The module ships an example that throws on purpose. As a member of a permitted group,
visit:

```
/modules/xwhoops/admin/index.php?do=example
```

You should get the Whoops screen. If you get the ordinary XOOPS error page instead — the
one that writes `=== EXCEPTION ===` into `xoops_data/logs/debug.log` — then Whoops is not
holding the handlers, and the section below tells you why.

## 5. Reading the screen

Four regions, and they answer different questions:

| Region | Question it answers |
|---|---|
| Top left | What went wrong |
| Lower left | The stack frames — how the request reached the failure |
| Top right | The code for the selected frame; click another frame to move |
| Lower right | Environment: request parameters, session, server state |

If XoopsLogger is enabled, the SQL it recorded appears in the environment section, which
is often where the real answer is.

One thing worth knowing: xWhoops takes the **exception and shutdown handlers only**, and
hands the error handler straight back. Notices, warnings and deprecations therefore still
reach XoopsLogger and the DebugBar module — you do not lose your ordinary debug output by
turning Whoops on.

---

## When Whoops does not appear

On XOOPS 2.7.3+, the site says why rather than leaving you to guess. Four constants are
published on every request:

| Constant | Meaning |
|---|---|
| `XOOPS_ERROR_SCREEN_OWNER` | which module owns the screen, or `core` |
| `XOOPS_ERROR_SCREEN_SOURCE` | `config` (pinned in debug.php), `recorded` (claimed at install), or `default` |
| `XOOPS_ERROR_SCREEN_STATUS` | what actually happened |
| `XOOPS_ERROR_SCREEN_MESSAGE` | a sentence explaining that status |

Read them from a scratch file after `mainfile.php`:

```php
echo XOOPS_ERROR_SCREEN_STATUS, ': ', XOOPS_ERROR_SCREEN_MESSAGE;
```

What the statuses mean:

- **`active`** — Whoops has the handlers. If the example still shows the XOOPS error page,
  something registered after it; that would be a bug worth reporting.
- **`dormant`** — the module is installed and recorded as owner, but `debug.php` is absent
  or `'enabled' => false`. Step 3.
- **`disabled`** — the module ran and chose not to register. The message says which reason:
  the request is not from an authenticated webmaster, or the `use_xwhoops` permission is
  not granted to this user.
- **`missing`** — `composer install` has not been run inside the module. Step 1.
- **`unclaimed`** — the configured owner is not an active module; usually it was
  deactivated. XOOPS does **not** promote another installed provider into a free seat.
- **`core`** — nothing owns the screen; XoopsLogger has the handlers.
- **`error`** — Whoops threw while starting; the message carries the reason.

If the constants are undefined, the core predates the seam — you are on 2.7.2 or earlier,
and Debug Mode is what switches the module on.

---

## Living with xTracy as well

Both modules provide an error screen, and PHP has exactly one pair of handlers, so exactly
one can own it. Before 2.7.3 this was decided by module weight and id, which meant
reinstalling something unrelated could silently change your error screen. Now XOOPS
resolves it in three steps, first answer wins:

1. `'error_screen'` in `debug.php`, when it names something other than `'auto'`
2. the owner recorded when a provider module was installed
3. `core`

So:

- **Installing the second module does not take the seat.** It says so and leaves the first
  alone.
- **Deactivating the owner does not pass the seat on.** The site falls back to core error
  handling and reports `unclaimed`.
- **To hand the seat over**, do one of three things: deactivate the holder and *update* the
  module you want; uninstall the holder and reinstall the one you want; or name your choice
  with `'error_screen' => 'xwhoops'` in `debug.php`, which beats anything recorded.

If you want certainty rather than convenience, pin it in `debug.php`.

---

## Turning it off

In increasing order of permanence:

- set `'error_screen' => 'core'`, which keeps the handlers with XoopsLogger whatever is
  installed
- set `'enabled' => false` at the top of `debug.php` (or turn off Debug Mode, on 2.7.2 and
  earlier)
- remove the groups from Administration → xWhoops → Permissions
- deactivate or uninstall the module

Deactivating does **not** release the recorded ownership, so switching it back on restores
the setup you had. Uninstalling does release it.

---

## Before you go live

Delete `xoops_data/data/debug.php` or set `'enabled' => false` — and on older XOOPS, turn
Debug Mode off. Nothing here can reach an anonymous visitor even if you forget, but a live
site has no reason to carry a debug configuration at all.

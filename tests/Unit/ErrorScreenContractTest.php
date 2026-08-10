<?php

declare(strict_types=1);

namespace XoopsModules\Xwhoops\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The obligations XOOPS 2.7.3's error-screen seam puts on a provider.
 *
 * ADR-0001 promises a CI gate that fails if an official provider stops honouring
 * `developer_request`. This is that gate. It exists because the gate is ADVISORY by
 * design -- core passes its answer and does not enforce it, so that a provider may
 * render a production-safe page for anonymous visitors -- which means the only thing
 * standing between this module and an anonymous visitor reading source, request data
 * and environment is the branch tested below. Prose cannot hold that; a test can.
 *
 * Runs without a booted XOOPS on purpose. Every case here exercises the part of
 * eventCoreDebugErrorscreen() that decides BEFORE it touches anything XOOPS-shaped:
 * the token match, the reporting channel, and the developer gate. That is deliberate,
 * not a shortcut -- a contract test that needed a database would not run in CI, and a
 * gate nobody runs is prose again.
 */
final class ErrorScreenContractTest extends TestCase
{
    /** @var list<array{0: string, 1: string}> */
    private array $reports = [];

    private mixed $errorHandlerBefore = null;

    private mixed $exceptionHandlerBefore = null;

    protected function setUp(): void
    {
        $this->reports = [];

        // XoopsPreloadItem is a core class; the module's own file only needs it to exist
        // as a parent. A fixture file provides the stand-in -- no eval(), which a scanner
        // flags and a fixture never needs.
        require_once __DIR__ . '/fixtures/XoopsPreloadItem.php';

        require_once \dirname(__DIR__, 2) . '/preloads/core.php';

        $this->errorHandlerBefore = $this->currentErrorHandler();
        $this->exceptionHandlerBefore = $this->currentExceptionHandler();
    }

    protected function tearDown(): void
    {
        // The permission verdict a case may have set must not leak to the next one.
        unset($GLOBALS['__xwhoops_test_permission_granted']);

        // POP back to where this case started; do not PUSH the old handler on top.
        //
        // set_error_handler() adds a frame, restore_error_handler() removes one. An
        // earlier version of this teardown called set_error_handler($original), which
        // leaves the stack one frame deeper than PHPUnit saw at the start -- and PHPUnit
        // 11 rightly calls that a leaked handler and marks the case risky. With
        // failOnRisky the whole gate then went red, so a teardown written to be tidy was
        // the only thing stopping the CI gate this file exists to be.
        //
        // Bounded, because a runaway loop in a teardown is a hung build.
        for ($i = 0; $i < 16 && $this->currentErrorHandler() !== $this->errorHandlerBefore; ++$i) {
            restore_error_handler();
        }
        for ($i = 0; $i < 16 && $this->currentExceptionHandler() !== $this->exceptionHandlerBefore; ++$i) {
            restore_exception_handler();
        }
    }

    /**
     * Read the live error handler without disturbing it: PHP offers no getter, and the
     * set-then-restore pair is the only way to look. Net zero frames.
     */
    private function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    private function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    /**
     * Not static, and cannot become static: the reporting closure below captures $this so
     * that a case can read back what the provider reported. A tool that looks for "$this"
     * in a method body but not inside its closures would call this staticifiable; it is
     * not, and the change would be a fatal rather than a style question.
     *
     * Recorded as a standing caution, NOT as a live one. The configured Rector run does
     * not propose it -- its rule here goes the other way, de-staticing the two pure
     * helpers above. An earlier note in this work read "non-static cleanup" as
     * "staticify" and got the direction backwards; three reviewers ran the tool and said
     * so.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return $overrides + [
            'owner' => 'xwhoops',
            'developer_request' => true,
            'report' => function (string $status, string $message = ''): bool {
                $this->reports[] = [$status, $message];

                return true;
            },
        ];
    }

    private function handlersMoved(): bool
    {
        if ($this->currentErrorHandler() !== $this->errorHandlerBefore) {
            return true;
        }

        return $this->currentExceptionHandler() !== $this->exceptionHandlerBefore;
    }

    #[Test]
    public function itRefusesWhenTheRequestIsNotADevelopers(): void
    {
        // The obligation core cannot check. Whoops shows source, superglobals and
        // environment; if this branch ever stops refusing, an anonymous visitor sees all
        // three and nothing else in the stack will stop it.
        \XwhoopsCorePreload::eventCoreDebugErrorscreen($this->event(['developer_request' => false]));

        self::assertCount(1, $this->reports, 'a provider must report in every branch, including this one');
        self::assertSame('disabled', $this->reports[0][0]);
        self::assertFalse($this->handlersMoved(), 'nothing may be registered for a non-developer request');
    }

    #[Test]
    public function itRefusesWhenTheGateAnswerIsAbsentEntirely(): void
    {
        // A core too old to send the flag, or a caller that forgot it. Absent must mean
        // no, never "assume yes" -- the failure has to land on the safe side.
        \XwhoopsCorePreload::eventCoreDebugErrorscreen([
            'owner' => 'xwhoops',
            'report' => function (string $status, string $message = ''): bool {
                $this->reports[] = [$status, $message];

                return true;
            },
        ]);

        self::assertSame('disabled', $this->reports[0][0] ?? '');
        self::assertFalse($this->handlersMoved());
    }

    #[Test]
    public function itIgnoresATokenThatIsNotItsOwn(): void
    {
        // The seat belongs to whoever core named. Answering anyway is the load-order
        // roulette the whole mechanism exists to end, and it is what core's contested
        // detectors would catch -- but a provider should never make them work for it.
        \XwhoopsCorePreload::eventCoreDebugErrorscreen($this->event(['owner' => 'somebodyelse']));

        self::assertSame([], $this->reports, 'a provider must stay silent about a seat it was not offered');
        self::assertFalse($this->handlersMoved());
    }

    #[Test]
    public function itAnswersItsDocumentedLegacyToken(): void
    {
        // Aliases live in the provider, never in core -- so the provider is the only
        // place this can be verified.
        \XwhoopsCorePreload::eventCoreDebugErrorscreen($this->event([
            'owner' => 'whoops',
            'developer_request' => false,
        ]));

        self::assertCount(1, $this->reports, 'the legacy spelling must still be answered');
    }

    #[Test]
    public function itRegistersNothingWhenCoreSendsNoReportingChannel(): void
    {
        // No channel means no way to say what happened. Registering anyway would leave
        // the published constants describing a screen nobody is showing, which is the
        // exact silence this seam was built to remove.
        \XwhoopsCorePreload::eventCoreDebugErrorscreen([
            'owner' => 'xwhoops',
            'developer_request' => true,
        ]);

        self::assertFalse($this->handlersMoved(), 'no reporting channel must mean no registration');
    }

    #[Test]
    public function itRegistersWhoopsAndLeavesTheErrorHandlerWithCore(): void
    {
        // The success path, and the one that matters most: it is where the SystemFacade
        // subclass earns its place. Whoops' register() takes all three handlers by
        // default; this module refuses the error handler at the facade so XoopsLogger and
        // DebugBar keep it, and takes only the exception and shutdown handlers.
        require_once __DIR__ . '/fixtures/Permission.php';
        $GLOBALS['__xwhoops_test_permission_granted'] = true;

        \XwhoopsCorePreload::eventCoreDebugErrorscreen($this->event());

        self::assertSame('active', $this->reports[0][0] ?? '', 'a granted developer request must activate');

        // The error handler is UNCHANGED -- the facade never let Whoops have it.
        self::assertSame(
            $this->errorHandlerBefore,
            $this->currentErrorHandler(),
            'Whoops must not take the error handler; XoopsLogger keeps it'
        );

        // The exception handler is Whoops': register() sets [$run, 'handleException'].
        $exceptionHandler = $this->currentExceptionHandler();
        self::assertIsArray($exceptionHandler, 'the exception handler must be the Whoops Run callable');
        self::assertInstanceOf(\Whoops\Run::class, $exceptionHandler[0]);

        // Tear it down the way Whoops itself does at destruction, and confirm the pairing
        // balances: unregister() restores BOTH handlers through the facade, so the error
        // handler (a facade no-op) is untouched and the exception handler returns to
        // baseline. This is the regression test for the round where restore_error_handler()
        // after register() left the stack one frame short when Whoops tidied up.
        $exceptionHandler[0]->unregister();

        self::assertSame($this->errorHandlerBefore, $this->currentErrorHandler());
        self::assertSame($this->exceptionHandlerBefore, $this->currentExceptionHandler());
    }

    #[Test]
    public function itStaysDormantWhenThePermissionIsNotGranted(): void
    {
        // The developer gate and the module permission answer different questions; a site
        // can withhold Whoops from an administrator who would otherwise qualify.
        require_once __DIR__ . '/fixtures/Permission.php';
        $GLOBALS['__xwhoops_test_permission_granted'] = false;

        \XwhoopsCorePreload::eventCoreDebugErrorscreen($this->event());

        self::assertSame('disabled', $this->reports[0][0] ?? '');
        self::assertFalse($this->handlersMoved(), 'a withheld permission must register nothing');
    }

    #[Test]
    public function theOwnerTokenIsTheModuleDirectoryName(): void
    {
        // Core resolves a token to a directory to go and look in. If these ever diverge,
        // 'error_screen' => 'xwhoops' in debug.php stops finding this module and the
        // failure looks like "the module is broken" rather than "the token is wrong".
        self::assertSame(
            basename(\dirname(__DIR__, 2)),
            \XwhoopsCorePreload::OWNER,
            'the owner token must equal the module dirname'
        );
    }
}

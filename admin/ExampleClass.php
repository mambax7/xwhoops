<?php declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');

class ExampleClass
{
    public function __construct(private readonly string $msg)
    {
    }

    /**
     * Fails on purpose, so an administrator can see a real xWhoops screen.
     *
     * Reached from Admin -> xWhoops -> Example, and only while XOOPS_DEBUG is on. The
     * chain admin/index.php -> number1 -> number3 -> number2 -> here exists to put more
     * than one frame on the stack, and the catch/rethrow puts a PREVIOUS exception on it
     * too -- both of which the screen renders and neither of which a one-line `throw`
     * would demonstrate.
     */
    public function flawedMethod(): void
    {
        try {
            // The class is missing deliberately: an Error thrown by PHP itself is a more
            // honest demonstration than one this file invents. Ignored for analysis with
            // the reason attached, rather than baselined -- a baseline entry would say
            // nothing about why, and the next person would delete the demo as a mistake.
            /** @phpstan-ignore class.notFound */
            new \NoSuchClass($this->msg);
        } catch (Throwable $e) {
            throw new RuntimeException('Example Exception, follow how we got here.', 100, $e);
        }
    }
}

<div id="help-template" class="outer">
    <{include file=$smarty.const._MI_XWHOOPS_HELP_HEADER}>

    <h4 class="odd">DESCRIPTION</h4> <br>

    <p>
        xWhoops provides extended error messages for XOOPS using <a href="https://github.com/filp/whoops" class="external" target="_blank">Whoops</a>.
        It is primarily intended for developers.<br> <br>
    </p>
    <p>
        xWhoops messages are only available to user groups that the administrator has <em>selected</em>,
        so on failure, messages can safely be much more informative and detailed.<br> <br>
    </p>

    <h4 class="odd">INSTALL/UNINSTALL</h4>


    <p>
        <br> <br> To add <strong>xwhoops</strong> to your XOOPS 2.7.0+ site, follow these steps<br> <br>

         <ul>
        <li>download the distribution archive of your choice</li>
        <li>extract the archive into your system's modules directory</li>
        <li>rename the new directory to xwhoops</li>
        <li>open a terminal into that directory and execute  <code>  composer install</code>
        </li>
        <li>install the xwhoops module in the system administration module page</li>
        <li>grant access by selecting groups in the permissions section</li>
    </ul>


    <br> <br>
        Detailed instructions on installing modules are available in the
        <a href="https://xoops.gitbook.io/xoops-operations-guide/" target="_blank">Chapter 2.12 of our XOOPS Operations Manual</a>
    </p>
    <br> <br>

    <h4 class="odd">SWITCHING IT ON</h4> <br>
    <p class="even">
    <p>
        On XOOPS <strong>2.7.2 and earlier</strong>, installing the module and granting the
        permission is the whole setup: xWhoops registers itself during the boot whenever
        XOOPS debug is on and an authenticated site administrator is looking.
    </p>
    <p>
        On XOOPS <strong>2.7.3 and later</strong> this changed, and for a good reason. PHP
        has exactly one error handler and one exception handler, and whoever registers last
        owns them — so before 2.7.3, which error screen you ended up with was decided by
        module weight and id, and reinstalling something unrelated could silently change it
        with nothing anywhere reporting the fact. 2.7.3 lets a site declare an owner, and
        offers the handlers to that one module at the end of the boot.
    </p>
    <p>
        What that means in practice:
    </p>
    <ul>
        <li><strong>Installing the module claims the error screen</strong>, if no other
            provider holds it. XOOPS ships <code>'error_screen' =&gt; 'auto'</code>, meaning
            "the first error-screen module installed", so normally nothing else is needed.</li>
        <li><strong>Only <code>xoops_data/data/debug.php</code> activates it.</strong>
            Admin &rarr; Preferences &rarr; Debug Mode does not, deliberately: writing a
            file on the server is a stronger credential than holding an admin session, and
            an error screen shows source, paths and request data. Copy
            <code>debug.dist.php</code> beside it and set <code>'enabled' =&gt; true</code>.
            With the module installed but no <code>debug.php</code>, XOOPS reports the
            status <code>dormant</code> and says so.</li>
        <li><strong>Only one module can own it.</strong> If xTracy is also installed,
            whichever was installed first keeps it. To hand it over: deactivate the holder
            and update this module, or uninstall the holder and reinstall this one, or name
            your choice with <code>'error_screen' =&gt; 'xwhoops'</code> in
            <code>debug.php</code>, which beats anything recorded.</li>
        <li><strong>Deactivating does not pass the screen to another module.</strong> The
            site falls back to core error handling and reports <code>unclaimed</code>.
            Ownership only ever changes when somebody asks for it.</li>
    </ul>
    <p>
        If something is not appearing, the site tells you why: XOOPS publishes
        <code>XOOPS_ERROR_SCREEN_OWNER</code>, <code>_SOURCE</code>, <code>_STATUS</code>
        and <code>_MESSAGE</code> on every request, with values such as <code>active</code>,
        <code>dormant</code>, <code>disabled</code> (the module ran and chose not to
        register — the message says which reason), <code>missing</code> (run
        <code>composer install</code> inside the module) or <code>unclaimed</code>.
    </p>
    </p>

    <h4 class="odd">OPERATING INSTRUCTIONS</h4><br>
    <p class="even">
    <p>
        When an error occurs, xWhoops will display a screen with information about the error.
        <br>
        The Whoops display contains 4 main sections.
    <ul>
        <li><em>top left</em> is the error that was encountered</li>
        <li><em>lower left</em> shows the stack frames, the trace of path of the processing that resulted in the error.</li>
        <li><em>top right</em> shows the code for the currently selected stack frame item. Select a new stack frame to see the related code.</li>
        <li><em>lower right</em> shows environment information such as request parameters, session information, etc.</li>
    </ul>
    Note: if the XoopsLogger is enabled, MySQL queries will be shown in the Environment &amp; details section.
    </p>
    <p>
        xWhoops takes the exception and shutdown handlers only, and hands the error handler
        straight back, so notices, warnings and deprecations still reach XoopsLogger and the
        DebugBar module.
    </p>
        Detailed instructions on configuring the access rights for user groups are available in the
        <a href="https://xoops.gitbook.io/xoops-operations-guide/" target="_blank">Chapter 2.8 of our XOOPS Operations Manual</a><br> <br></p>

    <h4 class="odd">TUTORIAL</h4> <br>

    <p class="even">
        Tutorial has been started, but we might need your help! Please check out the status of the tutorial <a href="https://xoops.gitbook.io/xwhoops-tutorial/" target="_blank">here </a>.
        <br><br>To contribute to this Tutorial, <a href="https://github.com/XoopsDocs/xwhoops-tutorial/" target="_blank">please fork it on GitHub</a>.
        <br> This document describes our <a href="https://xoops.gitbook.io/xoops-documentation-process/" target="_blank">Documentation Process</a> and it will help you to understand how to contribute.
        <br><br>
        There are more XOOPS Tutorials, so check them out in our <a href="https://www.gitbook.com/@xoops/" target="_blank">XOOPS Tutorial Repository on GitBook</a>.
    </p>


    <h4 class="odd">TRANSLATIONS</h4> <br>
    <p class="even">
        Translations are on <a href="https://www.transifex.com/xoops/" target="_blank">Transifex</a> and in our <a href="https://github.com/XoopsLanguages/" target="_blank">XOOPS Languages Repository on GitHub</a>.</p>

    <h4 class="odd">SUPPORT</h4> <br>
    <p class="even">
        If you have questions about this module and need help, you can visit our <a href="https://xoops.org/modules/newbb/viewforum.php?forum=28/" target="_blank">Support Forums on XOOPS Website</a></p>

    <h4 class="odd">DEVELOPMENT</h4> <br>
    <p class="even">
        This module is Open Source and we would love your help in making it better! You can fork this module on <a href="https://github.com/XoopsModules27x/xwhoops" target="_blank">GitHub</a><br><br>
        But there is more happening on GitHub:<br><br>
        - <a href="https://github.com/xoops" target="_blank">XOOPS Core</a> <br>
        - <a href="https://github.com/XoopsModules27x" target="_blank">XOOPS Modules</a><br>
        - <a href="https://github.com/XoopsThemes" target="_blank">XOOPS Themes</a><br><br>
        Go check it out, and <strong>GET INVOLVED</strong>

    </p>

</div>

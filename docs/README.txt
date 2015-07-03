
README.txt

CodeTrack is a web-based system for reporting and managing bugs and other software
defects, as well as tracking change requests.  The interface is W3C-compliant, and
was specifically designed for cross-browser and cross-platform friendliness.
The engine is written in PHP, and data are stored in simple XML text files;
No database or mail server is required, although there is an option for e-mail
notifications using the built-in mail functions of PHP.  The goals of the
project are to offer a simpler alternative to applications like Bugzilla and Jitterbug,
using a professional-quality front-end that can be set up in under 10 minutes.

See http://www.openbugs.org/ for the latest version of CodeTrack and all documentation.

This software is released under the GPL and is copyrighted by Kenneth White, 2001
Complete license is LICENSE.txt in the docs directory


See INSTALL.txt for a painless 10 minute configuration.


For the completely impatient:

1. Apache owner needs read, write, and execute on all codetrack directories

2. Initial login is:  admin/codetrack

3. For email to work, you need to set CT_RETURN_ADDRESS in config.inc.php
    (see INSTALL.txt first, then TROUBLESHOOTING.txt for more help)

4. To customize guest and user access, menu choices, options, etc. see config.inc.php

5. To customize the interface, use style/codetrack_w3c.css.


If you see giant fonts and an ugly white login screen, Apache probably can't read
content from ./style ./javascript or ./images, in which case you should read
INSTALL.txt again.

We hope you find CodeTrack useful.  Send questions to codetrack@openbugs.org

-kenn




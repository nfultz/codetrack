<?php

##########################################
#   SET CODETRACK CONSTANT DEFINITIONS   #
##########################################

# Sets the reply-to address on email notifications, def. is "codetrack@localhost"
DEFINE( "CT_RETURN_ADDRESS", "codetrack@example.com" );

# Disabling will remove all rendered mail-specific widgets, default is TRUE
DEFINE( "CT_ENABLE_EMAIL", TRUE );

# Pre-selects a project on login page. Must be exact match to project name
DEFINE( "CT_DEFAULT_PROJECT", "CodeTrack" );

# Pre-selects a project lead for new Projects. Must be exact match to a user's FULL name
DEFINE( "CT_DEFAULT_PROJECT_LEAD", "Kenn White" );

# Force client logout after x seconds of inactivity, def. is 28800 (8 hrs), NO quotes
DEFINE( "CT_DEFAULT_PAGE_TIMEOUT", 28800 );

# Increase to 8 or more for increased security on public sites, def is 1, NO quotes
DEFINE( "CT_MIN_PASSWORD_LENGTH", 1);

# Set to TRUE to prevent Developers from closing/deleting a bug. Only users with
#  explicit authority (CT_QA_WHO_CAN_CLOSE) are allowed to do so, def is FALSE
DEFINE( "CT_QA_ENFORCE_PRIVS", FALSE );

# If CT_ENFORCE_QA_PRIVS is enabled (above), then only these roles can close/delete a bug, MUST quote
DEFINE( "CT_QA_WHO_CAN_CLOSE", "QA,Manager,Admin" );

# Set to TRUE to allow users with role of Guest to edit existing issues, def is FALSE 
DEFINE( "CT_GUESTS_CAN_EDIT", FALSE );

# Set to TRUE to allow users with role of Guest to create new issues, def is FALSE
DEFINE( "CT_GUESTS_CAN_CREATE", FALSE );


# ____________________________________________________________________________
#
#    95% of customizations are *above* this section. Power users, go crazy...
# ____________________________________________________________________________
#


# Envelope reply header, for aggressive anti-spam SMTPs (PHP v. 4.05+ only), def. is TRUE
DEFINE( "CT_USE_EXTRA_MAIL_HEADER", TRUE );

# See SECURITY.txt for more info on CodeTrack crypto.
#  *HIGHLY* recommended to change this (alpha+numbers only)
DEFINE( "CT_PRIVATE_KEY", "c2s8Syye23k12VBF436U6l988785GBSAQ2jkgyceBrdyhhnrBrdtxxSwe67r7Bu3k212aAQW3fx" );


# Next is the only date field that should be customized.  See date() reference on php.net
#  Do NOT change in the middle of a project, default is "j-M-Y g:i A"
DEFINE( "CT_DATE_FORMAT", "j-M-Y g:i A" );

# Show/hide user email & phone #'s on Tools page (user view), def. is TRUE
DEFINE( "CT_DISPLAY_ADDRESSES", TRUE );


# Show balloon messages that appear over widgets & links, default is TRUE
DEFINE( "CT_ENABLE_TOOLTIPS", TRUE );


# Set next to customize which columns are displayed on the home page.  Warning: Test at resolutions most of your
#   users will actually use. I.e., if you specify too many columns everything at, say, 800x600 will wrap, creating
#   two lines for every entry. Default is  "Project,ID,Status,Severity,Summary,Last_Updated,Assign_To,Developer_Response"

DEFINE( "CT_HOME_PAGE_DATA_COLUMNS",
   "Project,ID,Status,Severity,Summary,Last_Updated,Assign_To,Developer_Response" );


# Only allow attachment uploads of these file types on issue reports
# (i.e., not .php or .pl -- Yikes! or .mp3, etc. )
#  Let in that which is known, block all others.  Add to the list, don't just disable.
#  Not case sensitive. Note: NO SPACES BETWEEN ENTRIES!

DEFINE( "CT_LEGAL_ATTACHMENTS",
   "jpg,jpeg,gif,bmp,png,tiff,doc,xls,ppt,mdb,csv,txt,rtf,xml,ps,pdf,zip,tar,tgz,gz,bzip,bz2" );


# Disabling potenially opens a major security hole (i.e., uploading a .php file), def. is TRUE
DEFINE( "CT_ENFORCE_LEGAL_ATTACHMENTS", TRUE );

# Enable to attempt client-side (js) validation of email address on create new user, def. is TRUE
DEFINE( "CT_PEDANTIC_VALIDATION", TRUE);

# Semi-fugly work-around b/c we can't simply append XML files, def. is 64
DEFINE( "CT_LAST_LINE_SIZE", 64 );

# In seconds; I/O sucks if this needs adjusted up, default is 10
DEFINE( "CT_LOCKFILE_TIMEOUT", 10 );

# Only return this many search results, default is 100
DEFINE( "CT_MAX_SEARCH_RESULTS", 100 );

# Bytes, should be <= upload_max_filesize in php.ini, def. is 2000000 (2MB)
DEFINE( "CT_UPLOAD_MAX_FILESIZE", 2000000 );

# Issue description max. length, b/c of an old Oracle varchar2 bug, def. is 1998
DEFINE( "CT_MAX_DESCR_SIZE", 1998 );

# Maximum length of an attachment filename (we have to pick something)
DEFINE( "CT_MAX_ATTACHMENT_FILENAME_SIZE", 64);

# When exporting to CVS/SQL, translate newlines to this (2 chars), def. is "__"
DEFINE( "CT_NEWLINE_SYMBOL", "__" );


# No Valid W3C-compliant CSS or HTML equivalent for this in Netscape 4.x textareas

DEFINE( "CT_OPTIONAL_UGLY_NN4_HACK", "" );
#DEFINE( "CT_OPTIONAL_UGLY_NN4_HACK", " WRAP=VIRTUAL " );  # Uncomment one of these, default is "" (W3C compliant)

#
# The next block of constants drives most of the form and drop down (DD) choices in CodeTrack.
# Customize to your heart's content, but note that THERE SHOULD BE NO SPACES BETWEEN CHOICES!
# *Adding* choices is always fine, but be careful about *modifying* existing entries in the
# middle of a project, as you risk orphaning existing records.  It might help to think of all
# of these lists as master lookup tables. Note: There's NO risk in changing the *x_DEFAULT_x* entries.
#
# A leading comma-only entry (example:  ",Fixed..." ) creates a blank option on drop down and
# allows for default null value choice.  See CUSTOMIZING.txt for more info.
#

DEFINE( "CT_ACCESS_LEVELS", "Developer,Analyst,QA,Manager,Admin,Guest");
DEFINE( "CT_DEFAULT_ACCESS_LEVEL", "Developer");

# We recommend keeping this "" to force proactive severity ratings
DEFINE( "CT_BUG_SEVERITIES",  "Fatal,Serious,Minor,Cosmetic,Change Req.");
DEFINE( "CT_DEFAULT_SEVERITY", "");

DEFINE( "CT_BUG_STATUSES", "Open,Closed,Deferred");

DEFINE( "CT_DEVELOPER_RESPONSES",
 ",Analyzing,As Designed,Cannot Recreate,Duplicate Bug,Enhancement,Fixed,Not Fixed");

# We recommend keeping this "" to force proactive status updates
DEFINE( "CT_DEFAULT_DEVELOPER_RESPONSE", "");


DEFINE( "CT_MAJOR_BROWSERS", ",IE 6.0,IE 5.5,IE 5.0,IE 4.0,NN 6,NN 4.7,AOL 9,AOL 8," .
   "AOL 7,AOL 6,Opera 7,Opera 6,Opera 5,FireFox,Safari,IE Mac,(see note),N/A" );
DEFINE( "CT_DEFAULT_BROWSER", "IE 6.0");

DEFINE( "CT_MAJOR_OS", ",WinXP SP2,WinXP SP1,Win2K SP4,Win2K SP3,Win2K SP2,Win2K SP1," .
   "WinNT SP6,WinNT SP5,WinNT SP4,Win98 SE,Win98 A,Win95 B,Win95 A," .
   "Linux,FreeBSD,OpenBSD,NetBSD,Solaris 8,Mac OSX");
DEFINE( "CT_DEFAULT_OS", "");


#
# Move these to a non-htdocs location for increased security, but make sure they're writable by Apache
# If using alternate locations, change implied . path to absolute, and only use FORWARD slashes!
#
# GOOD EXAMPLES:  "db"  "c:/codetrack_data"  "/home/httpd/nonbrowsable/ct"  "/var/codetrack"
#

DEFINE( "CT_XML_DIRECTORY",         "xml");    # Do NOT add trailing slashes, default is "xml"
DEFINE( "CT_XML_BACKUPS_DIRECTORY", "backups");   # Do NOT add trailing slashes, default is "backups"


# Set to TRUE for fabulous debugging.
#  Once enabled, for any given page foo, add &debug=y to any URL, ex.: codetrack.php?page=home&debug=y

DEFINE( "CT_ENABLE_AD_HOC_DEBUG", FALSE);     #  Default is FALSE



# *********  FROM THIS POINT DOWN, DO NOT CHANGE ANY CONSTANTS  **********


# Required for product activation.  (Just kidding!)
DEFINE( "CT_CODE_VERSION", "v. 0.99.3" );

# Arbitrary human-readable constants, see parse_xml_file() below for more details
DEFINE( "CT_ASCENDING", 1);
DEFINE( "CT_DESCENDING", 2);
DEFINE( "CT_DROP_HISTORY", 1);
DEFINE( "CT_KEEP_HISTORY", 0);

# Slashes ARE correct both for *nix AND Windows
# To customize, change CT_XML_DIRECTORY, NOT those below.
DEFINE( "CT_XML_BUGS_FILE",   CT_XML_DIRECTORY . "/bugs.xml");
DEFINE( "CT_XML_USERS_FILE",   CT_XML_DIRECTORY . "/users.xml");
DEFINE( "CT_XML_PROJECTS_FILE",  CT_XML_DIRECTORY . "/projects.xml");
DEFINE( "CT_XML_PERMISSIONS_FILE", CT_XML_DIRECTORY . "/permissions.xml");


?>
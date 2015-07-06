<?php

#
#  CodeTrack v. 0.99.3  - A tool to track software defects, bug reports, and change requests
#
#  CodeTrack is a web-based system for reporting and managing bugs and other software
#  defects.  The interface is W3C-compliant, and was specifically designed for cross-browser
#  and cross-platform friendliness.  The engine is written in PHP, and data are stored in
#   simple XML text files.  No database or mail server is required, although there is an option
#  for e-mail notifications using a major SMTP agent (such as sendmail or Qmail).  The
#  goals of the project are to offer a simpler alternative to applications like Bugzilla and
#  Jitterbug, using a professional-quality front-end that can be set up in under 10 minutes.
#
#  See http://www.openbugs.org/ for the latest version of CodeTrack and all documentation.
#
#  This software is released under the GPL and is copyrighted by Kenneth White, 2001-2006
#  Complete license is LICENSE.txt in the docs directory
#
#  It is released under the terms of the GNU General Public License as published by
#  the Free Software Foundation; either version 2 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program; if not, write to the Free Software
#  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA
#
#  Note: This document uses tab stops set to 3 (saves 25K of space!)
#


# IF CLIENT PRESSES STOP ON THE BROWSER, DON'T ALLOW FILE UPDATES TO CHOKE. DO *NOT* CHANGE!

ignore_user_abort(TRUE);


# SET ERROR LEVEL TO "E_ALL" ONLY DURING DEBUGGING; PRODUCES OVERLY-PEDANTIC PHP WARNINGS. BELOW IS REQ. FOR PHP 4.2+

error_reporting(E_ERROR);
#error_reporting(E_ALL);


# All customizations in the file below

require_once("config.inc.php");


##################################
#  BEGIN MAIN CODE BLOCK  #
##################################


$just_posted = ($_SERVER["REQUEST_METHOD"] == 'POST');


# INITIALIZE GLOBAL VARIABLES


$query_string_g = $_SERVER["QUERY_STRING"];

$debug_g = FALSE;
if ( ( CT_ENABLE_AD_HOC_DEBUG ) and ( isset($_GET["debug"]) or isset($_POST["debug"])) )
 $debug_g = TRUE;

$xml_array_g       = array();
$current_session_g = array();


# SANITY CHECK. WAS ANYTHING REQUESTED?
# DEPENDING ON PAGE, DATA COULD COME EITHER WAY

$page = $_GET["page"];

if (!$page)
 $page = $_POST["page"];

if (!$page) {
 header("Location: codetrack.php?page=login");
 exit;
}

# JUST BECAUSE YOU'RE PARANOID DOESN'T MEAN THEY AREN'T AFTER YOU...
$page = eregi_replace("[^a-z]+", "", $page);


#
# FOR EVERY PAGE BESIDES LOGIN/LOGOUT, VALIDATE THAT A SESSION
# COOKIE EXISTS AND IF SO, EXTRACT SESSION AND PREFERENCE DATA
#

if ( ($page != 'login') and ($page != 'logout') ) {

 $session_cookie = $_COOKIE["codetrack_session"] ;

 $current_session_g = unserialize(base64_decode(urldecode($session_cookie)));

 if ($current_session_g) {

  $full_name = $current_session_g["user_full_name"];
  $username  = $current_session_g["username"];

  $valid_sid = calc_session_id( $full_name );

  if ( !$valid_sid or ($valid_sid != $current_session_g["id"]) ) {
   header( "Location: codetrack.php?page=login&session=expired&user=$username" );
   exit;
  }
 }
 else {

  # Page other than login requested, but no session cookie present. No bueno!
  #  Either cookies are being blocked (check the referring page), or a direct page bookmark was followed

  if ( stristr($_SERVER['HTTP_REFERER'], "codetrack.php") )
   header( "Location: codetrack.php?page=login&nocookies=true" );
  else
   header("Location: codetrack.php?page=login&session_authentication=missing");
  exit;
 }

}


# IF WE'VE MADE IT HERE, THE SESSION ID WAS FOUND AND IS AUTHENTIC.

if ($debug_g) {
 print "<tt><pre>Raw Cookie:\n$session_cookie \n\nSession Info: \n";  var_dump($current_session_g);  print "</pre></tt><br />\n";
}


# RETRIEVE USERS
# Make complete copy of users (by value) & sort on user last name

parse_xml_file( CT_XML_USERS_FILE , CT_DROP_HISTORY, "Last_Name", CT_ASCENDING );
$user_table = $xml_array_g;


# RETRIEVE USER ACCESS LIST
# Make complete copy of user access list (by value)

parse_xml_file( CT_XML_PERMISSIONS_FILE , CT_DROP_HISTORY, "Project_ID", CT_ASCENDING);
$permission_table = $xml_array_g;

/*
foreach ($user_table as $user)
 if ($user["Full_Name"] == $full_name )  {
  $user_id = $user["ID"];
  break;
 }
*/


# RETRIEVE PROJECTS
# Make complete copy of projects (by value) & sort on project title

parse_xml_file( CT_XML_PROJECTS_FILE, CT_DROP_HISTORY, "Title", CT_ASCENDING );
$project_table = $xml_array_g;
$project_cnt = sizeof($project_table);

# Unless this user is an administrator, pare back the project list to what's authorized
/*
 $all_project_cnt = sizeof( $xml_array_g );

if ( $current_session_g["role"] != 'Admin' )
  for ($p=0; $p < $all_project_cnt; $p++)
   if (! authorized_user( $xml_array_g[$p]["ID"], $user_id, &$permission_table ) )
    unset($xml_array_g[$p]);

 usort($xml_array_g, create_function('$a,$b', 'return strcasecmp($a["ID"],$b["ID"]);'));

if (! $project_cnt )
 die("<br /><br /><center>You have not been authorized to access any project. Please contact support.");
*/


###########################################################
#   MAIN PROGRAM LOGIC BLOCK: WHAT PAGE TO DISPLAY?     #
###########################################################


switch ($page) {


 # LOGIN PAGE & AUTHENTICATION.  IN: $userLogin[] array , $failed flag

 case "login":

  if ($just_posted) {

   $user_login = $_POST["userLogin"];

   $index = "";

   if ( valid_password($user_login, $user_table, $index) ) {

    # If matched, user index is passed back by reference

    $user_entry = $user_table[$index];
    $selected_project = $user_login["project_name"];

    $cookie = build_new_session_cookie ($user_entry, $selected_project);

    if ( $user_login["password"] == 'codetrack' )    # First time login for Administrators
     set_session_cookie_load_page( $cookie, "changepassword" );
    else
     set_session_cookie_load_page( $cookie, "home" );

    exit;  # We have to stop execution following set_session...()
   }
   else {
    header("Location: codetrack.php?page=login&failed=y");
    exit;
   }
  }
  else {

   $prev_login_failed = FALSE;
   if ( isset($_GET["failed"])  )
    $prev_login_failed = TRUE;

   $session_expired = FALSE;
   if ( isset($_GET["session"])  )
    $session_expired = TRUE;

   $no_cookies = FALSE;
   if ( isset($_GET["nocookies"])  )
    $no_cookies = TRUE;

   draw_login_page( $project_table, $prev_login_failed, $session_expired, $no_cookies );
   exit;
  }



 # LOGOUT OF CODETRACK. IN: $origin

 #  Note, page=timeout is called when client META refresh expires; code below in turn calls login,
 #   passing expiration flag.  Could call page=login&session=expired directly from META,
 #   but ampersands in META clause (escaped or not) break many 4.x browsers.

 case "logout":
 case "timeout":

  print "<html><body><script type='text/javascript'> " .
    "document.cookie=\"codetrack_session=;\";  " .
    "location.replace('codetrack.php?page=login";

  if ( $page == "timeout" )
   print "&session=expired";

  print "');</script></body></html>";
  exit;
 break;



 # PRESENT NEW BUG/ISSUE FORM. IN: (NONE)

 case "newIssue":
  draw_page_top( $page );
  draw_add_edit_bug_form($project_table, $user_table);
  draw_page_bottom();
 break;



 # PRESENT CHANGE PASSWORD FORM. IN: (NONE)

 case "changepassword":
  draw_page_top( $page );
  draw_change_password_form( $user_table );
  draw_page_bottom();
 break;



 # QUICK-AND-DIRTY FULL TEXT SEARCH. IN: $pattern, $search_options[] array

 case "searchissue":
  draw_page_top( $page );
  search_bugs( $_GET["pattern"], $_GET["search_options"] );
  draw_page_bottom();
 break;



 # SAVE POSTED UPDATED PASSWORD.  IN: $user_data[] array, $old_pw

 case "savepassword":
  if ( $just_posted ) {
   draw_page_top( $page );
   $user_data = $_POST["user_data"];
   $old_pw    = $_POST["old_pw"];
   update_user( $user_data, $user_table, $old_pw );
   draw_page_bottom();
  }
 break;



 # NEW OR EDITED BUG JUST SUBMITTED.
 # IN:  $Attachment[] data array + upload file, $id, $bug_data[] array, $original_submit_time, $cc_list[] array


 case "saveissue":

  if ( $just_posted ) {     # We only allow POSTed bug forms

   if ( $_FILES["Attachment"]["size"] > 0 )
    $attachment_data = $_FILES["Attachment"];
   else
    $attachment_data = array();

   $id        = $_POST["id"];
   $bug_data     = $_POST["bug_data"];
   $original_submit_time = $_POST["original_submit_time"];
   $send_mail      = $_POST["send_mail"];
   $cc_list      = $_POST["cc_list"];

   # Alias for generic data array by reference for speed, so do not call not parse_xml again

   $xml_array_g = array(); # Lost global scope inside function, so must reinit

   parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING );
   $bug_table = &$xml_array_g;

   draw_page_top( $page );
   save_bug_data( $id, $bug_data, $attachment_data, $original_submit_time,
        $send_mail, $cc_list, $bug_table, $user_table );
   draw_page_bottom();
  }

 break;



 # DOWNLOAD/EXPORT DATA FILES.  IN: $export_options, browser data

 case "export":

  if ($just_posted) {

   $export_options = $_POST["export_options"];
   $user_agent    = $_SERVER["HTTP_USER_AGENT"];
   export_database( $export_options, $user_agent );
   exit;
  }
  else {
   draw_page_top( $page );
   draw_export_options( $current_session_g["project"] );
   draw_page_bottom();
  }

 break;



 # PRESENT TOOLS MENU.  IN: (none)

 case "tools":

  draw_page_top( $page );
  draw_tools_page();
  draw_page_bottom();

 break;



 # PRESENT EXECUTIVE SUMMARY REPORT.  IN: (none)

 case "summary":

  draw_page_top( $page );
  create_summary( $project_table );
  draw_page_bottom();

 break;



 # PRINT AUDIT LIST OF DELETED BUG LINKS.  IN: (none)
 # Note, this is currently only accessible via admin, but it should not be particularly sensitive

 case "deletedissues":

  draw_page_top( $page );
  parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING );
  $bug_table = &$xml_array_g;      # Copy by reference, so don't call parse_xml_file again
  print_deleted_bugs( $bug_table );
  draw_page_bottom();

 break;



 # DISPLAY AUDIT TRAIL.  IN: $id

 case "audit":

  $id = $_GET["id"];

  if (!$id)
   die("Fatal: No issue id specified for audit record!");

  # Alias xml data array by reference for speed, so do NOT call parse_xml again
  parse_xml_file( CT_XML_BUGS_FILE, CT_KEEP_HISTORY );

  $data_array = &$xml_array_g;

  draw_page_top( $page );
  print_audit_trail($data_array, "$id");  # Force string promotion on id
  draw_page_bottom();

 break;



 # VIEW AN ISSUE.  IN: $id

 case "viewissue":

  $id = $_GET["id"];

  if ( $id ){
   draw_page_top( $page );
   draw_view_bug_page( "$id" );
   draw_page_bottom();
   break;
  }
  else
   die("<center><br /><br /><h2>Fatal: No issue ID specified to view!");

 break;



 # EDIT AN ISSUE.  IN: $id

 case "editissue":

  $id = $_GET["id"];

  if ( $id ){
   draw_page_top( $page );
   draw_add_edit_bug_form( $project_table, $user_table, "$id" );
   draw_page_bottom();
   break;
  }
  else
   die("<center><br /><br /><h2>Fatal: No issue ID specified to edit!");

 break;



 # SHOW ALL ACTIVE USERS.  IN: (none)

 case "users":

  draw_page_top( $page );
  draw_table( $user_table, "Address Book", '' );
  draw_page_bottom();

 break;



 # SHOW ALL ACTIVE PROJECTS.  IN: (none)

 case "projects":

  draw_page_top( $page );
  draw_table($project_table, "Active Projects", '');  # Tied to logic in Prj Links
  draw_page_bottom();

 break;



 # DEFAULT PROJECT CHANGE FROM HOTLIST.  IN: $redir, $project;

 case "changeproject":

  $redir = $_GET["redir"];
  $project = $_GET["project"];

  $cookie = build_updated_session_cookie($project, $current_session_g);
  set_session_cookie_load_page($cookie, $redir);
  exit;
 break;



 # HOME PAGE (ISSUE LIST).  IN: $project, $all

 case "home":

  $project = $_GET["project"];
  $all   = $_GET["all"];

  parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING);

  # Alias for generic data array by reference for speed, so do not call not parse_xml again

  $bug_array = &$xml_array_g;

  if ( isset($project) )
   $filter["Project"] = $project;
  else
   $filter["Project"] = $current_session_g["project"];

  $t = "title='Show me only the open issues'";

  if ( isset ($all) )
   $title = "All <a href='codetrack.php?page=home' $t>" . $filter["Project"] . "</a> Issues";
  else {
   $filter["Status"] = "Open";
   $title = "Open <a href='codetrack.php?page=home&amp;all=1' $t>" . $filter["Project"] . "</a> Issues";
  }

  draw_page_top( $page );
  draw_table( $bug_array, $title, $filter, $project_table );
  draw_page_bottom();

 break;



 # FILTERED ISSUE REPORT

 case "filter":

  $filter  = $_GET["filter"];

  parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING);

  # Alias for generic data array by reference for speed, so do not call not parse_xml again

  $bug_array = &$xml_array_g;

  $report_title='';

  foreach ($filter as $filter_name => $filter_value) {
   if (!$filter_value)
    unset($filter[$filter_name]);
   else {
    if (!$report_title)
     $report_title = 'Custom Report:<br /><em>All issues matching criteria: &nbsp;';
    else
     $report_title .= ', &nbsp;';

    $report_title .= strtr($filter_name, "_", " ") .
    ( (strstr($filter_name, "Submitted_By")) ? "" : " =" ) . " $filter_value";
   }
  }
  if (!$report_title)
   $report_title = 'Custom Report: <em>Issues from All Projects';

  draw_page_top( $page );
  draw_table( $bug_array, "$report_title</em>", $filter, $project_table );
  draw_page_bottom();

 break;



 # DRAW REPORTS AND SEARCH PAGE.  IN: (NONE)

 case "reports":
  draw_page_top( $page );
  draw_reports_page($project_table, $user_table);
  draw_page_bottom();
 break;



 ###########################################
 #    BEGINNING OF ADMIN-ONLY FUNCTIONS    #
 ###########################################



 # CREATE BACKUPS OF KEY XML FILES.  IN: (NONE)

  case "dobackup":
   admin_check();
   create_xml_backups();
   header("Location: codetrack.php?page=backup&success=1");
  exit;



  # PRESENT ADMIN TOOLS STATIC LINKS PAGE. IN: (NONE)

  case "adminLinks":
   admin_check();
   draw_page_top( $page );
   draw_admin_page();
   draw_page_bottom();
  break;



  # PRESENT PROJECT ACCESS ADMIN FORM.  IN: (NONE)

  case "projectaccess":
   admin_check();
   draw_page_top( $page );
   draw_project_access_form( $project_table, $user_table, $permission_table );
   draw_page_bottom();
  break;



  # PRESENT XML DATA FILE BACKUP PAGE.  IN: $success

  case "backup":
   admin_check();
   $success = $_GET["success"];
   draw_page_top( $page );
   draw_xml_backups_page( $success );
   draw_page_bottom();
  break;



  # SAVE USER-BY-PROJECT PERMISSION SETTINGS.  IN: $permission_data[] array

  case "savepermissions":
   admin_check();
   $permission_data = $_POST["permission_data"];
   draw_page_top( $page );
   save_permission_data( $permission_data, $permission_table, $project_table );
   draw_page_bottom();
  break;



  # PRESENT ADD NEW USER ADMIN FORM.  IN: (NONE)

  case "adduser":
   admin_check();
   draw_page_top( $page );
   draw_user_maintenance_form();
   draw_page_bottom();
  break;



  # PRESENT ADD NEW PROJECT ADMIN FORM.  IN: (NONE)

  case "addproject":
   admin_check();
   draw_page_top( $page );
   draw_project_maintenance_form( $user_table );
   draw_page_bottom();
  break;



  # SAVE POSTED NEW USER DATA.  IN: $user_data[] array, $notify_user flag

  case "saveuser":
   admin_check();
   if ($just_posted) {
    $user_data = $_POST["user_data"];
    $notify_user = $_POST["notify_user"];
    $apache_vars = $_SERVER;
    draw_page_top( $page );
    save_user_data( $user_data, $user_table, $notify_user, $apache_vars );
    draw_page_bottom();
   }
  break;



  # SAVE POSTED NEW PROJECT DATA.  IN: $project_data[] array

  case "saveproject":
   admin_check();
   if ($just_posted) {
    $project_data = $_POST["project_data"];
    draw_page_top( $page );
    save_project_data( $project_data, $project_table );
    draw_page_bottom();
   }
  break;


 ###################################
 #   END OF ADMIN-SPECIFIC PAGES   #
 ###################################


 # GENERIC BIT BUCKET FOR BOGUS PAGE REQUEST

 default:
  no_cache();

  $ref = htmlentities($_SERVER['HTTP_REFERER']);

  print '<html><body><meta http-equiv="refresh" content="6; url=' .
    'codetrack.php?page=login&invalidpage=' . $page .'"><center><h4><br /><br /><br />' .
    "Invalid page requested '$page'<br /><br />Referring page: $ref <em><br /><br />" .
    "Redirecting to login page in six seconds. </em></center></h4></body></html>";
 break;
}
exit;  # EXECUTION STOPS AFTER CASE SWITCH IS PROCESSED




######################################
###      BEGIN FUNCTION DEFS       ###
######################################



FUNCTION admin_check() {

 GLOBAL $current_session_g;

 if ( $current_session_g["role"] != 'Admin' )
  die("<br /><br /><br /><center><h2>You are not an administrator.");

  # zztop some good logging here?

}



FUNCTION append_xml_file_node( $filename, $node, $closing_root_tag ) {

 brute_force_lock( $filename );

 # Open file in append R+W mode and request an exclusive blocking lock on the file

 $fh = fopen($filename, 'rb+')  or
  die("<br /><center><h3>Fatal: Couldn't append to '$filename' ! Check that it's writable by the Apache owner.");

 fseek($fh, 0, SEEK_END);
 $fsize = ftell($fh);         # We don't have to worry about nasty stat-caused caching issues now

 fseek($fh, -CT_LAST_LINE_SIZE, SEEK_END);  # Existing root tag should be somewhere in last 1K of file

 $last_line = fread($fh, CT_LAST_LINE_SIZE);

 $offset  = CT_LAST_LINE_SIZE - strrpos($last_line, $closing_root_tag );

 $append_location = $fsize - $offset;

 fseek($fh, $append_location, SEEK_SET);

 $node .= "\n$closing_root_tag";

 #die( "<pre>" . htmlspecialchars("offset: $offset trun: $trunc_loc last: *$last_line* node: $node") );

 # Translate form & XML tag CRs ( ? + \n  --->  ? + \r + \n ) for friendly *nix/Windows transfers

 $node = ereg_replace( "([^\r])\n", "\\1\r\n", $node);

 if ( fwrite($fh, $node) == -1 )
  die("Fatal: Unable to add XML node to '$filename' !");

 fclose($fh);

 brute_force_lock_release( $filename );
}



FUNCTION authorized_user( $project_id, $user_id, &$permission_table ) {

 # print "<pre>"; var_dump($permission_table);
 # print "PI: $project_id UI: $user_id " ;  print "</pre>";

 foreach ($permission_table as $permission) {

  if ( ($permission["Project_ID"] == $project_id) and
     ($permission["User_ID"] == $user_id) ) {
   return TRUE;
  }
 }
 return FALSE;
}



FUNCTION bar_graph( $values_array ) {

 #
 # A modest little function to draw a horizontal bar chart.  Requires relative access to "images/blue.gif"
 #
 # Expects a 1-dimensional associative array where the index names are meaningful:
 #   foo[x], foo[max], foo[avg], etc.
 # Note, scale type is NOT case sensitive.
 #

 if (!isset($values_array))
   die("<br /><br /><pre>Fatal: No values passed to graph array! </pre>");

 print "<div class='barGraph'>\n\t<table cellpadding='0' cellspacing='0' border='0' width='100%' summary='Bar Graph'>\n";

 $total = array_sum($values_array);

 foreach ($values_array as $this_name => $this_value) {

  if ($total == 0)
   $bar_value = " 0.0";  # Avoid division by zero problem
  else {
   $bar_value = sprintf("%2.1f", $this_value / $total * 100);
   if ($bar_value == '100.0')
    $bar_value='100';
  }

  # Print the bar, but only show image slice if non-zero

  print "<tr>\n\t<th>$this_name </th>\n\t<td>";

  if ( $bar_value != 0 ) {
   $html_width = (int)ceil($bar_value);  # Never allow WIDTH=0

   print "<img src='images/blue.gif' width='$html_width' height='5' " .
     "title='$this_value ($bar_value%) $this_name items' alt='Bar Graph' />&nbsp;" ;
  }
  print "</td>\n\t<td>$this_value</td>\n\t<td>($bar_value%)</td>\n</tr>\n";
 }

 # Display final total row

 print "<tr class='graphTotal'>\n\t<th><strong>Total </strong></th>\n\t" .
   "<td>&nbsp;</td>\n\t" .
   "<td><strong>$total</strong></td>\n\t" .
   "<td>(100%)</td>\n</tr>\n" .
   "</table>\n</div>\n\n";
}



FUNCTION brute_force_lock( $filename ) {

 brute_force_lock_check( $filename );

 touch("$filename.lck") or
  die("<br /><br /><center><h3>Fatal: Could not create file lock $filename.lck ! <br /> Make sure Apache has write permissions in this directory");
}



FUNCTION brute_force_lock_check( $filename ) {

 $timeout = 0;
 while ( file_exists("$filename.lck") ) {
  sleep(1);
  clearstatcache();  # Force OS level dump on possible dirty cache; expensive, but should be rarely needed
  if ( $timeout++ > CT_LOCKFILE_TIMEOUT ) {
   brute_force_lock_release( $filename );
   die("<br /><br /><br /><center><h3>Fatal: Timed out waiting on file lock $filename.lck !<br />Lock has been cleared.<br />Please report this message to support.");
  }
 }
}



FUNCTION brute_force_lock_release( $filename ) {

 unlink("$filename.lck") or
  die("<br /><center>Fatal: Could not release file lock $filename.lck !  Please report this message to support.");
}



FUNCTION build_new_session_cookie( $user_data, $selected_project ) {

 if ( (! $user_data ) or (! $selected_project ) )
  die("Fatal-Internal:  No User Data or Selected Project!");

 $session_authentication = calc_session_id( $user_data["Full_Name"] );

 # These variables can be accessed via current_session_g

 $session = array ( "user_full_name" => $user_data["Full_Name"],
            "id" => $session_authentication,
           "project" => $selected_project,
          "username" => $user_data["Username"],
            "role" => $user_data["Role"] );

 $serialized_session = urlencode(base64_encode(serialize($session)));
 return $serialized_session;
}



FUNCTION build_updated_session_cookie( $project_name, $current_session ) {

 #var_dump($current_session);

 $session = array ( "user_full_name" => $current_session["user_full_name"],
            "id" => $current_session["id"],
           "project" => $project_name,
          "username" => $current_session["username"],
            "role" => $current_session["role"] );

 $serialized_session = urlencode(base64_encode(serialize($session)));

 return $serialized_session;
}



FUNCTION calc_next_node_id( &$node_list ) {

 $max_id = 0;
 $node_list_cnt = sizeof($node_list);

 for ($i=0; $i < $node_list_cnt; $i++)
  if ($node_list[$i]["ID"] > $max_id)
   $max_id = $node_list[$i]["ID"];

 return $max_id + 1;
}



FUNCTION calc_session_id( $user_full_name ) {

 # Create a one-day session ID by calculating the MD5 hash of date + user full name + private key

 if (! CT_PRIVATE_KEY )
  die("Fatal: Internal - no authentication key!");

 if (! $user_full_name )
  return FALSE;

 $todays_SID = date("dMY") . $user_full_name . CT_PRIVATE_KEY ;

 return md5( $todays_SID );
}



FUNCTION create_summary( &$project_table ) {

 GLOBAL $xml_array_g, $current_session_g;

 # Present executive summary of all issues.  Project table is passed by reference

 print "\n<br /><br /><div id='pageTitle'>Quality Assurance Executive Summary: <strong>" .
   $current_session_g['project'] . "</strong> Activity for past 30 days</div><center><br />" .
   "<table border=0 width=300 cellpadding=3 cellspacing=2>\n";

 parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "PROJECT", CT_ASCENDING );
 $bug_table = &$xml_array_g;      # Copy by reference, so don't call parse_xml_file again

 $bug_severity = explode(",", CT_BUG_SEVERITIES);
 $bug_status   = explode(",", CT_BUG_STATUSES);

 $stats["change_requests_created"]=0; $stats["change_requests_closed"]=0; $stats["change_requests_deferred"]=0;
 $stats["defect_reports_created"]=0; $stats["defect_reports_closed"]=0; $stats["defects_deferred"]=0;

 $stats["average_lifespan_of_change_requests"]=0;
 $mean_cnt["average_lifespan_of_defects"]=0;

 $stats["average_lifespan_of_defects"]=0;
 $mean_cnt["average_lifespan_of_change_requests"]=0;

 foreach($bug_table as $bug) {

  if ($bug["Project"] != $current_session_g["project"])
   continue;

  if ( isset($bug["Delete_Bug"]) )
   continue;

  if  ( (strtotime($bug["Submit_Time"]) >= strtotime("-30 days")) and
    (strtotime($bug["Submit_Time"]) <= strtotime("now")) ) {

   if (strstr($bug["Severity"], "Change Req"))
    $stats["change_requests_created"]++ ;
   else
    $stats["defect_reports_created"]++ ;
  }

  if  ( (strtotime($bug["Last_Updated"]) >= strtotime("-30 days")) and
    (strtotime($bug["Last_Updated"]) <= strtotime("now")) ) {

   if (strstr($bug["Status"], "Closed")) {
    if (strstr($bug["Severity"], "Change Req")) {
     $stats["change_requests_closed"]++ ;
     $stats["average_lifespan_of_change_requests"] +=
      (strtotime($bug["Last_Updated"]) - strtotime($bug["Submit_Time"]) );
     $mean_cnt["average_lifespan_of_change_requests"]++;
    }
    else {
     $stats["defect_reports_closed"]++ ;
     $stats["average_lifespan_of_defects"] +=
      (strtotime($bug["Last_Updated"]) - strtotime($bug["Submit_Time"]) );
     $mean_cnt["average_lifespan_of_defects"]++;
    }
   }
   elseif (strstr($bug["Status"], "Deferred")) {
    if (strstr($bug["Severity"], "Change Req")) {
     $stats["change_requests_deferred"]++ ;
    }
    else
     $stats["defects_deferred"]++ ;
   }
  }
 }
 print "<tr><td colspan=2 align=left class='rowEven2'><b>" . $current_session_g['project'] . "</b></td></tr>\n";
 $rows=0;

 foreach($stats as $stat=>$value) {
  if ($mean_cnt[$stat] != 0)
   print "<tr><td align=right class='" . ( ($rows % 2) ? "rowOdd" : "rowEven" ) . "'>" .
     ucwords(strtr($stat, "_", " ")) .
     "</td><td class='" . ( ($rows++ % 2) ? "rowOdd" : "rowEven" ) .  "'>" .
     ( (strstr($stat, "lifespan")) ? get_stats($value/$mean_cnt[$stat]) : $value ) .
     "</td></tr>\n";
  }
 print "</table>\n</center><br /><br />";
}



FUNCTION get_stats( $time_in_seconds ) {

 $days = $time_in_seconds / 86400.0;

 if ($days)
  return sprintf("%3.1f days", $days );
 else
  return "(n/a)";

}



FUNCTION create_xml_backups() {

 if ( strstr( getcwd(), "\\" ) )  # In Windows, even the root is guaranteed to be: "X:\"
  $slash_style = "\\";     # PHP escape, not a UNC thing...
 else
  $slash_style = "/";

 $filenames = array("bugs.xml", "users.xml", "projects.xml", "permissions.xml");

 foreach ($filenames as $filename) {

  # E.g.:  xml/bugs.xml
  $source   = CT_XML_DIRECTORY . $slash_style . $filename;

  $destination = CT_XML_BACKUPS_DIRECTORY . $slash_style . $filename . date("_m-d-Y_G.i");
  # E.g:  backups/bugs.xml_12-31-2001_14.51.03

  if (! copy ( $source, $destination ) )
   die("<br /><br /><center>Fatal: Unable to create backup file '$destination' copied from '$source'! <br />".
    " Check that the directory is readable and writable by Apache.");
 }
}



FUNCTION draw_add_edit_bug_form( $project_table, $user_table, $bug_id="" ) {

 GLOBAL $current_session_g, $debug_g, $xml_array_g;

 $project_cnt = sizeof($project_table);
 $user_cnt    = sizeof($user_table);

 for ($i=0; $i < $user_cnt; $i++) {  # zztop -- this block is redundant to c_s_g[role]?
  if ($user_table[$i]["Full_Name"] == $current_session_g["user_full_name"] ) {
   $user_role = $user_table[$i]["Role"];
   break;
  }
 }
 if (!$user_role)
  die("<br /><center><h3>Fatal: No user role found!");

 if ( $user_role == 'Guest' and ($bug_id) and (!CT_GUESTS_CAN_EDIT) )
  die("<br /><div class='cfgProb'>You are not authorized to edit issues</div>");

 if ( $user_role == 'Guest' and (!$bug_id) and (!CT_GUESTS_CAN_CREATE) )
  die("<br /><div class='cfgProb'>You are not authorized to create new issues</div>");


 $browser_name = explode(",", CT_MAJOR_BROWSERS);
 $bug_severity = explode(",", CT_BUG_SEVERITIES);
 $bug_status   = explode(",", CT_BUG_STATUSES);
 $OS_name      = explode(",", CT_MAJOR_OS);
 $developer_response = explode("," , CT_DEVELOPER_RESPONSES);


 # Present bug form

 if ( $bug_id ) {

  # Alias for generic data array by reference for speed, so do not call not parse_xml again

  parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING );
  $bug_array = &$xml_array_g;

  $index = get_array_index_for_id( $bug_array, "$bug_id" );
  $bug_to_edit = $bug_array[$index] ;

  print "<div id='pageTitle'> Edit  " . $current_session_g['project'] . " Issue " .
   "<a href='codetrack.php?page=viewissue&amp;id=$bug_id'>$bug_id</a></div>\n";

  $prev_id = get_prev_bug_id( $bug_array, $index );
  $next_id = get_next_bug_id( $bug_array, $index );
  draw_prev_next_buttons( "$prev_id", "$next_id", $bug_to_edit, "editissue" );

 }
 else
  print "\n\n<div id='pageTitle'> Report a New Issue for {$current_session_g['project']} </div>\n";
?>

 <form enctype="multipart/form-data" id="bF" action="codetrack.php" method="post"
  onsubmit="return checkMissing(this);" >
 <div id="bugForm">

 <input name="MAX_FILE_SIZE" type="hidden" value="<?php print CT_MAX_UPLOAD_FILE_SIZE ; ?>" />
 <input name="page" type="hidden" value="saveissue" />

 <?php if ($debug_g) print '<input name="debug" type="hidden" value="1" />'; ?>

 <div class="innerBorderFix">
 <table cellpadding="5" cellspacing="4" border="1" width="100%" summary="Issue Report">
<?php

 if ( $bug_to_edit ) {

  if ( isset( $bug_to_edit["Updated_By"] ) )
   $msg = "Last updated ";
  else {
   $bug_to_edit["Updated_By"] = $bug_to_edit["Submitted_By"];  # Fix for non-updated first post
   $msg = "Submitted ";
  }
  $msg .=  $bug_to_edit['Last_Updated'] . " by " . $bug_to_edit["Updated_By"] ;

  print "\n\t<tr><td colspan='3'> $msg " .
    "<a href='codetrack.php?page=audit&amp;id={$bug_to_edit['ID']}'>[History]</a></td></tr>\n";
 }

 print "\t<tr><td id='addEditBugColumn'>\n\n\tProject\n\t<select name='bug_data[Project]'>";

 if ($bug_to_edit)
  $p = $bug_to_edit["Project"];
 else
  $p = $current_session_g["project"];

 for ($i=0; $i < $project_cnt; $i++) {
  print "\n\t\t<option value=\"" . $project_table[$i]["Title"] . '"';

  if ( $project_table[$i]["Title"] == $p ) {
   print " selected='selected' ";
   $this_project = $project_table[$i];
  }

  print ">" . $project_table[$i]["Title"] . "</option>" ;
 }
 print "\n\t</select></td>\n\n";
?>
 <td>Module or Screen Name
 <input name="bug_data[Module]" type="text" size="25" maxlength="25" <?php

 # HTML-escape single quotes

 if ($bug_to_edit)
  print " value='" . ereg_replace( "'", "&#039", $bug_to_edit["Module"] ) . "' ";

?> /></td>
 <td>Version
 <input name="bug_data[Version]" type="text" size="10" maxlength="12" <?php
  if ($bug_to_edit)
   print " value='" . ereg_replace( "'", "&#039", $bug_to_edit["Version"] ) . "' ";
?> /></td>
 </tr>
 <tr>

 <td>Severity*
 <select name='bug_data[Severity]'><?php

   print "\n\t\t<option value=''></option>";

   foreach ($bug_severity as $severity) {
    if ( (!$bug_to_edit and $severity == CT_DEFAULT_SEVERITY) or
      ($bug_to_edit["Severity"] == $severity) )
     print "\n\t\t<option value='$severity' selected='selected'>$severity</option>";
    else
     print "\n\t\t<option value='$severity'>$severity</option>";
   }
?>

 </select>
 </td>

 <td colspan="2"><em>Brief</em> Summary of Problem*
 <input name="bug_data[Summary]" type="text" size="40" maxlength="55" <?php
  if ($bug_to_edit)
   print " value='" . ereg_replace( "'", "&#039", $bug_to_edit["Summary"] ) . "' ";
?> /></td>
  </tr>
  <tr>
   <td colspan="3">Full Description*<em> (the more details the better!)</em>
   <textarea name="bug_data[Description]" rows="7" cols="50" <?php

   print " " . CT_OPTIONAL_UGLY_NN4_HACK . " \n>";

   if ($bug_to_edit)
    print $bug_to_edit["Description"];

?></textarea></td>
  </tr>
  <tr>

   <td colspan="<?php

    if ($bug_to_edit)
     print '2">Attachment: ';
    else
     print '3">Attachment <em>(screen print, data file, etc.)</em>';


    if ( isset($bug_to_edit["Attachment"]) ) {
      print "&nbsp;<span class='txtSpecial'>" .
        "<a href='attachments/" . $bug_to_edit["Attachment"] . "' " .
        "onclick=\"this.target='_blank';\" >" .
        $bug_to_edit["Attachment"] . "</a></span>".
        "<input name='bug_data[Attachment]' type='hidden' value='".
        $bug_to_edit["Attachment"] . "' />";
    }
    else {
     print "<input name='Attachment' type='file' size='40' />\n";
    }

   ?>

   </td>
  <?php if ($bug_to_edit) {

    # A little complicated: If [some user role] is explictly blocked, and this user is not in that category...

    if ( (CT_QA_ENFORCE_PRIVS) and ( !in_array($current_session_g["role"], explode(',' , CT_QA_WHO_CAN_CLOSE))) ) {
     print "\n<td><span class='txtSpecial'> [ ] </span> Delete Report </td>";
    }
    else {
     print "\n<td><span class='bugFormGroup'>" .
       "\n<input type='checkbox' name='bug_data[Delete_Bug]' value='Y' " .
       "onclick='if (this.checked) " .
       "return confirm(\"Checking the Delete box will permanently erase this report." .
       '\nIf you really want to delete this report, click OK then press Save. ' .
       '\nTo simply close the issue, cancel now and change the Status category.' .
       "\");' />&nbsp; Delete Report </span></td>";
    }
   }
  ?>

  </tr>

 <?php

 if ( $this_project["Project_Type"] == "Web-Based" )

  {

 ?>

  <tr>
  <td colspan='2'>
   Tested Browser  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;  Browser-Specific? <br />
   <span class='bugFormGroup'>
   <select name='bug_data[Tested_Browser]'>
<?php
   foreach ($browser_name as $browser) {

    if ( ($bug_to_edit["Tested_Browser"] == $browser) or
     (!$bug_to_edit and $browser == CT_DEFAULT_BROWSER) )
      print "\t\t\t\t<option value='$browser' selected='selected'>$browser</option>\n";
    else
     print "\t\t\t\t<option value='$browser'>$browser</option>\n";
   }
?>
   </select>

   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

   <input type='radio' name='bug_data[Browser_Specific]' value='Y'
<?php if ($bug_to_edit["Browser_Specific"] == 'Y') print " checked='checked' "; ?> />Y &nbsp;
   <input type='radio' name='bug_data[Browser_Specific]' value='N'
<?php if ($bug_to_edit["Browser_Specific"] == 'N') print " checked='checked' "; ?> />N &nbsp;
   <input type='radio' name='bug_data[Browser_Specific]' value=''
<?php if ($bug_to_edit["Browser_Specific"] == '')
 print " checked='checked' ";
  else if (!$bug_to_edit)
 print " checked='checked' ";
?> />D/K
   </span>
  </td>
  <td>
   Tested OS
   <select name='bug_data[Tested_OS]'>
<?php
   foreach ($OS_name as $OS) {

    if ( (!$bug_to_edit and $OS == CT_DEFAULT_OS) or
     ($bug_to_edit["Tested_OS"] == $OS) )
     print "<option value='$OS' selected='selected'>$OS</option> \n";
    else
     print "<option value='$OS'>$OS</option> \n";
   }
?>

  </select>
  </td>
 </tr>

<?php

 }  # End of Web-Based Project Check

?>

 <tr>
  <td>
<?php
  if ($bug_to_edit)
   $submitter = $bug_to_edit["Submitted_By"];
  else
   $submitter = $current_session_g["user_full_name"];

  print "Submitted By <div class='txtSpecial'> &nbsp;&nbsp;" .
    $submitter .
    " &nbsp;</div>" .
    "<input name='bug_data[Submitted_By]' type='hidden' value='" .
    $submitter . "' />";
?>
  </td>
  <td><div id="addEditActionButtons" class="bugFormGroup">
    <input type='submit' value=' Save ' />
    <input type='button' value='Cancel' onclick="location.replace('codetrack.php?page=home');" />
    <input type='reset' value=' Undo ' />
  </div></td>

  <td>
<?php
  if ($bug_to_edit) {

   # A little complicated: If [some user role] is explictly blocked, and this user is not in that category...

   if ( (CT_QA_ENFORCE_PRIVS) and ( !in_array($current_session_g["role"], explode(',' , CT_QA_WHO_CAN_CLOSE))) ) {

    print "Status: <div class='txtSpecial'>&nbsp; " . $bug_to_edit["Status"]  . "</div>" .
      "<input name='bug_data[Status]' type='hidden' value='" . $bug_to_edit["Status"] . "' />\n";
   }
   else {
    print "Status\n<select name='bug_data[Status]'>\n";

    foreach ($bug_status as $status) {

     print "\t<option value='$status' ";

     if ( $bug_to_edit["Status"] == $status )
      print " selected='selected' ";

     print ">$status</option>\n";
    }
    print "\n</select>\n";
   }
  }
  else
   print "Status: <div class='txtSpecial'>&nbsp; Open</div>" .
     "<input name='bug_data[Status]' type='hidden' value='Open' />\n";
?>
  </td>
 </tr>
<?php
 if ($bug_to_edit) {
?>

 <tr>
  <td colspan="2">


  <?php  print $this_project["Preferred_Title"] . " Comment \n"; ?>
   <textarea name="bug_data[Developer_Comment]" rows="4" cols="50"
  <?php

   print CT_OPTIONAL_UGLY_NN4_HACK . " >";

   if ($bug_to_edit) {
    $comment = ereg_replace("__", "\r\n", $bug_to_edit["Developer_Comment"]);
    print "$comment";
   }

  ?></textarea>
  </td>
  <td>

  <?php  print $this_project["Preferred_Title"] . " Response \n"; ?>

   <select name='bug_data[Developer_Response]'>
    <?php
    foreach ($developer_response as $response) {
      if ( (!$bug_to_edit and $response == CT_DEFAULT_DEVELOPER_RESPONSE) or
         ($bug_to_edit["Developer_Response"] == $response) )
      print "<option value=\"$response\" selected='selected'>$response</option> \n";
     else
      print "<option value=\"$response\">$response</option> \n";
    }
   ?></select>
  </td>
 </tr>

<?php

 }  # End of Edited Bug-specific widgets

 print "<tr>\n\t\t<td colspan='3'>\n\t\tAssign To: \n<div class='bugFormGroup'>";
 print "<select name='bug_data[Assign_To]'><option value=''>&nbsp;</option>";

 if ($bug_to_edit)
  $assignee = $bug_to_edit["Assign_To"];

 for ($i=0; $i < $user_cnt; $i++) {
  print "<option value='"             .
    $user_table[$i]["Full_Name"] .
    ( ( $user_table[$i]["Full_Name"] == $assignee ) ? "' selected='selected' " : "'" ) .
    ">" . $user_table[$i]["Full_Name"] . "</option>\n" ;
 }
 print "</select>";


 if ( CT_ENABLE_EMAIL ) {

  print "<label title='Send an email to the Assignee'><input type='checkbox' name='send_mail' value='1' /><img ".
    "src='images/email.gif' \n\t\t onclick='send_mail.checked=!send_mail.checked' ".
    "alt='email' /></label> \n";

  print "<label title='Send an email to these people too'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; cc: ";
  print "<select name='cc_list[]' multiple='multiple' size='3'>";

  for ($i=0; $i < $user_cnt; $i++) {
   print "<option value=\"{$user_table[$i]["Email"]}\">" .
     $user_table[$i]["Full_Name"] . "</option>\n" ;
  }
  print "</select></label>\n";
 }

?>

  </div></td>
 </tr>
 </table>
 </div>
<?php
  if ($bug_to_edit) {
   print "<input name='id' type='hidden' value='" . $bug_to_edit["ID"] . "' />\n" ;
   print "<input name='original_submit_time' type='hidden' value ='" . $bug_to_edit["Submit_Time"] . "' />\n" ;
  }
?>
 </div>
 </form>
 <script type="text/javascript" src="javascript/bugform_prevalidate.js"> </script>
 <script type="text/javascript" src="javascript/form_validate.js"> </script>

<?php
}



FUNCTION draw_admin_page() {

?>
 <div id='pageTitle'> Administrator Tools </div><br />
 <div style='margin-left: 20%; margin-top: 0px; margin-bottom: 0px;'>
 <hr width='80%' class="hrBlack" align="left">
  Maintenance
  <ul>
   <li><a href="codetrack.php?page=addproject">Add a Project</a></li>
   <li><a href="codetrack.php?page=adduser">Add a User</a></li>
   <li><a href="codetrack.php?page=changepassword">Change a Password</a></li>
   <li><a href="codetrack.php?page=export">Export Data</a></li>
   <li><a href="codetrack.php?page=backup">Backup XML Databases</a></li>
   <li><a href="codetrack.php?page=users">List Active Users</a></li>
<?php #   <li><a href="codetrack.php?page=projectaccess">Set Permissions</a> <i>(experimental)</i></li>
?>   <li><a href="codetrack.php?page=deletedissues">List Deleted Issues</a></li>
  </ul>
  Technical References
  <ul>
   <li><a href="docs/INSTALL.txt" target="_blank">Installation Guide</a></li>
   <li><a href="docs/CUSTOMIZING.txt" target="_blank">Customizing CodeTrack</a></li>
   <li><a href="docs/TROUBLESHOOTING.txt" target="_blank">Troubleshooting Guide</a></li>
   <li><a href="docs/CHANGELOG.txt" target="_blank">CodeTrack <?php print CT_CODE_VERSION; ?> Changelog</a></li>
   <li><a href="docs/FUNCTIONS.txt" target="_blank">Functions Reference</a></li>
   <li><a href="docs/FILES.txt" target="_blank">Inventory of all CodeTrack Files</a></li>
  </ul>
  License
  <ul>
   <li><a href="docs/LICENSE.txt" target="_blank">CodeTrack is Free software</a></li>
   <li><a href="docs/GPL_FAQ.html" target="_blank">FAQ about the GNU Public License (GPL)</a></li>
  </ul>
  <br />
  <b>Support questions? <a href="mailto:codetrack@openbugs.org">Write us</a> we're happy to help!</b>
	<br /><br /><i>Copyright &copy; 2001-2006 Kenneth White</i>
 </div></div>
<?php
}



FUNCTION draw_export_options( $current_project ) {

 ?>
 <div id='pageTitle'> Data Export Wizard </div><br />
 <form action='codetrack.php' method=POST>
 <div style='font-family: verdana, sans-serif; font-size: 10pt;'>
 <div style='margin-left: 15%; margin-top: 0px; margin-bottom: 0px;'>
 <input type=hidden name=page value=export>
 File Format: <br />
 <em>
  &nbsp;&nbsp;<input type='radio' name='export_options[file_format]' value='csv' checked='checked'> Excel file (CSV)<br />
  &nbsp;&nbsp;<input type='radio' name='export_options[file_format]' value='prn'> Tab-separated text file <br />
  &nbsp;&nbsp;<input type='radio' name='export_options[file_format]' value='sql'> SQL Import file <br />
  &nbsp;&nbsp;<input type='radio' name='export_options[file_format]' value='xml_download'> XML file <br />
  &nbsp;&nbsp;<input type='radio' name='export_options[file_format]' value='xml'> XML tree browser <br />
 </em><br />
 History: <br />
 <em>
  &nbsp;&nbsp;<input type='radio' name='export_options[show_history]' value=1> Show all changes for each issue <br />
  &nbsp;&nbsp;<input type='radio' name='export_options[show_history]' value=0 checked='checked'> Only show the current status of each issue <br />
 </em><br />
 Sort issues by: <br />
 <em>&nbsp;&nbsp;<input type='radio' name='export_options[sort_type]' value=descending checked='checked'>
   Oldest first (Ascending by ID)<br />
  &nbsp;&nbsp;<input type='radio' name='export_options[sort_type]' value=ascending> Newest first <br />
 </em><br />
 <input type=checkbox name='export_options[project_filter]' value='<?php echo $current_project; ?>' checked='checked'>
 Restrict export to <strong><?php echo $current_project; ?></strong> issues <br />
 <input type=checkbox name='export_options[show_deleted]' value='y'> Include deleted issues in export <br /><br />
 <input type=submit value="Download">
 <br /><br /><em>Note: History and Sort options do not apply to XML exports</em>
 </div>
 </div>
 </form>
 <?php
}



FUNCTION draw_login_page( $project_table, $failed="", $session_expired="", $no_cookies="" ) {

 no_cache();
 draw_page_header();

 $project_cnt = sizeof($project_table);

?>
<form id="loginForm" action="codetrack.php" method="post" onsubmit="return validateLogin(this);" >
 <h2 class="form-signin-heading">Welcome to CodeTrack</h1>

 <div class="loginLabel"> Project </div>
 <div class="loginDD">
  <select name="userLogin[project_name]">
<?php
  for ($i=0; $i < $project_cnt; $i++)
   print "\t\t<option value='". $project_table[$i]["Title"] ."'" .
   ((CT_DEFAULT_PROJECT == $project_table[$i]["Title"]) ? " selected='selected' " : '' ) .
   ">". $project_table[$i]["Title"] ."</option>\n";
?>
  </select>
 </div>


 <input type="text" name="userLogin[username]" class="input-block-level" placeholder="Username" />

 <input type="password" name="userLogin[password]" value="" class="input-block-level" placeholder="Password" />

 <button class="btn btn-large btn-primary" type="submit">Sign in</button>

 <input name="page" type="hidden" value="login" />

</form>

<?php
 if ( $failed )
  print "<div class='loginMsg'><h1>Your login failed.</h1> Please enter a correct username and password.</div> \n";

 if ( $session_expired )
  print "<div class='loginMsg'><h2>Your session has expired.</h2> Please log in again.</div> \n";

 if ( $no_cookies )
  print "<div class='loginMsg'><h1>Login unsuccessful.</h1> " .
    "CodeTrack requires session cookies. Please ensure they are not being blocked.</div> \n";
?>

<script type="text/javascript" src="javascript/login_validate.js"> </script>

<noscript>
 <div class="cfgProb"> CodeTrack is pretty broken without Javascript. Please turn it on. </div>
</noscript>

<div class="cfgChkAccessCSS"> If you can see this, there are permission problems on the <em>style</em> directory. </div>
<div class="cfgChkAccessImg"> If you can see this, there are permission problems on the <em>images</em> directory. </div>
<div class="cfgChkAccessJs"> If you can see this, there are permission problems on the <em>javascript</em> directory. </div>

</div></body></html>

<?php
}



FUNCTION draw_page_bottom() {

 print "\n</div><!-- End bodyFrame -->\n</body>\n</html>";

}



FUNCTION draw_page_header() {

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
 <html xmlns="http://www.w3.org/1999/xhtml" lang="en">
 <head>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
 <meta http-equiv="Refresh" content="<?php print CT_DEFAULT_PAGE_TIMEOUT ; ?>;URL=codetrack.php?page=timeout" />
 <title>CodeTrack: Bug and Defect Reporting <?php print CT_CODE_VERSION; ?> </title>
 <!--link rel="stylesheet" href="style/codetrack.css" type="text/css" /-->
 <link rel="stylesheet" href="images/cfgChkAccess.css" type="text/css" />
 <link rel="stylesheet" href="javascript/cfgChkAccess.css" type="text/css" />
 <!--link rel="stylesheet" href="style/codetrack_w3c.css" type="text/css" media="all" /-->
 <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
 </head>
 <body>
 <div class='container'>

<?php
}



FUNCTION draw_page_top( $this_page ) {

 #
 #  Top navigation bar for CodeTrack.  Do a few W3C-legal tricks with span colors on current page, and
 #  (optionally) show balloon tooltips over links.
 # Unless we're debugging, don't cache any rendered (vs. form processing/redirect) page, and autologout after
 # CT_DEFAULT_PAGE_TIMOUT seconds (def. is 8 hours).  Logout is proactively forced via refresh head meta directive.
 #

 GLOBAL $debug_g, $current_session_g, $query_string_g;  # Latter is Apache server variable

 if ( !$debug_g )
  no_cache();

 draw_page_header();

?>
<div id="bodyFrame">

<div id="navBar">
 <a href='codetrack.php?page=home' title='Summary of your current project'
 <?php if ($this_page=='home') print ' id="navCurrent"'; ?>>Home</a>

 <a href='codetrack.php?page=newIssue' title='Create a new defect report or Change Request'
 <?php if ($this_page=='newIssue') print ' id="navCurrent"'; ?>>New Issue</a>

 <a href='codetrack.php?page=reports' title='Create simple and advanced reports'
 <?php if ($this_page=='reports') print ' id="navCurrent"'; ?>>Reports</a>

 <a href='codetrack.php?page=projects' title='List of active projects'
 <?php if ($this_page=='projects') print ' id="navCurrent"'; ?>>Projects</a>

<?php
 if ( $current_session_g["role"] == "Admin" )
  print "\t<a href='codetrack.php?page=adminLinks' " . (($this_page=='adminLinks') ? 'id="navCurrent" ' : '' ).
    "title='CodeTrack Administration and Setup'>Admin</a> \n\n";
 else
  print "\t<a href='codetrack.php?page=tools' " . (($this_page=='tools') ? 'id="navCurrent" ' : '' ).
    "title='CodeTrack system tools'>Tools</a> \n\n";
?>
 <a href="javascript: this.print();" title="Printer friendly version of this page">Print</a>

 <a href="docs/help.html#home" title="Need help with this (or any) screen?" onclick='this.target="_blank";'>Help</a>

 <a href="codetrack.php?page=logout&amp;origin=user" title="Log off the CodeTrack System">Logout</a>
</div>

<hr class="legacyDivider" />

<noscript>
 <div class="cfgProb"> CodeTrack is pretty broken without Javascript. Please turn it back on. </div>
</noscript>

<?php
}



FUNCTION draw_prev_next_buttons( $prev_id, $next_id, &$bug_to_edit, $destination_page, $extra_buttons=FALSE ) {

 #  Draw next/previous issue navigation buttons (seen on viewissue and editissue)

 $id = "{$bug_to_edit['ID']}";  # Force string promotion

 if ($prev_id == $id )  {
  $prev_color  = 'buttonTiny';
  $prev_widget = 'button';  # Grey non-working button
 }
 else {
  $prev_color  = 'buttonTiny';
  $prev_widget = 'submit';
 }

 if ($next_id == $id )  {
  $next_color  = 'buttonTiny';
  $next_widget = 'button';  # Grey non-working button
 }
 else {
  $next_color  = 'buttonTiny';  # Should be buttonWhite
  $next_widget = 'submit';
 }

 if (!$extra_buttons)    # zztop not optimal
  print "<div style='text-align: center;'>\n";

?><div class='subNav'><form action='codetrack.php' method='get'><div class='subNavBar'>
   <input type='hidden' name='page' value='<?php print $destination_page; ?>' />
   <input type='hidden' name='id'   value='<?php print "$prev_id"; ?>' />
   <input type='<?php print $prev_widget; ?>' value='&lt; Prev' class='<?php print $prev_color; ?>' />
 </div></form>
<?php
 if ($extra_buttons) {
  ?>
  <form action='codetrack.php' method='get'><div class='subNavBar'>
   <input type='hidden' name='page' value='editissue' />
   <input type='hidden' name='id'   value='<?php print $id; ?>' />
   <input type='submit' value='  Edit  ' class='buttonTiny' /></div></form>

  <form action='codetrack.php' method='get'><div class='subNavBar'>
   <input type='hidden' name='page' value='audit' />
   <input type='hidden' name='id'   value='<?php print $id; ?>' />
   <input type='submit' value='History' class='buttonTiny' /></div></form>
  <?php
 }

?>
 <form action='codetrack.php' method='get'><div class='subNavBar'>
  <input type='hidden' name='page' value='<?php print $destination_page; ?>' />
  <input type='hidden' name='id'   value='<?php print $next_id; ?>' />
  <input type='<?php print $next_widget; ?>' value='Next &gt;' class='<?php print $next_color; ?>' />
 </div></form></div>
<?php

 if (!$extra_buttons)  # zztop not optimal
  print "</div>\n";
}



FUNCTION draw_project_hotlist_widget( $project_table, $current_project, $destination ) {

 GLOBAL $debug_g;

 if ($debug_g) { print "<pre>" ; var_dump($project_table); var_dump($current_project); var_dump($destination); print "</pre>"; }

?>

<form id="f" class="dropDown" action="codetrack.php" method="get">
<div>

<input type="hidden" name="page" value="changeproject" />
<input type="hidden" name="redir" value="<?php  print $destination;  ?>" />

<select name="project"
 onchange='if (this.options[selectedIndex].value != "") document.forms[0].submit();' class="dropDown"
 <?php

 if ( CT_ENABLE_TOOLTIPS )
  print ' title="Change your current project"';

 print ">\n";

 $project_cnt = sizeof($project_table);

 for ($i=0; $i < $project_cnt; $i++) {
  print "\t<option value=\"" . $project_table[$i]["Title"] . '"' ; #zztop -- had urldecode here (new proj seems to scrub...)

  if ($current_project == $project_table[$i]["Title"])
   print " selected='selected'";

  print ">{$project_table[$i]["Title"]}</option>\n";
 }
 print "</select>\n";

 print "<input id='goButton' type='submit' value='Go' />\n</div>\n</form>\n\n";
}



FUNCTION draw_project_access_form( $project_table, $user_table, $permission_table ) {

 #print "<pre>"; var_dump($permission_table); print "</pre>";

 GLOBAL $current_session_g;
 $current_project = $current_session_g["project"];

 print '<script type="text/javascript" src="javascript/form_validate.js"> </script>';

 print "<div id='pageTitle'> Select Authorized Users for: &nbsp;$current_project </div><br />\n";
 draw_project_hotlist_widget($project_table, $current_project, "projectaccess");

 for ($i=0; $i < sizeof($project_table); $i++) {
  if ($project_table[$i]["Title"] == $current_project) {
   $description = $project_table[$i]["Description"];
   $this_project_id = $project_table[$i]["ID"];
   break;
  }
 }
 #print "<br /><span class='txtSmall'> Project $this_project_id Description: $description </span><br />\n";

?>
<form id="maintenance" action="codetrack.php" method="POST" style="margin-top: 0px; margin-left:0; margin-bottom: 0px;">
 <input type="hidden" name="page" value="savepermissions">
 <input type="hidden" name="permission_data[Project_ID]" value="<?php print $this_project_id; ?>" >
 <table cellspacing=0 cellpadding=0 border=1 style="margin-top: 0px; margin-left:0; margin-bottom: 5px;" summary="Permissions">
 <tr class='rowHeader'>
  <td style='text-align: center;' width='10'><input type=checkbox name=toggleBoxes onClick='toggle_checkboxes(document.forms["maintenance"]);'></td>
  <td style='text-align: left;'><strong> Authorized User &nbsp;&nbsp;</strong></td>
  <td style='text-align: left;'><strong> Role &nbsp;&nbsp;</strong></td>
 </tr>
<?php

 $row = 0;

 foreach ( $user_table as $user ) {       # Traverse all users

  $checked = '';

  if ($user["Role"] == "Admin") {
   $widget_type = 'hidden';       # Admins have access to all projects
   $extra = '[<b>x</b>]' ;
  }
  else {

   $widget_type = 'checkbox';
   $extra = '';

   if ( authorized_user( $this_project_id, $user["ID"], $permission_table ) )
    $checked = ' checked="checked" ';        # If user is listed, they've already got access
  }

  if ($row++ % 2)
   $row_color = 'rowEven2';
  else
   $row_color = 'rowOdd';

  print "\n<tr>\n\t<td class='$row_color' align=center height=22>" .
    "<input type=$widget_type name='permission_data[User_ID][$row]' " .
    "value='" . $user["ID"] . "' $checked >$extra" . "</td>\n" .
    "\t<td class='$row_color' > &nbsp; " .
    $user["Last_Name"] . ", " . $user["First_Name"] . "&nbsp;&nbsp;</td>\n" .
    "\t<td class='$row_color' >&nbsp; {$user['Role']} &nbsp;</td>\n</tr>" ;
 }
 print "\n</table>\n<input type='submit' value='Save'>\n</form>\n\n";
}



FUNCTION draw_read_only_table( $data_table ) {

 print "\n<table border='1' cellspacing='1' cellpadding='3' width='600' summary='Read-only data table'>\n";

 $rowcnt = 0;

 foreach ($data_table as $column_name => $value) {

  print "<tr class='" . ( ($rowcnt++ % 2 ) ? 'rowEven' : 'rowOdd' ) . "'>" .
    "<td class='readOnlyLabel'>" . strtr($column_name, "_", " ") . "</td>" ;

   if ($column_name == 'Attachment')
   $value = "<a href='attachments/$value' onclick=\"this.target='_blank';\" >$value</a>";

  print "<td class='readOnlyData'>" . nl2br($value) . " &nbsp;</td></tr>\n";
 }
 print "</table>\n";
}



FUNCTION draw_reports_page( $project_table, $user_table ) {

 GLOBAL $current_session_g;

 $current_project = $current_session_g["project"];
 $user_full_name  = $current_session_g["user_full_name"];
 ?>
 <div id='pageTitle'> Search and Reports Wizard </div>
 <div style='margin-left: 10%;'>
 <hr width='80%' class="hrBlack" align="left">

  Quick Reports
  <ul>
   <?php

   $link = "codetrack.php?page=filter" .
       "&amp;filter[Status]=Open" .
       "&amp;filter[Assign_To]=" . urlencode($user_full_name);
   print "<li><a href='$link' >All open issues assigned to me</a></li>\n";


   $link = "codetrack.php?page=filter" .
       "&amp;filter[Project]=" . urlencode($current_project) .
       "&amp;filter[Status]=Open" .
       "&amp;filter[Assign_To]=" . urlencode($user_full_name);
   print "<li><a href='$link' >Open <strong>$current_project</strong> issues assigned to me</a></li>\n";


   $link = "codetrack.php?page=filter" .
       "&amp;filter[Project]=" . urlencode($current_project) .
       "&amp;filter[Submitted_By]=" . urlencode($user_full_name);
   print "<li><a href='$link' ><strong>$current_project</strong> issues that I created</a></li>\n";

   print "<li><a href='codetrack.php?page=summary'>Quality Assurance Statistics for <strong>$current_project</strong></a></li>\n";

   ?>
  </ul>

 <hr width='80%' class="hrBlack" align="left">

 Custom Reports <br /><br />

 <div style='margin-left: 3%; margin-top: 0px; margin-bottom: 0px;'>
 <form action="codetrack.php" method="get"> <!-- Need to GET not POST for rational back behavior -->
 <table border=0 summary="">
  <tr class='txtSmall'>
   <td>Project</td>
   <td>Severity</td>
   <td>Status</td>
   <td>Submitted By</td>
   <td>Assigned To</td>
   <td> &nbsp; </td>
  </tr>
  <tr>
   <td>
    <select name='filter[Project]'>
     <option value=''>(All)</option>
  <?php
   for ($i=0; $i < sizeof($project_table); $i++) {
    print "\t\t\t<option value=\"" . $project_table[$i]["Title"] . "\">" .
     $project_table[$i]["Title"] . "</option>\n";
   }
   print "\t\t</select>\n\t\t</td>\n\t\t<td>\n";

   $bug_severity = explode(",", CT_BUG_SEVERITIES);

   print "<select name='filter[Severity]'>\n\t\t<option value=''>(All)</option>\n";
   foreach( $bug_severity as $severity )
				print "\t\t\t<option value=\"$severity\">$severity</option>\n";
			print "\t</select>\n\n\t\t</td>\n\t\t<td>\n";


			$bug_status = explode(",", CT_BUG_STATUSES);
			print "<select name='filter[Status]'>\n\t\t<option value=''>(All)</option>\n";

			foreach( $bug_status as $status )
				print "\t\t\t<option value=\"$status\">$status</option>\n";

			print "\t</select></td>\n\n";


			print "\t\t<td>\n\t\t<select name='filter[Submitted_By]'>\n\t\t".
     "<option value=''>(Anyone)</option>\n\t\t";

   for ($i=0; $i < sizeof($user_table); $i++) {
    print "\t\t\t<option value=\"{$user_table[$i]["Full_Name"]}\">" .
    $user_table[$i]["Full_Name"] . "</option>\n";
   }
 ?>
    </select>
   </td>
   <td>
    <select name='filter[Assign_To]'>
     <option value=''>(Anyone)</option>
     <?php
     for ($i=0; $i < sizeof($user_table); $i++) {
      print "\t\t\t<option value=\"{$user_table[$i]["Full_Name"]}\">" .
      $user_table[$i]["Full_Name"] . "</option>\n";
     }
     ?>
    </select>
   </td>
   <td>&nbsp;<input type=submit value='Go!'> </td>
  </tr>
 </table>
 <input type=hidden name='page' value='filter'>
 </form>
 </div>

 <hr width='80%' class="hrBlack" align="left">

 Full Text Search <br /><br />

 <div style='margin-left: 3%; margin-top: 0px; margin-bottom: 0px;'>
 <form action="codetrack.php" method="get"> <!-- Need to GET not POST for rational back behavior -->
  <input type=hidden name='page' value='searchissue'>
  <input type=text size='50' name=pattern>&nbsp;
  <input type=submit value='Go!'> <br /><br />
 Look for:
		<input type='radio' name='search_options[phrase]'         value=0 CHECKED>Any word &nbsp;&nbsp;&nbsp;
		<input type='radio' name='search_options[phrase]'         value=1>Entire Phrase <br />
	Within: &nbsp;&nbsp;
		<input type=checkbox name='search_options[summary]'     value=1 CHECKED>Summaries &nbsp;
		<input type=checkbox name='search_options[description]' value=1 CHECKED>Descriptions &nbsp;
		<input type=checkbox name='search_options[comment]'     value=1 CHECKED>Comments <br />
	</form>
	</div>

	</div>
	<?php
}



FUNCTION draw_change_password_form( &$user_table ) {

	GLOBAL $current_session_g, $debug_g;

	print '<script type="text/javascript" src="javascript/form_validate.js"> </script>' . "\n" .
			'<center><div class="txtSmall"><br /><b>  Password Update  </b><br /><br /><br /></div>' . "\n"  .
			'<form id="passwordForm" action="codetrack.php" method=POST '.
			"onsubmit='return checkPasswords(this, " . CT_MIN_PASSWORD_LENGTH . ");' >" ;

	if ($debug_g)
		print "\t<input type=hidden name='debug' value='1'>\n";

	if ( $current_session_g["role"] == 'Admin' ) {			#	Admins can change anyone's password...

		$admin = TRUE;

		$widget_label = '&nbsp; User to update <br />&nbsp;';

		$widget = "<select name='user_data[ID]'> \n";

		foreach( $user_table as $user )
			$widget .= "\t\t <option value='" . $user["ID"] . "'>" . $user["Full_Name"] . "</option> \n";

		$widget .= "\t\t </select><br />&nbsp;\n";
	}
	else {																#	But users can only change their own

		$admin = FALSE;

		$widget_label = 'Old Password<br />&nbsp; &nbsp;';

		foreach ($user_table as $user) {
			if ( $user["Username"] == $current_session_g["username"] ) {
				$id = $user["ID"];
				break;
			}
		}
		$widget = "<input type=hidden name='user_data[ID]' value='$id' >\n" .
					 "<input type=password name='old_pw' maxlength=25><br />&nbsp; &nbsp;";
	}
?>

	<table border=0 cellpadding=5 cellspacing=0 bgcolor="#c0c0c0" summary="Password Update">
		<tr>
			<td class='txtSmall' align=RIGHT><?php print $widget_label; ?></td>
			<td><?php print $widget; ?></td>
		</tr>
		<tr>
			<td class='txtSmall' align=RIGHT>New Password</td>
			<td><input type=password name="user_data[Password]" maxlength=25></td>
		</tr>
		<tr>
			<td class='txtSmall' align=RIGHT>Confirmation</td>
			<td><input type=password name="retyped_pw" maxlength=25></td>
		</tr>
		<tr>
			<td align=LEFT><input type=submit value='Save'></td>
			<td align=RIGHT><input type=button value='Cancel' onclick='location.href="codetrack.php?page=home";'></td>
		</tr>
	</table>
	<input type=hidden name='page' value='savepassword'>
	</form>
	</center>
<?php
	if ($admin)
		print	"\t<script type='text/javascript'> document.forms['passwordForm'].elements['user_data[Password]'].focus(); </script>\n";
	else
		print	"\t<script type='text/javascript'> document.forms['passwordForm'].elements['old_pw'].focus(); </script>\n";
}


FUNCTION draw_user_maintenance_form() {

	GLOBAL $debug_g;

	$roles = explode(",", CT_ACCESS_LEVELS);

?>
	<center>
	<div class='txtSmall'><br /><b>Add a New User</b><br /><br /><br /></div>


	<!-- NN4 chokes if onSubmit is multi-lined (probably a bug in JS w/ split consecutive ANDs)  -->

	<form id="userForm" action="codetrack.php" method="POST"
		onSubmit="return checkMissing(this) <?php

	if (CT_PEDANTIC_VALIDATION)
		print " &amp;&amp; validEmail(this['user_data[Email]'].value) ";

?>;" >

	<table border=2 cellpadding=7 cellspacing=0 bgcolor="#E4E2DF" summary="User Maintenance">
		<tr>
			<td class='txtSmall'>
				<input name="page" type="hidden" value="saveuser">
				<input name="user_data[ID]" type="hidden" value="-1">
				First Name* <br /><input name="user_data[First_Name]" type="text" size="20" maxlength="25">&nbsp;&nbsp;
			</td>
			<td class='txtSmall'>
				Last Name*  <br /><input name="user_data[Last_Name]" type="text" size="20" maxlength="25">
				<input name="user_data[Full_Name]" type="hidden" value="x">
				<input name="user_data[Initials]"  type="hidden" value="x">
				<input name="user_data[Username]"  type="hidden" value="x">
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2>e-mail address*<br />
				<input name="user_data[Email]" type="text" size="30" maxlength="60">
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2>Phone Number<br />
				<input name="user_data[Phone]" type="text" size="20" maxlength="32">
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2>Role<br />
				<select name='user_data[Role]'>
			<?php
				foreach ($roles as $role) {
					if ( $role == CT_DEFAULT_ACCESS_LEVEL )
						print "<option value=\"$role\" selected='selected' > $role </option>\n";
					else
						print "<option value=\"$role\">$role</option>\n";
				}
			?>
				</select>
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2>Username <em>(leave blank to autogenerate)</em><br />
				<input name="user_data[Username]" type="text" size="20" maxlength="32">
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2>Initial Password <em>(leave blank to autogenerate)</em><br />
				<input name="user_data[Password]" type="text" size="20" maxlength="32">
			</td>
		</tr>
		<tr>
			<td class='txtSmall' colspan=2><input type=submit value='Save'>

			&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <input type=checkbox value=1 name="notify_user"> e-mail information to user

			<?php if ($debug_g) print "<input type=hidden name=debug value='y'>"; ?>
			</td>
		</tr>
	</table>
	</form>
	</center>
	<script type="text/javascript" src="javascript/userform_prevalidate.js"> </script>
	<script type="text/javascript" src="javascript/form_validate.js"> </script>
<?php
}



FUNCTION draw_project_maintenance_form( $user_table ) {

	GLOBAL $debug_g;

?>
	<center>
	<div class='txtSmall'><br /><b>Add a New Project</b><br /><br /><br /></div>

	<!-- Note NN4 chokes if onSubmit is on multiple lines (probably a bug in the js AND)  -->

	<form id="projectForm" action="codetrack.php" method="POST" onSubmit="return checkMissing(this);" >

	<table border=2 cellpadding=7 cellspacing=0 bgcolor="#E4E2DF" summary="Project Maintenance">
		<tr>
			<td class='txtSmall'>Project Title <em>(One or Two Words, or an acronym) </em><br />
				<input name="page" type="hidden" value="saveproject">
				<input name="project_data[ID]" type="hidden" value="-1">
				<input name="project_data[Title]" type="text" size="20" maxlength="25" value="">
			</td>
		</tr>
		<tr>
			<td class='txtSmall'>Lead Developer or Analyst<br />
				<select name='project_data[Test_Lead]'>
<?php

	$user_cnt = sizeof( $user_table );

	for ($i=0; $i < $user_cnt; $i++) {
		print "\t\t\t<option value=\"" . $user_table[$i]["First_Name"] . "\"" .
		( ($user_table[$i]["Full_Name"] == CT_DEFAULT_PROJECT_LEAD) ? " selected='selected'>" : ">" ) .
		$user_table[$i]["Full_Name"] . " </option>\n";
	}
	print "\t\t</select>\n\n";

?>
			</td>
		</tr>
		<tr>
			<td class='txtSmall'>Project Description <br />
				<input name="project_data[Description]" type="text" size="55" maxlength="65" value="">
			</td>
		</tr>
		<tr>
			<td class='txtSmall'>
				Type of Project <br />
				<input name="project_data[Project_Type]" type=radio value="Web-Based" CHECKED>Web-Based &nbsp;&nbsp;&nbsp;&nbsp;
				<input name="project_data[Project_Type]" type=radio value="Desktop Application">Desktop Application &nbsp;&nbsp;&nbsp;&nbsp;
				<input name="project_data[Project_Type]" type=radio value="Data Analysis">Data Analysis
			</td>
		</tr>
		<tr>
			<td class='txtSmall'>
				Preferred Title of Responding Team Members <br />
				<input name="project_data[Preferred_Title]" type=radio value="Developer" CHECKED>Developer &nbsp;&nbsp;&nbsp;&nbsp;
				<input name="project_data[Preferred_Title]" type=radio value="Analyst">Analyst &nbsp;&nbsp;&nbsp;&nbsp;
				<input name="project_data[Preferred_Title]" type=radio value="Engineer">Engineer
			</td>
		</tr>
		<tr>
			<td class='txtSmall'><input type=submit value='Save'>
			<?php if ($debug_g) print "<input type=hidden name=debug value='y'>"; ?>
			</td>
		</tr>
	</table>
	</form>
	</center>
	<script type="text/javascript" src="javascript/form_validate.js"> </script>
	<script type="text/javascript" src="javascript/projectform_prevalidate.js"> </script>
<?php
}


FUNCTION draw_view_bug_page( $bug_id ) {

	GLOBAL $xml_array_g;

	#	Alias for generic data array by reference for speed, so do not call not parse_xml again

	parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING );
	$bug_array = &$xml_array_g;

	$index = get_array_index_for_id( $bug_array, "$bug_id" );
	$bug_to_edit = $bug_array[$index] ;

	print "\n\t<div id='pageTitle'> Issue # $bug_id </div><br />\n";

	$prev_id = get_prev_bug_id( $bug_array, $index );
	$next_id = get_next_bug_id( $bug_array, $index );
	draw_prev_next_buttons( "$prev_id", "$next_id", $bug_to_edit, "viewissue", "all_buttons" );
	print "\n";
	draw_read_only_table( $bug_to_edit );

}



FUNCTION draw_xml_backups_page( $success='' ) {

	print "<div class='txtSmall'><center><br /><b>XML Database Backup Utility</b></center><br />" .
			"<div style='margin-left: 20%; margin-top: 0px; margin-bottom: 0px;'>" .
			"<hr width='80%' class='hrBlack' align='left'><br />" .
			"Existing Entries in <em>" . CT_XML_BACKUPS_DIRECTORY . "</em> :\n<br />";

	$dir_h = opendir( CT_XML_BACKUPS_DIRECTORY );

	if (! $dir_h )
		die('<br /><br /><H3>Fatal:  Could not read the "'. CT_XML_BACKUPS_DIRECTORY .'" directory! " .
				Make sure it is read+writable by Apache.');

	#	Yes, that's the correct evaluation operator -- see PHP manual on readdir for more info.

	while ( FALSE !== ($filename = readdir( $dir_h )) ) {
		if ( $filename == '.' or $filename == '..' or $filename == 'index.html' )
			continue;
		$file_list[] = $filename;
	}
	closedir( $dir_h );

	if ( $file_list ) {
		sort( $file_list );
		print "\n<form action='#'>\n<textarea ROWS='8' COLS='35'>\n";
		foreach ($file_list as $entry)
			print "$entry\n";
		print "</textarea>\n</form>\n\n";
	}
	else
		print "<br /><em>(none)</em><br />\n";

	print "<br /><br /><form action='codetrack.php' method=post>\n" .
			"<input type=submit value='Backup Now'>\n".
			"<input type=hidden name='page' value='dobackup'></form>\n";

	if ($success)
		print "<br /><em>Backups successfully created.</em>\n";

	print "\n</div></div>\n";
}



FUNCTION draw_table( &$data_array, $title, $filter, $project_table='' ) {

	# data_array is potentially huge.  Pass by reference explicitly

	GLOBAL $debug_g, $current_session_g;

	$bug_statuses	 = explode("," , CT_BUG_STATUSES);
	$bug_severities = explode("," , CT_BUG_SEVERITIES);

	foreach ($bug_statuses as $status)
			$status_counts[$status] = 0;							# ex. $status_counts["Closed"]

	foreach ($bug_severities as $severity)
			$severity_counts[$severity] = 0;						# ex. $severity_counts["Fatal"]

	if ($debug_g) {
		print "<pre>"; var_dump($data_array); var_dump($filter); print "</pre>";
	}

	if ( (!$data_array) or (!$title) )
		die("Fatal: Draw table passed no data array or title!");

	print '<script type="text/javascript" src="javascript/table_sort.js"> </script>';
	print "\n\n<div id='pageTitle'>$title</div><br />\n";

	$row_cnt = sizeof($data_array);  # This is correct; We need original size.

	#	Project Hot List Drop Down

	if ($project_table)
		draw_project_hotlist_widget($project_table, $current_session_g["project"], "home");

		#	Sort by $data_array by Project, Severity (desc), ID (bug lists only)

		$in  = 'return strcasecmp($a["Project"].$b["Severity"].$a["ID"],';
		$out = '$b["Project"].$a["Severity"].$b["ID"]);';
		$f   = $in . $out;
		usort($data_array, create_function('$a,$b', $f));

		#	EXTRACT COLUMN HEADINGS

		if ($title == 'Active Projects' or $title == 'Address Book')
			$element_entry = array_keys( $data_array[0] );
		else
			$element_entry = explode(",", CT_HOME_PAGE_DATA_COLUMNS );

	#  zztop -- got it, sweep all code to send project table on draw_table call
#	}
#	else
#		$element_entry = array_keys( $data_array[0] );		#zztop ?
# 		$element_entry = explode(",", CT_HOME_PAGE_DATA_COLUMNS );  zztop Source of duplicate URL


	if (!$element_entry)
		return;

	#	Build Generic Summary Table

	print "<div class='dataBlock'>\n";
	print "<table id='results' cellspacing='1' border='0' width='100%' summary='Data Table'>\n";
	print "<thead>\n\t<tr title='Click on column name to sort'>\n";

	$hdr_cnt = 0;

	foreach ($element_entry as $column_name) {

		if ( $column_name == 'Project' and !stristr($title, "All Projects") )	# zztop this is a kludge
			continue;

		if ( $column_name == 'Password')
			continue;

		if ($column_name == 'Assign_To')
			$column_name = 'Assigned_To';

		if ($column_name == 'Developer_Response') {			# zztop customization has it's costs...

			if (sizeof($project_table))
				foreach ($project_table as $tmp_proj)
					if ($tmp_proj["Title"] == $current_session_g["project"]) {
						if (isset($tmp_proj["Preferred_Title"]))
							$column_name = $tmp_proj["Preferred_Title"] . "_Response";
						break;
					}
		}

		print "\t\t<th onclick='SortTable(" . $hdr_cnt++ . ");'> " . strtr($column_name, "_", " ") . " </th>\n";
	}
	print "\t</tr>\n</thead>\n<tbody>";


	#	WALK EACH NODE (BUG, USER, WHATEVER) THROUGH EACH OF ITS ELEMENTS

	$row_cnt = sizeof($data_array);
	$shown_row_cnt = 0;
	$delcnt = 0;

	for ( $row = 0; $row < $row_cnt; $row++ ) {

		if (!$data_array[$row]["ID"])
			continue;

		for ($j=0; $j < $delcnt; $j++) {
			if ($data_array[$row]["ID"] == $already_shown[$delcnt])
				break;
			else
				$already_shown[$delcnt++] = $data_array[$row]["ID"];
		}

		if ( isset($data_array["$row"]["Delete_Bug"]) )
				continue;

		$display = TRUE;

		if ($filter) {
			foreach ($filter as $filter_column => $filter_value) {
				if ($filter_column == 'Project' and $filter_value == 'All' )
					continue;  				# Don't fail looking for a project "all"
				if ( $data_array[$row][$filter_column] != $filter_value  ) {
					$display = FALSE;
					break;
				}
			}
		}

		if ( $display )	{

			#	Got one -- let's show it

			$highlight_class = "";

			print "\t<tr" . (($shown_row_cnt % 2) ? " class='rowOdd'" : " class='rowEven'" ) . ">\n";

			if ( stristr($title, "issue") ) {

				foreach ($bug_statuses as $status) {
					if ($data_array[$row]["Status"] == $status)
						$status_counts[$status]++;									# ex. $status_counts["Closed"]
				}

				foreach ($bug_severities as $severity) {
					if ($data_array[$row]["Severity"] == $severity)
						$severity_counts[$severity]++;							# ex. $severity_counts["Fatal"]
				}
			}


			foreach ($element_entry as $column) {

				if ( ($column == 'Project' and !stristr($title, "All Projects" )) or ( $column == 'Password') )  # zztop kludge
					continue;

				$data_array[$row][$column] = trim( $data_array[$row][$column] );

				if ($project_table)		# Limit column size for bug-related tables (not user/proj)
					if (strlen($data_array[$row][$column]) > 40)
						$data_array[$row][$column] = substr($data_array[$row][$column],0,40) . "...";

				#	If this is a designated (and populated) link entry, make it a link

				if ($title != "Address Book") {

/*						if ( ($column == "Attachment") and ($data_array[$row][$column]) ) {
									$data_array[$row][$column] = "<a href='attachments/"  .
																		  $data_array[$row][$column] .
																		  "' target=_blank >{$data_array[$row][$column]}</a>" ;
						}
*/
						if ( ($column == "ID") and ($data_array[$row][$column]) ) {

							$accession = (int) $data_array[$row][$column];

							if ($title == "Active Projects")
									$data_array[$row][$column] = "<a href='codetrack.php"  .
																		  "?page=changeproject&amp;redir=home&amp;project=" .
																		  urlencode($data_array[$row]["Title"]) .
																		  "'>{$data_array[$row][$column]}</a>" ;
							else
								$data_array[$row][$column] = "<a href='codetrack.php" .
																	  "?page=viewissue&amp;id=" .
																	  $data_array[$row][$column] .
																	  "'>{$data_array[$row][$column]}</a>" ;

							# Embed a numeric, sortable numeric-only comment so the column sort works
							# in human-order (lastNode value in table_sort.js)

							$data_array[$row][$column] .= '<!-- ' . $accession . ' -->';
					}

				}

				#	If developer has posted a response, highlight the status column

				if ( ($column == 'Status') and
					  ( ! isset($data_array[$row]["Developer_Comment"]) and
						 ! isset($data_array[$row]["Developer_Response"]) ) ) {
					$highlight_class = " class='devResponse'";
				}

				# Embed a numeric, sortable Unix-style timestamp as an HTML comment, so the column sort js will use

				if ($column == 'Last_Updated' or $column == "Submit_Time") {
						$human_date = parse_human_date( $data_array[$row][$column] );
						$unix_timestamp = strtotime( $data_array[$row][$column] );
						$data_array[$row][$column] = $human_date . '<!-- ' . $unix_timestamp . ' -->';
				}

				print "\t\t<td$highlight_class>";
				print (($data_array[$row][$column]) ? $data_array[$row][$column] : '&nbsp;' );
				print "</td>\n";

				$highlight_class = "";
			}
			print "\t</tr>\n";

			$shown_row_cnt++;
		}
	}
	print "</tbody></table>\n</div>\n";

	if ( stristr($title, "issue") ) {	#zztop ?

		print "<span id='resultsTotal'>Total issues: $shown_row_cnt </span>";

		if ( $shown_row_cnt > 0 ) {

			print "\n<span id='resultsFooter'> (Oldest to newest, by severity. " .
					"Red status indicates response or comment needed.)</span><br /><br /><br /> \n";

			print "<div class='graphTitle'> Count by Severity </div>\n";
			bar_graph( $severity_counts );

			print "<div class='graphTitle'> Count by Status </div>\n";
			bar_graph( $status_counts );

		}
	}
}



FUNCTION encrypt_password( $plaintext_password ) {

	#	Produce an MD5 hash that is alphanumeric only, for HTML & XML friendliness.
	#	Keyspace is SLIGHTLY reduced, but if raw MD5 is revealed, and they're determined, we're hosed anyway...

	mt_srand(time());
	$sanity_check = 0;

	do {

		$salt  = chr(mt_rand(48,122));		#	Look for a "mostly" alphanumeric salt
		$salt .= chr(mt_rand(48,122));

		$encrypted_password = crypt($plaintext_password, $salt);

		if ( $sanity_check++ > 250 )
			die("<br /><br /><center>Fatal:  Could not construct valid encrypted password!");

	} while( eregi("[^0-9a-z]+", $encrypted_password) );

	return $encrypted_password;
}



FUNCTION export_database( $export_options, $user_agent ) {

	GLOBAL $xml_array_g, $debug_g;

	#  PUSH XML DATABASE TO CLIENT IN VARIOUS FORMATS (XML, CSV, SQL, TXT, ETC.)

	$show_history = $export_options["show_history"];
	$sort_type = $export_options["sort_type"];
	$show_deleted = $export_options["show_deleted"];
	$file_format = $export_options["file_format"];
	$project_filter = $export_options["project_filter"];

	if ($file_format == 'xml_download') {
		$force_download = TRUE;
  $file_format = 'xml';
 }
 else
  $force_download = FALSE;

 $table_name = 'bugs';
 $download_filename = "$table_name.$file_format";  # i.e., "bugs.csv"


 # START FILE PUSH

 if ( !$debug_g )
  push_mime_info( $user_agent, $download_filename, $force_download );


 # PUSH FILE DIRECTLY IF XML FORMAT REQUESTED

 if ( $file_format == 'xml' ) {

  readfile ( CT_XML_BUGS_FILE )  or
   die ("<br /><center><h3>Fatal: Couldn't open 'CT_XML_BUGS_FILE' ! Check that " .
      "it's readable by the Apache owner.");
  flush();
  exit;
 }



 # EXTRACT XML ELEMENT LIST (DATA COLUMN NAMES) FROM DOCUMENT TYPE DEFINITION FILE

 $column_names  = parse_dtd_file( "$table_name.dtd" ); # i.e., "bugs.dtd"

 $xml_filename = CT_XML_DIRECTORY . "/$table_name.xml";


 $xml_array_g = array();  # Lost scope, so reset

	#	Alias xml data array by reference for speed, so do NOT call parse_xml again

	if ($show_history)
		parse_xml_file( $xml_filename, CT_KEEP_HISTORY, "ID", CT_ASCENDING);		# i.e., "bugs.xml"
	else
		parse_xml_file( $xml_filename, CT_DROP_HISTORY, "ID", CT_ASCENDING);		# i.e., "bugs.xml"

	$data_array = &$xml_array_g;

	$row_cnt = sizeof($data_array);  #	Yes, this is correct; need original # of rows

	usort($data_array, create_function('$a,$b',
			'return strcasecmp($a["Project"].$a["ID"],$b["Project"].$b["ID"]);'));

	$table_prefix=''; $table_suffix=''; $line_prefix=''; $line_suffix='';

	if ( $file_format == "csv") {
		$delimiter   = '"' ;
		$escaped_delimiter = '""';				# Excel '97/2K escapes this way
		$separator   = ',' ;
		$line_suffix = "\r\n" ;
	}
	elseif ( $file_format == "prn") {
		$delimiter    = "\t" ;
		$escaped_delimiter = "[tab]";			# We're open to any clever alternatives here...
		$separator    = "" ;
		$line_suffix  = "\r\n" ;
		$table_prefix = "\n" ;
		$table_suffix = "\n\n" ;
	}
	elseif ( $file_format == "sql") {
		$delimiter = "'" ;
		$escaped_delimiter = "'''";			# Oracle escapes this way, not sure if it's "official" ANSI SQL, though...
		$separator = "," ;
		$table_prefix = "-- ALTER SESSION SET NLS_DATE_FORMAT='DD-MON-YYYY HH:MI:SS AM' ;\n\n";
		$table_suffix = "\n.\n/\n\n" ;
		$line_prefix  = "INSERT INTO bugs VALUES (" ;
		$line_suffix  = ") ; \r\n" ;
	}
	else
		die ( "<br /><br /><center>Internal Fatal: No download type specified!" );


	#	SEND COLUMN NAMES

	if ($file_format == "csv" or $file_format == "prn" ) {

		for ($j=0; $j < sizeof($column_names); $j++)
			print ( ($j) ? "$separator" : "" ) . $delimiter . $column_names[$j] . $delimiter;
		print "\r\n";

	}


	#	PUSH DATA, EITHER ASCENDING OR DESCENDING

	print $table_prefix;

	for ( $row = 0; $row <= $row_cnt; $row++ ) {

		if (!isset($data_array[$row]))
			continue;

		if ( (!$show_deleted) and ($data_array[$row]["Delete_Bug"] == 'Y') )
			continue;

		if ( $project_filter && $data_array[$row]["Project"] != $project_filter )
			continue;

		foreach ($column_names as $column) {

			$cell = reverse_htmlspecialchars ( $data_array[$row][$column] );
			$cell = strtr($cell, "\n\r", CT_NEWLINE_SYMBOL);								# NO CROSS-VENDOR WAY TO EMBED NEWLINES
			$cell = ereg_replace( $delimiter, $escaped_delimiter, $cell );


			# DO NOT PRINT LEADING SEPARATORS IN FRONT OF FIRST COLUMN, I.E.:   ,foo,bar

			if ($column == $column_names[0]) {

				print $line_prefix ;

				if ($file_format == "prn" )
					print $cell . $delimiter ;
				else
					print $delimiter . $cell . $delimiter;
			}
			else {

				print $separator . $delimiter . $cell ;

				if ($file_format != "prn" )	# TAB-SEPARATORS SHOULD NOT DOUBLE. BAD:  \t foo \t \t bar \t  GOOD: "foo","bar"
					print $delimiter;
			}
		}
		print $line_suffix;
	}
	print $table_suffix;

	#	EMPTY STDOUT CACHE AND END DOWNLOAD

	flush();
	exit;
}



FUNCTION get_array_index_for_id( &$data_array, $id ) {

	# Data explicitly passed by reference

	$row_cnt = sizeof($data_array);

	if ($row_cnt == 1)		# Special case, single array element isn't really sorted, thus not compacted, thus index=1
		return 0;

	for ($index=0; $index < $row_cnt; $index++)
		if ($data_array[$index]["ID"] == $id)
			return $index;

	die("<center><br /><br /><h2>Fatal: No issue matching ID# $id found!");

}



FUNCTION get_next_bug_id( $bug_array, $current_row_id ) {

	GLOBAL $current_session_g;

	#	Count forward from current bug, until we find a non-deleted bug or hit max array
	#	Note: $bug_array is passed by reference

	$n = $current_row_id;
	while ( ++$n < sizeof($bug_array) ) {
		if ( (!isset($bug_array[$n]["Delete_Bug"])) &&
			  ($bug_array[$n]["Project"] == $current_session_g["project"]) &&
			(! stristr( $bug_array[$n]["Status"], "Closed" ) ))  {
			$next_id = $bug_array[$n]["ID"];
			break;
		}
	}

	if ( !isset($next_id) )   #	If we couldn't find it, set next to current
		$next_id = $bug_array[$current_row_id]["ID"];

	return $next_id;
}



FUNCTION get_prev_bug_id( &$bug_array, $current_row_id ) {

	GLOBAL $current_session_g;

	#	Count backwards from current bug, until we find a non-deleted bug or index is negative
	#	Note: $bug_array is explicitly passed by reference

	$p = $current_row_id;
	while ( --$p >= 0 ) {
		if ( (!isset($bug_array[$p]["Delete_Bug"])) &&
			($bug_array[$p]["Project"] == $current_session_g["project"]) &&
			(! stristr( $bug_array[$p]["Status"], "Closed" ) ))  {
			$prev_id = $bug_array[$p]["ID"];
			break;
		}
	}
	if ( !isset($prev_id) )   #	If we couldn't find it, set prev to current
		$prev_id = $bug_array[$current_row_id]["ID"];

	return $prev_id;
}



FUNCTION no_cache() {
	header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    # Date in the past
	header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header ("Cache-Control: no-cache, must-revalidate");  # HTTP/1.1
	header ("Pragma: no-cache");                          # HTTP/1.0
}



FUNCTION parse_dtd_file( $filename ) {

	$fh = fopen($filename, "r") or
		die ("<br /><center><h3>Fatal: Couldn't open '$filename' ! Check that it's readable by the Apache owner.");
	$dtd = fread ($fh, filesize ($filename));
	fclose ($fh);

	#
	#	Extract all element words in the form of:
	#
	#	<!ELEMENT (whitespace)
	#		ELEMENT_WORD_OF_INTEREST
	#		(whitespace) ( (maybe whitespace) #PCDATA (maybe whitespace) )
	#

	preg_match_all ("|<\!ELEMENT\s+(\w+)\s+\(\s*#PCDATA\s*\)>|", $dtd, $match_array);
	$dtd_array = $match_array[1];  # Convert matches to flat single-dimensional array

 return ($dtd_array);
}



FUNCTION parse_human_date( $date_string ){

#  Check if $date_string occurred yesterday or today, and if so augment the string. Otherwise return as passed.

  if ( strtotime( $date_string ) > strtotime( "00:00 today" ) )
   return "Today, " . date ("g:i A", strtotime($date_string)) ;
  elseif ( strtotime( $date_string ) > strtotime( "00:00 yesterday" ) )
   return "Yesterday, " . date ("g:i A", strtotime($date_string)) ;
  else
   return $date_string;
}



FUNCTION parse_xml_file( $xml_filename, $drop_history=FALSE, $sort_element='', $sort_order='' ) {

 #
 # A non-validating, non-generic, single-pass XML parser.  Returns data by populating
 # the global $xml_array_g.  Although written specifically for CodeTrack, it could be
 # recycled, as long as elements are no more than one level deep, and minor elements
 # do not repeat hierarchically inside major elements.  For example, this would not work:
 #
 #  <bar><foo>x</foo><foo>y</foo></bar>
 #
 # The returned array would contain only one $foo, y.
 #

 GLOBAL $xml_array_g, $debug_g;

 $xml_array_g = array();   # Reset it, in case we've been called before

	if (!($fp = fopen($xml_filename, "r")))
		die("<br /><br /><center><h2>Fatal: Error opening '$xml_filename'. Make sure it's readable by the Apache owner.");

	$xml_document = fread($fp, filesize($xml_filename)) ;
	fclose($fp);

	#	Pull out <tag_name>(data)</tag_name> as: $matches[j][1]=$tag_name & $matches[j][2]=$data

	preg_match_all( "#<([\w]+)[^>]*>([^<]*)</\\1>#", $xml_document, $matches, PREG_SET_ORDER );

	$node_cnt=0;			# Nodes here are a complete <bug>, <user>, <project>, etc.
	$node_id=0;

	$match_cnt = count($matches);					# Element matches, not node matches

	for ($i=0; $i < $match_cnt; $i++) {			# Walk every element found

		if ( $matches[$i][1] == 'ID' ) {			# [1] is column, [2] is content

			#
			#	If we need a bug's complete history, the node list will continue to grow, otherwise
			#	each successive entry of, say, bug[37] gets replaced with the newest bug[37].
			#	This will could theoretically leave gaps: bug[39], bug[41], etc., but this will be
			#	corrected in the sort below (the index will be packed, and the array index will no
			#	longer be guaranteed to match the bug ID.)
			#

			$node_id = 0 + $matches[$i][2];			# Ex., bug[23][ID]=006 -> 0+006 -> $node_id = 6

			if ($drop_history) {
				$index = $node_id;
				unset( $xml_array_g[$node_id] );		# Clear all elements of any previous entry
			}
			else
				$index = $node_cnt;

			$node_cnt++;									# New bug entry, new user, etc.
		}
		$xml_array_g[$index][$matches[$i][1]] = $matches[$i][2];		# ex. $bug[8][Author]=Bob
	}

	#	Sort arrays, but lose key associations:  $foo[3], $foo[5], vs. $foo[0], $foo[1]

 if ($sort_element) {

  if ( ( sizeof($xml_array_g) == 1 ) and ( isset($xml_array_g[1]) ) ){ # Weirdo once-in-the-life-of-the-system boundary condition

   if ($debug_g)
    print "<tt><br />Encountered 1-element array with non-zero index.  Shifting down **** <br /></tt>\n";
   $tmp = $xml_array_g[1];
   unset($xml_array_g[1]);
   $xml_array_g[0] = $tmp;
  }
  elseif ($sort_order == CT_ASCENDING) {
   usort($xml_array_g,
    create_function('$a,$b','return strcasecmp($a["'.$sort_element.'"],$b["'.$sort_element.'"]);'));
  }
  else {
   usort($xml_array_g,
    create_function('$a,$b','return strcasecmp($b["'.$sort_element.'"],$a["'.$sort_element.'"]);'));
  }
 }
 if ($debug_g)
  printf("<tt><br />Parsing: *%s*&nbsp; Sort element: *%s*&nbsp; Ascending: %0d  ".
     "Drop history: %0d  Node count: %0d <br /> Total # of parsed elements: %0d <br /></tt>\n",
     $xml_filename, $sort_element, $sort_order, $drop_history, $node_cnt, $i);
}



FUNCTION print_audit_trail( &$data_array, $id ) {

  $row_cnt = sizeof($data_array);   # Yes, this is correct; need original # of rows

  $last_entry = '';

  $updates_posted = FALSE;

  for ($i=0; $i < $row_cnt; $i++) {

   if ( $data_array[$i]["ID"] == $id ) {

    if ( !$last_entry ) { # For those just tuning in, legacy data fix follows (zztop WFT?)

     print "<div class='txtSmall'>\n<center><br /><b>History for Issue #" .
       "<a href='codetrack.php?page=viewissue&amp;id=$id'>$id</a>" .
       "</b></center>\n";

     print "<br />&nbsp;Original Report\n";
     $initial_report = $data_array[$i];
     unset($initial_report["Last_Updated"]) ;  # It might be confusing to show this
     draw_read_only_table( $initial_report );

     $last_entry = $data_array[$i];
     continue;         # Search next node in loop
    }
				$updates_posted = TRUE;

				if (! isset( $data_array[$i]["Last_Updated"] ) ) {

					$data_array[$i]["Last_Updated"] = $data_array[$i]["Submit_Time"];
					$data_array[$i]["Updated_By"]   = 'someone';
					$data_array[$i]["Submit_Time"] = $last_entry["Submit_Time"];
				}
				print "<br /><hr class='hrBlack' width='80%' align='left'>\n<br />".
						"On {$data_array[$i]["Last_Updated"]}, <strong>{$data_array[$i]["Updated_By"]}</strong> ".
						"modified this entry:<br /><br />";

				foreach ($data_array[$i] as $column => $newval)	{

					$newval = nl2br($newval);

					if ( ($column == 'Last_Updated') or ($column == 'Updated_By') )
						continue;

					$oldval = $last_entry[$column];
						if ( !$oldval )
							print "<b>" . strtr($column, "_", " ") . "</b> \"$newval\" was <b>added</b>.<br />";
				}

				foreach ($last_entry as $column => $oldval)	{

					if ( ($column == 'Last_Updated') or ($column == 'Updated_By') )
						continue;

					$newval = $data_array[$i][$column];
					#print "[Scanning *$column* of *$oldval* against *$newval*]<br />";
					if ( !$newval )
						print "<b>" . strtr($column, "_", " ") . "</b> was <b>deleted</b>.<br />";
					elseif ( $oldval != $newval )
						print "<b>" . strtr($column, "_", " ") . "</b> \"$oldval\" <b>changed to</b> \"$newval\". <br />";
				}
				$last_entry = $data_array[$i];
			}
		}
		print "<br /><br />\n</div>\n";

		if (!$last_entry)
			die("Fatal: No issue #$id found!");

		if (!$updates_posted)
			print "<em>(No updates have been made to this report)</em>";

		return;
}



FUNCTION print_deleted_bugs( &$bug_table ) {

	usort($bug_table, create_function('$a,$b',
		'return strcasecmp($a["Project"].$a["ID"],$b["Project"].$b["ID"]);'));

	$row_cnt = sizeof($bug_table);

	if ($row_cnt) {

	?>
		<center>
		<div class='txtSmall'><br /><b>Deleted Reports by Project</b><br /><br /><br /></div>
		<table border=1 cellpadding=2 cellspacing=0 summary="Deleted Issues List">
		<tr class='rowOdd'><th>ID</th><th>Project</th><th>Original Author</th>
		<th>Submit Time</th><th>Summary</th><th>Deleted On</th><th>Deleted By</th></tr>
	<?php
		$displayed_cnt = 0;

		for ( $row = 0; $row <= $row_cnt; $row++ ) {
			if ( ($bug_table[$row]["Delete_Bug"] == 'Y') ) {

				if ( ($displayed_cnt++) % 2 )
					$class = 'rowOdd';
				else
					$class = 'rowEven';

				print "<tr>" .
						"\t<td class='$class'> <a href='codetrack.php?page=audit&amp;id=" .
						$bug_table[$row]["ID"] . "'> " . $bug_table[$row]["ID"] . "</a> " .
						"\t<td class='$class'> " . $bug_table[$row]["Project"] . " </td>\n" .
						"\t<td class='$class'> " . $bug_table[$row]["Submitted_By"] . " </td>\n" .
						"\t<td class='$class'> " . $bug_table[$row]["Submit_Time"] . " </td>\n" .
						"\t<td class='$class'> " . $bug_table[$row]["Summary"] . " </td>\n" .
						"\t<td class='$class'> " . $bug_table[$row]["Last_Updated"] . " </td>\n" .
						"\t<td class='$class'> " . $bug_table[$row]["Updated_By"] . " </td>\n" .
						"</tr>\n";
			}
		}
	}
	else
		print "<br /><br /><em>(No deleted issues found)</em><br />\n";
	print "</table></center><br />\n";
}



FUNCTION process_file_attachment( $tmp_filename, $requested_filename ) {

	GLOBAL $debug_g;

	if ( $debug_g )
		print "\n<pre> Temp File: '$tmp_filename' \n Requested filename: '$requested_filename' </pre>\n";


	#	WEBIFY LONG WINDOWS NAMES, SANITIZE THE FILENAME, AND MOVE UPLOADED FILE TO ATTACHMENTS DIRECTORY.
	#	ONLY ALLOW CHARACTERS WE EXPLICITY SPECIFY, DROP ALL OTHERS (I.E., UNICODE CRAP, ESCAPE/CONTROL CHARACTERS, ETC.)

	$filename = ereg_replace("( +)", "_",  trim($requested_filename));		# Convert all spaces to underscores
	$filename = eregi_replace('([^.0-9a-z_-]+)', "", $filename );				# Only allow numbers, alpha, and  . - _
	$filename = ereg_replace("(\.+)", ".", $filename);								# No directory traversal hacks, i.e., ".."

	if ( $debug_g )
		print "<pre> Scrubbed filename: '$filename' </pre>\n";

	if( strlen($filename) > CT_MAX_ATTACHMENT_FILENAME_SIZE )
		die("\n<br /><br /><center><h3> The filename of your attachment is too long.<br />The name cannot exceed " .
			CT_MAX_ATTACHMENT_FILENAME_SIZE . " characters.<br /><br />* Please contact the CodeTrack admin if this is incorrect. * " .
			"<br />( check \"CT_MAX_ATTACHMENT_FILENAME_SIZE\" setting )" );

	$legal_attachment_types = explode( ","  , CT_LEGAL_ATTACHMENTS );

	$legal = FALSE;
	foreach( $legal_attachment_types as $legal_type ) {
		if ( eregi( "\.$legal_type\$" , $filename) ) {		# E.g., [a_long_filename][.pdf]  ( $ = right end of string match )
			$legal = TRUE;
			break;
		}
	}
	if ( !$legal )
		die("\n<br /><br /><center><h3> Your attachment is not a permissible file.<br /></h3>Legal file types: " .
			"<tt>" . CT_LEGAL_ATTACHMENTS . "</tt><br /><br />" .
			 "* Please contact the CodeTrack admin if this is incorrect (check \"CT_LEGAL_ATTACHMENTS\" setting) *");

	if (move_uploaded_file($tmp_filename, "attachments/$filename")) {   	# PHP built-in function. Windows & *nix all use /
		chmod("attachments/$filename", 0600);
		return $filename;
	}
	else
		die("<br /><b>Fatal: File attachment transfer failed for TMP: $tmp_filename FILE: $filename </b><br /><center><h3>Please notify support!!!");
}



FUNCTION push_mime_info( $browser_string, $download_filename, $force_download ) {

	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

	if ( stristr($download_filename, ".xml") and !stristr($browser_string, "Opera") )	#  Painful, but Opera is stupid
		header("Content-Type: application/xml");
	else
		header("Content-Type: application/force-download");

	if ($force_download)
		$behavior = "attachment";
	else
		$behavior = "inline";

   header("Content-Disposition: $behavior; filename=\"$download_filename\"");

	return;
}



FUNCTION reverse_htmlspecialchars ( $html_encoded_string ) {

	# UNDO WHAT PHP'S htmlspecialchars() DOES, I.E.,  &quot; -->  "
	# DON'T KNOW WHY PHP DOESN'T ALREADY DO THIS...

	$trans = array_flip( get_html_translation_table( HTML_SPECIALCHARS ));  	# PHP BUILT-IN FUNCTION
	return ( strtr($html_encoded_string, $trans) );
}



FUNCTION rewrite_xml_file( $filename, &$parsed_tree, $major_element_tag ) {


	#	Take a pre-parsed xml tree (table), and create or rebuild an existing file


	$root_element_tag = $major_element_tag . "s";			# <users><user>, <bugs><bug>, etc.

	brute_force_lock( $filename );

	#	Open file write-only and set length to zero

	$fh = fopen($filename, 'wb')  or
		die("<br /><center><h3>Fatal: Couldn't truncate '$filename' ! Check that it's writable by the Apache owner.");

	$opening_content = "<?xml version=\"1.0\" ?>\r\n" .
							 "<!--  " . ucfirst($root_element_tag) . " database for CodeTrack.  Edit at your own risk.    -->\r\n" .
							 "<!--  Meaning: re-check for XML well-formedness and validity!  -->\r\n" .
							 "<!DOCTYPE $root_element_tag SYSTEM \"$root_element_tag.dtd\">\r\n" .
							 "<$root_element_tag>\r\n";

	$ret_val = fwrite($fh, $opening_content);
	if ( $ret_val == -1 )
		die("Fatal: Unable to write XML data to '$filename' !");

	foreach ($parsed_tree as $node) {

		$ret_val = fwrite( $fh, "\t<$major_element_tag>\r\n" );
		if ( $ret_val == -1 )
			die("Fatal: Unable to write XML data to '$filename' !");

		foreach ($node as $element => $contents) {
			$ret_val = fwrite($fh, "\t\t<$element>$contents</$element>\r\n");
			if ( $ret_val == -1 )
				die("Fatal: Unable to write XML data to '$filename' !");
		}
		$ret_val = fwrite($fh, "\t</$major_element_tag>\r\n");
		if ( $ret_val == -1 )
			die("Fatal: Unable to write XML data to '$filename' !");
	}
	$ret_val = fwrite($fh, "</$root_element_tag>");
	if ( $ret_val == -1 )
		die("Fatal: Unable to write XML data to '$filename' !");

	brute_force_lock_release( $filename );

	fclose($fh);
}



FUNCTION draw_tools_page() {

?>
	<div id='pageTitle'> System Tools </div><br />
	<div style='margin-left: 10%;'>
	<hr width='80%' class="hrBlack" align="left">
		<ul>
<?php if ( CT_DISPLAY_ADDRESSES )
			print "\t\t<li><a href='codetrack.php?page=users'>Address Book</a></li>\n";
?>
			<li><a href="codetrack.php?page=changepassword">Change Your Password</a></li>
			<li><a href="codetrack.php?page=export">Export Data</a></li>
		</ul>
		<br />
		<br />
		<b>A Few Tips</b><br />
		<ol>
			<li>To create a quick defect report in Excel, just click Export Data (above), then click Download.</li><br /><br />
			<li>On the New and Edit screens, send an email notification to other team members by clicking the envelope.</li><br /><br />
			<li>To see an audit trail of all the changes that have been made to an issue, click on the ID, then History.</li><br /><br />
			<li>Print pages in landscape mode.</li><br /><br />
			<li>If printing from Internet Explorer, enable prettier reports: Tools/Internet Options/Advanced/Print Background colors.</li><br /><br />
			<li>The Help button above is context-sensitive -- the text changes depending on which screen you're on.</li>
		</ol>
  <br /><br /><br />CodeTrack is <a href="http://www.gnu.org/licenses/gpl.html#SEC1" target=_blank>Free</a> software. Copyright &copy; 2001 Kenneth White
<?php
}



FUNCTION save_bug_data( $id='', $raw_bug_data, $attachment_data='', $original_submit_time='',
        $send_mail=FALSE, $cc_list='', $bug_table, $user_table ) {

 #
 # SAVE A NEW OR UPDATED BUG
 #
 # 1. Scrub and process attachment (if any)
 # 2. Prep notification email (if requested)
 # 3. Generate a submission (or modified) date/time stamp for this issue
 # 4. Construct a new (or preserve the old) ID
 # 5. Scrub the raw posted data and build up the complete <bug>...</bug> xml node
 # 6. Append the new node to the bugs.xml database
 # 7. Send out any email (if requested)
 # 8. Present success message where "OK" returns to home
 #

 GLOBAL $debug_g, $current_session_g;

 if ( $attachment_data ) {

  if ( $debug_g ) {
   print "<pre>\n*** Inside save_bug_data() *** \n\nattachment_data[]: \n"; var_dump($attachment_data); print "</pre>";
  }

  $sanitized_name = process_file_attachment( $attachment_data["tmp_name"], $attachment_data["name"] );
  $final_filename = '<Attachment>' . $sanitized_name . '</Attachment>';
 }
 else
  $final_filename = '';

	if ( CT_ENABLE_EMAIL ) {
		$b_submittor = eregi_replace("([^a-z0-9' -]+)", '', $raw_bug_data["Submitted_By"] );
		$b_assignee  = eregi_replace("([^a-z0-9' -]+)", '', $raw_bug_data["Assign_To"] );
		$b_modifier  = eregi_replace("([^a-z0-9' -]+)", '', $raw_bug_data["Modified_By"] );
		$b_project   = eregi_replace("([^a-z0-9' -]+)", '', $raw_bug_data["Project"] );
		$b_summary   = eregi_replace("([^a-z0-9'/@:\" -]+)", '', $raw_bug_data["Summary"] );
		$b_description = eregi_replace("([^a-z0-9'/@:\"\r\n. -]+)", '', $raw_bug_data["Description"] );
		$b_severity = eregi_replace("([^a-z0-9' -]+)", '', $raw_bug_data["Severity"] );
	}

	$bug_data = scrub_and_tag_form_data($raw_bug_data);


	#	DEFAULT CT_DATE_FORMAT IS: 12-JUN-2001 12:34 PM

	if ( $original_submit_time ) {

		$Submit_Time	= "<Submit_Time>" . $original_submit_time . "</Submit_Time>";
		$Updated_By		= "<Updated_By>" . $current_session_g["user_full_name"] . "</Updated_By>";
	}
	else
		$Submit_Time = "<Submit_Time>" . date( CT_DATE_FORMAT ) . "</Submit_Time>";

	$Last_Updated	= "<Last_Updated>" . date( CT_DATE_FORMAT ) . "</Last_Updated>";


	if ( $id )
		$this_is_new_bug = FALSE;
	else {
		$id = calc_next_node_id ( $bug_table );
		$this_is_new_bug = TRUE;
	}
	$ID = sprintf("<ID>%04d</ID>", $id);


	if ( !$bug_data['Project'] or !$bug_data['Severity'] or !$bug_data['Summary'] or !$bug_data['Description'] or !$bug_data['Submitted_By'])
		die("<br /><h3><center>Fatal:  Vital form data for create issue not received (Project/Severity/Summary/Description/Submitted By)!");


	#	BUILD THE COMPLETE XML TEXT NODE TO INSERT


	$node = "\t<bug>\n\t\t$ID\n\t\t$Submit_Time";

	foreach ($bug_data as $content)
		$node .= "\n\t\t$content";

	if ( $final_filename )						# Guaranteed.  Should be if ();
		$node .= "\n\t\t$final_filename";

	$node .= "\n\t\t$Last_Updated";

	if ( $original_submit_time )
		$node .= "\n\t\t$Updated_By";

	$node .= "\n\t</bug>";

	if ($debug_g) {
		print "<pre> About to append node to " . CT_XML_BUGS_FILE .
				" as: \n\n" . htmlspecialchars($node) . "</pre>\n";
	}

	append_xml_file_node( CT_XML_BUGS_FILE, $node, "</bugs>" );

	if ($debug_g)
		print "<pre> Back from append_xml_file_node().  About to check email request... </pre>";

	if ( ( CT_ENABLE_EMAIL ) and ( $send_mail == TRUE ) ) {

		if ($debug_g)
			print "<pre> Email has been requested, preparing now... </pre>\n";

		if ( isset($b_assignee) ) {
			for ($j=0; $j < sizeof($user_table); $j++)
				if ($user_table[$j]["Full_Name"] == $b_assignee) {
					$assignee_email = $user_table[$j]["Email"];
					break;
				}
			$cc_list[] = $assignee_email;			# Append to end of recipient mail address array
		}
		if ($this_is_new_bug) {
			$subject = "New issue (ID# $id) has been filed for the $b_project project by $b_submittor";
			$msg = "A $b_severity issue has been submitted by $b_submittor for the $b_project project\r\n" .
					 "____________________________________________________________\r\n\r\n" .
					 "Summary: $b_summary \r\n\r\n" .
					 "Description: $b_description \r\n\r\n" .
					 "____________________________________________________________\r\n\r\n" .
					 "This issue has been assigned to $b_assignee";
		}
		else {
			$subject = "An update to issue # $id on the $b_project project has been filed by $b_modifier";
			$msg = "Issue is currently rated as: $b_severity \r\n" .
					 "____________________________________________________________\r\n\r\n" .
					 "Summary: $b_summary \r\n\r\n" .
					 "Description: $b_description \r\n\r\n" .
					 "____________________________________________________________\r\n\r\n" .
					 "This issue has been assigned to $b_assignee.";
		}

		if ($debug_g) {
			print "<pre>Subject line in mail will be:\n$subject\n\n";
			print "Message will be:\n*****\n$msg\n***** \n\n\n";
		}


		foreach ($cc_list as $recipient) {

			if ($debug_g)
				print "About to send mail to: $recipient \n";

			if ( CT_USE_EXTRA_MAIL_HEADER )
				mail( $recipient, $subject, $msg,
						"From: " . CT_RETURN_ADDRESS . "\r\n"
						."Reply-To: " . CT_RETURN_ADDRESS . "\r\n"
						."X-Mailer: CodeTrack Mail" , "-f" . CT_RETURN_ADDRESS );
			else
				mail( $recipient, $subject, $msg,
						"From: " . CT_RETURN_ADDRESS . "\r\n"
						."Reply-To: " . CT_RETURN_ADDRESS . "\r\n"
						."X-Mailer: CodeTrack Mail" );
		}

		if ($debug_g)
			print "Mail has been sent. \n</pre>\n";

	}
	else {
		if ($debug_g)
			print "<pre> Email was not requested, skipping... </pre>\n";
	}

	if ($this_is_new_bug)
		$issue_action = 'created';
	else
		$issue_action = 'updated';


	print  "<span class'txtSmall'><center><br /><br /><br /><br /><em>Issue # $id has been $issue_action.</em>"
			."<br /><br /><br /><form action='codetrack.php' method=get>"
			."<input type=hidden name=page value=home><input type=submit value=' OK '></form>"
			."</center></span>\n";

	return;
}



FUNCTION update_user( $user_data, $user_table, $old_password=""  ) {

	#	Rebuild users.xml for a given user node.  If $old_password is passed in, only the password field will update

	GLOBAL $current_session_g, $debug_g;

	#	Re-sort users by ID
	usort( $user_table, create_function('$a,$b','return strcasecmp($a["ID"],$b["ID"]);') );

	$matching_id = '-1';
	for ($i=0; $i < sizeof($user_table); $i++)  {
		if ( $user_table[$i]["ID"] == $user_data["ID"] ) {
			$matching_id = $i;
			break;
		}
	}

	if ( $matching_id < 0)
		die("<br /><br /><center>Fatal:  No matching user ID found!");

	$username_to_update = $user_table[$matching_id]["Username"];

	if ( $current_session_g['role'] == 'Admin' ) {
		$next_page = "adminLinks";
	}
	else {

		if ( $username_to_update != $current_session_g['username'] )
			die("<br /><br /><center> Unless you're an admin, you can only change your own password. ");

		$old_hash  = $user_table[$matching_id]["Password"];
		$old_salt  = substr($old_hash, 0, 2);
		$plaintext = $old_password;

		if ( crypt($plaintext, $old_salt) != $old_hash ) {
			draw_change_password_form( $user_table );
			print "<br /><center><div class='txtSmall'><b>Password change failed.</b> <br />" .
					"The old password you gave was incorrect. <br />Please try again. </div>\n";
			return FALSE;
		}
		$next_page = "home";
	}

	if ( strlen($user_data["Password"]) < CT_MIN_PASSWORD_LENGTH )
		die("<br /><br /><center> Password length was less than the minimum " . CT_MIN_PASSWORD_LENGTH .
			"characters. Changes were NOT saved .");

	$user_data["Password"] = encrypt_password ( $user_data["Password"] );

	if ($debug_g) {
		print "\n<pre>\n\$user_data:\n"; var_dump($user_data); print "</pre>\n";
	}

	update_xml_file_node( CT_XML_USERS_FILE, $user_table, "user", $user_data );

	print "<br /><br /><br /><br /><center><b><em> Password for {$user_table[$matching_id]['Full_Name']} successfully changed. </em></b>" .
			"<br /><br /><br /><form action='codetrack.php' method=get>" .
			"<input type=hidden name='page' value='$next_page'><input type=submit value=' OK '></form></center>";
}



FUNCTION save_permission_data( $permission_data, $permission_table, $project_table ) {

	if ( (! isset($permission_data)) or (! isset($permission_data["Project_ID"])) )
		die("<br /><center>Fatal:  Received permission data are corrupt! Nothing was saved.");

	#
	#	Traverse existing permission table, and create a packed array of permissions
	#		from all projects except the one at hand.  Updated form data will then be appended,
	#		and the new (full) permission matrix will overwrite (or create) permissions.xml
	#

	$packed_index = 0;

	foreach ($permission_table as $row) {
		if ( $row["Project_ID"] != $permission_data["Project_ID"] ) {
			$permission_tree[$packed_index]["ID"]			= sprintf("%04d", $packed_index);
			$permission_tree[$packed_index]["Project_ID"]= $row["Project_ID"];
			$permission_tree[$packed_index++]["User_ID"]	= $row["User_ID"];
		}
	}
	#print "<pre>* Dumping preserved data * \n"; var_dump($permission_tree);

	#	Now add new permission data from form (selected users) to end of packed data array

	foreach ($permission_data["User_ID"] as $checked_user_id) {
		$permission_tree[$packed_index]["ID"]			= sprintf("%04d", $packed_index);
		$permission_tree[$packed_index]["Project_ID"]= $permission_data["Project_ID"];
		$permission_tree[$packed_index++]["User_ID"]	= $checked_user_id;
	}
	#print "\n\n* Dumping preserved and appended form data * \n"; var_dump($permission_tree);

	#	Save it, pass big data by reference

	rewrite_xml_file ( CT_XML_PERMISSIONS_FILE, $permission_tree, "permission" );

	print "<center><br /><br /><br /><div class='txtSmall'>Project permissions successfully saved.<br />\n".
			"<form method=get action=codetrack.php><input type=hidden name='page' value='adminLinks'>\n".
			"<br /><input type=submit value=' OK '></form>";
}



FUNCTION save_project_data( $project_data, $project_table ) {

	GLOBAL $debug_g;

	$id = calc_next_node_id( $project_table );
	$project_data["ID"] = sprintf("%02d", $id );

	$project_data["Title"] = eregi_replace( "[^A-Z0-9, '\"-]+", '', $project_data["Title"] );
	$project_data["Description"] = eregi_replace( "[^A-Z0-9 (),-]+", '', $project_data["Description"] );
	$project_data["Test_Lead"]  = eregi_replace( "[^A-Z0-9(),-]+", '', $project_data["Test_Lead"] );

	if (	!$project_data["Title"] or !$project_data["Description"] )
		die("<br /><h3><center>Fatal:  Vital form data for create project not received (Title or Description)!");

	print "\n\n<br /><br /><br />\n<center><table border=0 cellpadding=3 cellspacing=0 summary='Project Maintenance'>\n";
	print "<tr><td colspan=2 class='newUserSummaryTitle'>" .
			" &nbsp; New Project Summary<br />&nbsp;</td></tr>\n\n";

	foreach ($project_data as $field => $contents) {

		$field = strtr($field, '_', ' ');

		print "<tr><td class='newUserSummaryField' align=RIGHT> $field &nbsp;</td>" .
				"<td class='newUserSummaryInfo' align=LEFT>&nbsp; $contents &nbsp;</td></tr>\n\n";
	}
	print "</table>\n";

	$project_data = scrub_and_tag_form_data( $project_data );

	$node = "\t<project>";

	foreach ($project_data as $content)
		$node .= "\n\t\t$content";

	$node .= "\n\t</project>";

	if ($debug_g) {
		print "<pre>\n" . htmlspecialchars($node) . "</pre><br /><br />";
		#phpinfo();
	}
	append_xml_file_node( CT_XML_PROJECTS_FILE, $node, "</projects>" );

	print "<br /><br /><em>This project has been successfully added.</em>".
			"<br /><br /><br /><form action='codetrack.php' method=get><input type=hidden name='page' value='adminLinks'><input type=submit value=' OK '></form>".
			"</center>\n";

}



FUNCTION save_user_data( $user_data, $user_table, $notify_user='', $apache_vars ) {

	GLOBAL $debug_g;

	$host = $apache_vars["HTTP_HOST"];
	$uri  = $apache_vars["REQUEST_URI"];
	$ssl  = $apache_vars["HTTPS"];

	if ( $debug_g )
		print "<br /><b>Inside save_user_data</b><br />";

	if (!$user_data["First_Name"] or !$user_data["Last_Name"])
		die("<br /><h3><center>Fatal:  Vital form data for create user not received (First or Last Name)!");


	# Autogenerate password if none given

	if ($user_data["Password"])
		$initial_password = $user_data["Password"];
	else {
		for($len=14,$r=''; strlen($r)<$len; $r.=chr(!mt_rand(0,2)?mt_rand(48,57):(!mt_rand(0,1)?mt_rand(65,90):mt_rand(97,122))))
			;
		$initial_password = $r;
	}

	$id = calc_next_node_id($user_table);

	$user_data["ID"] = sprintf("%04d", $id );

	$user_data["First_Name"] = eregi_replace( "[^A-Z0-9,-]+", '', $user_data["First_Name"] );

	$user_data["Last_Name"]  = eregi_replace( "[^A-Z0-9,-]+", '', $user_data["Last_Name"] );

	#	$user_data["Phone"] = substr($phone,0,3) ."-". substr($phone,3,3) ."-". substr($phone,6,4);  # Too US-centric

	$phone = ereg_replace( '[^0-9\-\,\.\+ ]+', '', $user_data["Phone"] );		# Be nice to the rest of the world
	$user_data["Phone"] = substr($phone,0,32);


	$user_data["Email"] = eregi_replace( "[^A-Z0-9@._-]+", '', strtolower($user_data["Email"]) );

	$user_data["Full_Name"]= $user_data["First_Name"] ." ". $user_data["Last_Name"];

	$user_data["Initials"] = strtoupper(substr($user_data["First_Name"], 0, 1) .
									 substr($user_data["Last_Name"], 0, 1)) ;


	# Autogenerate username if none supplied

	if ($user_data["Username"])
		$user_data["Username"] = eregi_replace ( "[^a-z0-9._-]+", '', $user_data["Username"] );
	else
		$user_data["Username"] = eregi_replace ( "[^a-z0-9._-]+", '',
			 strtolower(substr($user_data["First_Name"], 0, 1) . $user_data["Last_Name"] ) );


	# Check for conflict with existing username, if so, suffix an accession number (jsmith, jsmith1, jsmith2, etc.)

	foreach ($user_table as $user_entry)
		$name_list[] = $user_entry["Username"];

	$username_accession = 0;
	$base_name = $user_data["Username"];
	while (in_array($user_data["Username"], $name_list))
		$user_data["Username"] =  $base_name . ++$username_accession;


	$user_data["Password"] = encrypt_password( $initial_password );

	if ( $debug_g )
		print "<br /><b>Passed Reg-Ep cleaning in save_user_data</b><br />";

	print "\n\n<br /><br /><br />\n<center><table border=0 cellpadding=3 cellspacing=0 summary='New User Confirmation'>\n";
	print "<tr><td colspan=2 class='newUserSummaryTitle'>" .
			" &nbsp; New User Summary<br />&nbsp;</td></tr>\n\n";

	foreach ($user_data as $field => $contents) {

		$field = strtr($field, '_', ' ');

		print "<tr><td class='newUserSummaryField' align=RIGHT> $field &nbsp;</td>" .
				"<td class='newUserSummaryInfo' align=LEFT> &nbsp;" .
				( ($field == 'Password') ? "<span id='displayPwd'>$initial_password</span>" : $contents ) . " &nbsp;</td></tr>\n\n";
	}
	print "</table>\n";


	if ( $notify_user ) {

		$protocol = ( (isset( $ssl )) ? "https://" : "http://" ) ;

		$url = $protocol . $host . $uri ;

		$msg = "\nA new account has been set up for you on CodeTrack. \r\n\r\n" .
				 "Your username is: " . $user_data["Username"] . "\r\n" .
				 "Your initial password is: $initial_password \r\n\r\n" .
				 "Please bookmark this URL: \r\n$url " .
				 "to access the system. \r\n\r\n" .
				 "Also, please verify the spelling and accuracy of the information below. \r\n\r\n" .
				 $user_data["Full_Name"] . ": " . $user_data["Role"];

		$recipient = $user_data["Email"];
	}


	$user_data = scrub_and_tag_form_data($user_data);

	if ( $debug_g )
		print "<br /></center><b>Passed Data scrub and tag in save_user_data</b><br />";

	$node = "\t<user>";

	foreach ($user_data as $content)
		$node .= "\n\t\t$content";

	$node .= "\n\t</user>";

	if ( $debug_g ) {
		print "<pre>\n" . htmlspecialchars($node) . "</pre><br /><br />";
		#phpinfo();
	}

	if ( $debug_g ) {
		print "<br /><b>About to append xml node in save_user_data</b><br />";
		flush();
	}

	append_xml_file_node( CT_XML_USERS_FILE, $node, "</users>" );


	if ( $notify_user ) {

		if ( $debug_g ) {
			print "<br /><b>About to mail verification to user in save_user_data</b><br />";
			flush();
		}

		$subject = "New account on the CodeTrack system";

		if ( CT_USE_EXTRA_MAIL_HEADER )
			mail( $recipient, $subject, $msg,
					"From: " . CT_RETURN_ADDRESS . "\r\n"
					."Reply-To: " . CT_RETURN_ADDRESS . "\r\n"
					."X-Mailer: CodeTrack Mail" , "-f" . CT_RETURN_ADDRESS );
		else
			mail( $recipient, $subject, $msg,
					"From: " . CT_RETURN_ADDRESS . "\r\n"
					."Reply-To: " . CT_RETURN_ADDRESS . "\r\n"
					."X-Mailer: CodeTrack Mail" );

	print "<br /><br />The new account information has been mailed to $recipient.";

		if ( $debug_g ) {
			print "<br /><b>Mail has been sent in save_user_data</b><br />";
			flush();
		}
	}

	print (($username_accession) ? '<br /><br /><strong>Note: This username already existed.  An accession number was added.</strong><br />' : '' ) .
			"<br /><br /><em>This user has been successfully added.</em>" .
			"<br /><br /><form action='codetrack.php' method=get><input type=hidden name='page' value='adminLinks'>" .
			"<input type=submit value=' OK '></form></center>\n";

	if ( $debug_g )
		print "<br /><b>About to successfully exit save_user_data!!!</b><br />";
}



FUNCTION scrub_and_tag_form_data( $data_array ) {

	foreach ($data_array as $element => $untrusted_content) {

		if ( $untrusted_content == '' ) {
			unset($data_array["$element"]);
		}
		else {
			$untrusted_content = trim(substr($untrusted_content, 0, CT_MAX_DESCR_SIZE));		# Keep it to a sane length

			#	Only allow reasonable alphanumerics in text & dropdown fields

			$content = eregi_replace('[^ 0-9a-z_@#$%();:?+*!=&,/"' . "'\n\t.-]+", '',
							$untrusted_content);

			$clean_data = "<$element>" . htmlspecialchars( trim($content) ) . "</$element>";

			$data_array["$element"] = $clean_data;
		}
	}
 return $data_array;
}



FUNCTION search_bugs( $pattern, $search_options ) {

	#	A poor-man's (*very* poor!) full text search.  Not terribly efficient, but at most, we're scanning 2-3MBs...

	GLOBAL $debug_g, $xml_array_g;

	#	Sanitize search string & block cross-site scripting

 $pattern = trim( eregi_replace('([^ .#&$=0-9a-z_-]+)', ' ', $pattern) );
 $pattern = eregi_replace('[ ]+', ' ', $pattern);

 if (!$pattern)
  die('<br /><br /><center><h3>No search terms were supplied. Enter a keyword or phrase, e.g., "blue screen".');

 if (!$search_options)
  die("<br /><br /><center><h3>No search fields specified.  You must choose at least one field (i.e., Summaries).");


 parse_xml_file( CT_XML_BUGS_FILE, CT_DROP_HISTORY, "ID", CT_ASCENDING);

 # Alias for generic data array by reference for speed, so do not call not parse_xml again

 $bug_array = &$xml_array_g;

 if ( $search_options['phrase'] ) {
  $needles[] = $pattern;
  $title = "Issues Containing: &nbsp;<em>&quot;{$pattern}&quot;</em>";
 }
 else {
  $needles = explode(" ", $pattern);
  $title = "Issues Containing: ";
  for ($j=0; $j < sizeof($needles); $j++) {
   if ($j)
    $title .= " or <em>&quot;{$needles[$j]}&quot;</em>";
   else
    $title .= "<em>&quot;{$needles[$j]}&quot;</em>";
  }
 }
 $title .= " &nbsp;(Detailed View Below)";

 $hits = array();
 $max_ceiling_hit = FALSE;

 foreach ($bug_array as $bug) {

  if ( isset($bug["Delete_Bug"]) )
   continue;

  $haystack = '';

  if ( isset($search_options['summary']) )
   $haystack  = $bug["Summary"];
  if ( isset($search_options['description']) )
   $haystack .= " " . $bug["Description"];
  if ( isset($search_options['comment']) )
   $haystack .= " " . $bug["Developer_Comment"];

  $haystack = trim( $haystack );

  foreach($needles as $needle)
   if ( stristr($haystack, $needle) ) {
    $hits[] = $bug;
    break;
   }

		if ( sizeof($hits) == CT_MAX_SEARCH_RESULTS ) {
			$max_ceiling_hit = TRUE;
			$title .= "<br /><em>Note: Too many hits for this search term. Showing first " . CT_MAX_SEARCH_RESULTS . " results.</em>";
			break;
		}
	}

	if ( $hits ) {

		#	Sort hits by ID

		usort($hits, create_function('$a,$b', 'return strcasecmp($a["ID"],$b["ID"]);'));

		draw_table( $hits, $title, '', $project_table );

		print "<center><span class='txtSmall'>Reports</span></center><hr class='hrBlack' width='80%'><br />\n";

		foreach($hits as $hit) {
			draw_read_only_table($hit);
			print "<br /> __________________________________________ <br /><br />\n";
		}
	}
	else
		print "<br /><br /><center><span class='txtSmall'>Search Results: <em>No matches found</em></span></center>\n";
}



FUNCTION set_session_cookie_load_page( $serialized_session, $page_to_load ) {

	print "<html><script type='text/javascript'>" .
			" document.cookie='codetrack_session=$serialized_session' ; ".
			" location.replace('codetrack.php?page=$page_to_load'); </script></html>";

	exit;
}



FUNCTION update_xml_file_node( $filename, $parsed_tree, $major_element_tag, $update_node ) {

	#	Take a pre-parsed xml data tree, update (replace) specific elements, re-write file

	$node_cnt	  = sizeof($parsed_tree);
	$target_id	  = $update_node["ID"];
	$node_updated = FALSE;


	#	Update specific elements with new content, but leave all remaining elements for node in tact

	for ( $node_index = 0; $node_index < $node_cnt; $node_index++ )
		if ( $parsed_tree[$node_index]["ID"] == $target_id ) {
			foreach ( $update_node as $element => $contents ) {
				#print "Updating node: $major_element_tag [ $element ] = $contents <br />\n";
				$parsed_tree[$node_index][$element] = $contents;
			}
			$node_updated = TRUE;
		}

	if (! $node_updated )
		die("<br /><center>Fatal:  XML Node ID $target_id not found! ");

	#print "<pre>";  var_dump($parsed_tree);  print "</pre>";

	#	Save it, pass big data by reference

	rewrite_xml_file( $filename, $parsed_tree, $major_element_tag );
}



FUNCTION valid_password( $login_data, $user_table, &$user_index ) {

	#	If username is matched, and password is correct, user index is passed back by reference

	$user_cnt = sizeof($user_table);

	$match = FALSE;

	for ($i=0; $i < $user_cnt; $i++) {
		if ( $login_data["username"] == $user_table[$i]["Username"] ) {
			$plaintext = $login_data["password"];
			$hashed    = $user_table[$i]["Password"];
			$salt      = substr($hashed, 0, 2);
			$match     = ( crypt($plaintext, $salt) == $hashed );
			break;
		}
	}
	if ($match) {
		$user_index = $i;
  return TRUE;
 }
 else
  return FALSE;
}

?>

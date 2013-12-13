<?php
//----------------------------------------------------------------------
// Copyright (c) 2011 Raytheon BBN Technologies
//
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and/or hardware specification (the "Work") to
// deal in the Work without restriction, including without limitation the
// rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Work, and to permit persons to whom the Work
// is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Work.
//
// THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
// IN THE WORK.
//----------------------------------------------------------------------

// Write a message to the session and redirect to the shibboleth local
// logout page.

require_once("util.php");
require_once("user.php");

$message = "You have logged out of the GENI " .
"Portal. Note however that you are still logged in to your identity " .
"provider (e.g. school) which may enable access to other sites. If this is a shared " .
"computer, clear your browsing history (cookies in particular) to " .
"fully log out. (On Chrome, select 'Clear Browsing Data...' and on " .
"Firefox select 'History->Clear Recent History...'.";
clear_session_with_message($message);

$shib_logout_url = get_logout_url();

$protocol = "http";
if (array_key_exists('HTTPS', $_SERVER)) {
  $protocol = "https";
}
$host  = $_SERVER['SERVER_NAME'];

$logout_dest = "$protocol://$host";
$encoded_dest = urlencode($logout_dest);
$redirect_url = "$shib_logout_url?return=$encoded_dest";
header("Location: $redirect_url");

<?php
//----------------------------------------------------------------------
// Copyright (c) 2012 Raytheon BBN Technologies
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

require_once("user.php");
require_once("header.php");
require_once('util.php');
require_once('sr_constants.php');
require_once('sr_client.php');
require_once("sa_constants.php");
require_once("sa_client.php");

function show_rspec_chooser() {
  $all_rmd = fetchRSpecMetaData();
  print "Choose Resources:\n";
  print "<select name=\"rspec_id\" id=\"rspec_select\""
    . " onchange=\"rspec_onchange()\""
    . ">\n";
  foreach ($all_rmd as $rmd) {
    $rid = $rmd['id'];
    $rname = $rmd['name'];
    $rdesc = $rmd['description'];
    print "<option value=\"$rid\" title=\"$rdesc\">$rname</option>\n";
  }
  //  print "<option value=\"paste\" title=\"Paste your own RSpec\">Paste</option>\n";
  //  print "<option value=\"upload\" title=\"Upload an RSpec\">Upload</option>\n";
  print "</select>\n";

  // RSpec entry area
  print '<span id="paste_rspec" style="display:none;vertical-align:top;">'
    . PHP_EOL;
  print '<label for="paste_rspec2">RSpec:</label>' . PHP_EOL;
  print "<textarea id=\"paste_rspec2\" name=\"rspec\" rows=\"10\" cols=\"40\""
    //. " style=\"display: none;\""
    . "></textarea>\n";
  print '</span>' . PHP_EOL;

  // RSpec upload
  print '<span id="upload_rspec" style="display:none;">'
    . PHP_EOL;
  print '<label for="rspec_file">RSpec File:</label>' . PHP_EOL;
  print '<input type="file" name="rspec_file" id="rspec_file" />' . PHP_EOL;
  print '</span>' . PHP_EOL;
}

function show_am_chooser() {
  $all_aggs = get_services_of_type(SR_SERVICE_TYPE::AGGREGATE_MANAGER);
  print "Choose Aggregate:\n";
  print '<select name="am_id">\n';
  foreach ($all_aggs as $agg) {
    $aggid = $agg['id'];
    $aggname = $agg['service_name'];
    $aggdesc = $agg['service_description'];
    print "<option value=\"$aggid\" title=\"$aggdesc\">$aggname</option>\n";
  }
  print "</select>\n";
}

$user = geni_loadUser();
if (!isset($user) || is_null($user) || ! $user->isActive()) {
  relative_redirect('home.php');
}

$mydir = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
add_js_script($mydir . '/slice-add-resources.js');
show_header('GENI Portal: Slices', $TAB_SLICES);

$slice_id = "None";
$slice_name = "None";
include("tool-lookupids.php");

if (!$user->isAllowed(SA_ACTION::ADD_SLIVERS, CS_CONTEXT_TYPE::SLICE, $slice_id)) {
  relative_redirect('home.php');
}

include("tool-breadcrumbs.php");

print "<h1>Add resources to GENI Slice: " . $slice_name . "</h1>\n";

// Put up a warning to upload SSH keys, if not done yet.
$keys = fetchSshKeys($user->account_id);
if (count($keys) == 0) {
  // No ssh keys are present.
  print "No ssh keys have been uploaded. ";
  print ("Please <button onClick=\"window.location='uploadsshkey.php'\">"
         . "Upload an SSH key</button> to enable logon to nodes.\n");
  print "<br/>\n";
}

print '<form id="f1" action="sliceresource.php" method="post">';
show_rspec_chooser();
print '<br/><br/>';
show_am_chooser();
print '<input type="hidden" name="slice_id" value="' . $slice_id . '"/>';
print '</form>';

//print "<p>Click to reserve a default set of resources at an available AM.</p>";
//print "<p>Otherwise click 'Cancel'.</p>";
print '<br/>';

print ("<button onClick=\"document.getElementById('f1').submit();\">"
       . "<b>Reserve Resources</b></button>\n");
print "<button onClick=\"history.back(-1)\">Cancel</button>\n";
print '<br/><br/>';

include("footer.php");
?>

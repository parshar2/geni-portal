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
require("logging_constants.php");
require("logging_client.php");
require_once("sr_client.php");
require_once("sr_constants.php");
require_once("pa_client.php");
require_once("pa_constants.php");
require_once("pa_client.php");
require_once("rq_client.php");
$user = geni_loadUser();
if (!isset($user) || is_null($user) || ! $user->isActive()) {
  relative_redirect('home.php');
}
include("tool-lookupids.php");

// Cancel request to join a project

if (! isset($pa_url)) {
  $pa_url = get_first_service_of_type(SR_SERVICE_TYPE::PROJECT_AUTHORITY);
}

if (array_key_exists("request_id", $_REQUEST)) {
  $request_id = $_REQUEST["request_id"];
  $request = get_request_by_id($pa_url, $user, $request_id);
} else {
  error_log("cancel-project-request got no project_id");
}
if (! isset($request) || is_null($request)) {
  error_log("No request from request_id");
  if (isset($project_id)) {
    $reqs = get_pending_requests_for_user($user->account_id, CS_CONTEXT_TYPE::PROJECT, $project_id);
    if (isset($reqs) && count($reqs) > 0) {
      if (count($reqs) > 1) {
	error_log("cancel-project-request: Got " . count($reqs) . " pending requests on same project for same member");
      }
      $request = $reqs[0];
      $request_id = $request['id'];
    } else {
      error_log("cancel-p-reqs: no pending reqs for this project, user");
    }
  } else {
    error_log("cancel-p-req: And no project_id. Fail");
  }
  if (! isset($request) || is_null($request)) {
    show_header('GENI Portal: Projects', $TAB_PROJECTS);
    include("tool-breadcrumbs.php");
    print "<h2>Error canceling project request</h2>\n";
    if (isset($request_id)) {
      print "Unknown request ID $request_id<br/>\n";
    }
    if (isset($project_id)) {
      print "No outstanding requests for you, ";
      print $user->prettyName();
      print ", and project ";
      if (isset($project_name)) {
	print $project_name;
      } else {
	print $project_id;
      }
      print "<br/>\n";
    } else {
      print "No project specified to look up that way.<br/>\n";
    }

    print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
    include("footer.php");
    exit();
  }
}

// So we have the request object
	/* id SERIAL, */
	/* context INT,  */
	/* context_id UUID, */
	/* request_text VARCHAR,  */
	/* request_type INT, -- 0 = JOIN, 1 = UPDATE_ATTRIBUTES, 2 = .... [That's all for now] */
	/* request_details VARCHAR, -- I suggest this is a JSON string with a dictionary of requested attributes for the case of a user wanting a change to his attributes */
	/* requestor UUID, */
	/* status INT, -- 0 = PENDING, 1 = APPROVED, 2 = CANCELED, 3 = REJECTED */
	/* creation_timestamp DATETIME, */
	/* resolver UUID, */
	/* resolution_timestamp DATETIME, */
	/* resolution_description VARCHAR */

if (false && $user->account_id != $request['requestor']) {
  error_log("cancel-p-reg got member_id != request's requestor. Member " . $user->account_id . " != " . $request['requestor'] . " for request " . $request['id']);
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error canceling project request</h2>\n";
  print "Request " . $request['id'] . " is not for you!<br/><br/>\n";
  // FIXME: Print other request details
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}
$member_id = $request['requestor'];
$member = $user;
$member_name = $user->prettyName();

error_log("REQ = " . print_r($request, true));
if ($request['request_type'] != RQ_REQUEST_TYPE::JOIN) {
  error_log("cancel-p-req: Non join request in request " . $request['id'] . ": " . $request['request_type']);
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error canceling project request</h2>\n";
  print "Request " . $request['id'] . " is not a join request, but a " . $request['request_type'] . "<br/>\n";
  // FIXME: Print other request details
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}

if ($request['context_type'] != CS_CONTEXT_TYPE::PROJECT) {
  error_log("cancel-p-req: Not a project, but " . $request['context_type']);
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error canceling project request</h2>\n";
  print "Request not a project request, but " . $request['context_type'] . "<br/>\n";
  // FIXME: Print other request details
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}

if (isset($project_id) && $request['context_id'] != $project_id) {
  error_log("cancel-p-req: Request project != given project: " . $request['context_id'] . " != " . $project_id);
}
$project_id = $request['context_id'];
$project = lookup_project($pa_url, $project_id);
$project_name = $project[PA_PROJECT_TABLE_FIELDNAME::PROJECT_NAME];
$lead_id = $project[PA_PROJECT_TABLE_FIELDNAME::LEAD_ID];
$lead = geni_loadUser($lead_id);
$leadname = $lead->prettyName();

// Now: was this a form submission (e.g. trying to handle the request?)
// FIXME: Validate those inputs
$reason = null;
$error = null;
if (array_key_exists('submit', $_REQUEST)) {
  $submit = $_REQUEST['submit'];
  if (array_key_exists('reason', $_REQUEST)) {
    $reason = $_REQUEST['reason'];
  } else {
    error_log("cancel-p-req got no reason");
    $reason = "";
  }
}
// OK, inputs validated

// Handle form submission
if (isset($submit)) {
  // Cancel project join request
  $cancelres = resolve_pending_request($pa_url, $user, $request_id, RQ_REQUEST_STATUS::CANCELLED, $reason);
  // FIXME: Handle result

  error_log("cancel-p-req canceled add of $member_name to project $project_name");
  // FIXME: Put up a page
    relative_redirect('home.php');
}

show_header('GENI Portal: Projects', $TAB_PROJECTS);

include("tool-breadcrumbs.php");

print "<h2>Cancel Project Join Request</h2>\n";
print "Cancel Request to join project $project_name:<br/>\n";
print "<br/>\n";
print "You may cancel your request to join project $project_name.<br/><br/>\n";
print "<b>Project</b>: <br/>\n";
// Show details on the project: name, purpose, lead
print "<table><tr><td>$project_name</td><td>" . $project[PA_PROJECT_TABLE_FIELDNAME::PROJECT_PURPOSE] . "</td><td>$leadname</td></tr></table>\n";

print "<br/><br/>\n";

print "<form action='cancel-join-project.php'>\n";
print "<input type='hidden' name='request_id' value='$request_id'>\n";
// Provide text box of reason
print "<b>Cancel Explanation</b>: <br/>\n";
print "<textarea name='reason' cols='60' rows='2'></textarea><br/>\n";
print "<br/>\n";
print "<button type=\"submit\" name='submit' value=\"cancel\"><b>Cancel Join Request</b></button>\n";


print "<input type=\"button\" value=\"Do not Cancel request\" onclick=\"history.back(-1)\"/>\n";
print "</form>\n";

include("footer.php");
?>

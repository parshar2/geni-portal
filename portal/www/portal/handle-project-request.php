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
require_once("cs_constants.php");
$user = geni_loadUser();
if (!isset($user) || is_null($user) || ! $user->isActive()) {
  relative_redirect('home.php');
}
include("tool-lookupids.php");

// Error if we don't have a project object
if (! isset($project) || is_null($project)) {
  error_log("No project set?");
  if (isset($project_id)) {
    show_header('GENI Portal: Projects', $TAB_PROJECTS);
    include("tool-breadcrumbs.php");
    print "<h2>Error handling project request</h2>\n";
    print "Unknown project ID $project_id<br/>\n";
    print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
    include("footer.php");
    exit();
  } else {
    /* error_log("doing redirect when _REQUEST: "); */
    /* foreach (array_keys($_REQUEST) as $key) { */
    /*   error_log("   [" . $key . "] = " . $_REQUEST[$key]); */
    /* } */
    relative_redirect('home.php');
  }
}

$lead_id = $project[PA_PROJECT_TABLE_FIELDNAME::LEAD_ID];
$lead = geni_loadUser($lead_id);
$leadname = $lead->prettyName();

// Handle a single project request
// This is the page the PI is pointed to via email
// Show details on the requestor
// Show details on the project
// Show text explaining what you are doing.
// provide drop-down of Role
// Provide text explaining different roles
// Provide text box of reason
// 3 buttons: 'Accept, Deny' Cancel (put off handling)

// The email from the PI supplied project_id, member_id, request_id

if (array_key_exists("request_id", $_REQUEST)) {
  $request = get_request_by_id($_REQUEST["reqeust_id"]);
} else {
  error_log("handle-project-request got no project_id");
}
if (! isset($request) || is_null($reqeust)) {
  error_log("No request from request_id");
  if (isset($member_id)) {
    $reqs = get_requests_pending_for_user($member_id, CS_CONTEXT_TYPE::PROJECT, $project_id);
    if (isset($reqs) && count($reqs) > 0) {
      if (count($reqs) > 1) {
	error_log("handle-p-reqs: Got " . count($reqs) . " pending requests on same project for same member");
      }
      $request = $reqs[0];
      $request_id = $request->id;
    } else {
      error_log("handle-p-reqs: no pending reqs for this project, user");
    }
  } else {
    error_log("handle-p-req: And no member id. Fail");
  }
  if (! isset($request) || is_null($request)) {
    show_header('GENI Portal: Projects', $TAB_PROJECTS);
    include("tool-breadcrumbs.php");
    print "<h2>Error handling project request</h2>\n";
    if (isset($request_id)) {
      print "Unknown request ID $request_id<br/>\n";
    }
    if (isset($member_id)) {
      print "No outstanding requests for member ";
      if (isset($member)) {
	print $member->prettyName();
      } else {
	print $member_id;
      }
      print " and project ";
      if (isset($project_name)) {
	print $project_name;
      } else {
	print $project_id;
      }
      print "<br/>\n";
    } else {
      print "No member specified to look up that way.<br/>\n";
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

if (isset($member)) {
  // That is the requestor. But make sure
  if ($member->account_id != $request->requestor) {
    error_log("handle-p-reg got member_id != request's requestor. Member " . $member->account_id . " != " . $request->requestor . " for request " . $request->id);
  }
}
$member = geni_loadUser($request->requestor);

if ($request->request_type != REQ_TYPE::JOIN) {
  error_log("handle-p-req: Non join request in request " . $request->id . ": " . $request->request_type);
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error handling project request</h2>\n";
  print "Request " . $request->id . " is not a join request, but a " . $request->request_type . "<br/>\n";
  // FIXME: Print other request details
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}

if ($request->context != REQ_CONTEXT_TYPE::PROJECT) {
  error_log("handle-p-req: Not a project, but " . $request->context);
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error handling project request</h2>\n";
  print "Request not a project request, but " . $request->context . "<br/>\n";
  // FIXME: Print other request details
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}

if (isset($project_id) && $request->context_id != $project_id) {
  error_log("handle-p-req: Request project != given project: " . $request->context_id . " != " . $project_id);
  $project_id = $request->context_id;
  $project = lookup_project($pa_url, $project_id);
}

// FIXME: Validate this user has authorization to change membership on this project
if (! $user->isAllowed('add_project_member', CS_CONTEXT_TYPE::PROJECT, $project_id)) {
  error_log("handle-p-req: User " . $user->prettyName() . " not authorized to add members to project $project_id");
  show_header('GENI Portal: Projects', $TAB_PROJECTS);
  include("tool-breadcrumbs.php");
  print "<h2>Error handling project request</h2>\n";
  print "You are not authorized to handle project requests for project $project_name<br/>\n";
  print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
  include("footer.php");
  exit();
}

// Basic inputs validated

// Now: was this a form submission (e.g. trying to handle the request?)
// FIXME: Validate those inputs
// submit, reason, role
$reason = null;
$role = null;
$error = null;
if (array_key_exists('submit', $_REQUEST)) {
  $submit = $_REQUEST['submit'];
  if ($submit == 'approve') {
    error_log("handle-p-req: request is being approved");
  } elseif ($submit != 'reject') {
    error_log("handle-p-req: huh? what is a submit value of $submit?");
    // Pretend we got no submittal
    $submit = null;
  } else {
    error_log("handle-p-req: request is being denied");
  }
  if (array_key_exists('reason', $_REQUEST)) {
    $reason = $_REQUEST['reason'];
  } else {
    error_log("handle-p-req got no reason");
    $reason = "";
  }
  if (array_key_exists('role', $_REQUEST)) {
    $role = intval($_REQUEST['role']);
  } else {
    error_log("handle-p-req got no role: default to member");
    $role = CS_ATTRIBUTE_TYPE::MEMBER;
  }
}

// OK, inputs validated

// Handle form submission
if (isset($submit)) {
  if ($submit == 'approve') {
    // call pa add member
    $addres = add_project_member($pa_url, $project_id, $member_id, $role);
    // FIXME: Check result

//	$appres = approve_request($request_id, $reason);
    // FIXME: Check result

    // log this
    $context[LOGGING_ARGUMENT::CONTEXT_TYPE] = CS_CONTEXT_TYPE::PROJECT;
    $context[LOGGING_ARGUMENT::CONTEXT_ID] = $project_id;
    $context2[LOGGING_ARGUMENT::CONTEXT_TYPE] = CS_CONTEXT_TYPE::MEMBER;
    $context2[LOGGING_ARGUMENT::CONTEXT_ID] = $member_id;
    $log_url = get_first_service_of_type(SR_SERVICE_TYPE::LOGGING_SERVICE);
    log_event($log_url, "Added $member_name to project " . $project_name, array($context, $context_2), $user->account_id);
    error_log("handle-p-req added $member_name to project $project_name with role $role");
  
    // FIXME: Email the member
    $email = $user->email();
    $name = $user->prettyName();
    $rolestr = CS_ATTRIBUTE_TYPE_NAME[$role];
    $message = "Your request to join GENI project $project_name was accepted!
You have been added to the project with role $rolestr.<br/>
Log in to the GENI portal to start using this project.

Reason:
$reason

Thank you,
$name\n";
    mail($member_name . "<" . $member->email() . ">",
       "Added to GENI project $project_name",
       $message,
       "Reply-To: $email" . "\r\n" . "From: $name <$email>");

    // FIXME: Put up a page
    relative_redirect('project.php?project_id=$project_id');

  } else {
//	$appres = reject_request($request_id, $reason);
    error_log("handle-p-req denied $member_name membership in $project_name");
  // FIXME: Email the member
    $email = $user->email();
    $name = $user->prettyName();
    $message = "Your request to join GENI project $project_name was denied.

Reason:
$reason

Thank you,
$name\n";
    mail($member_name . "<" . $member->email() . ">",
       "Request to join GENI project $project_name denied",
       $message,
       "Reply-To: $email" . "\r\n" . "From: $name <$email>");
  // FIXME: Put up a page
    relative_redirect('project.php?project_id=$project_id');
  }
}

show_header('GENI Portal: Projects', $TAB_PROJECTS);

include("tool-breadcrumbs.php");

print "<h2>Handle Project Join Request</h2>\n";
print "Handle Request to join project $project_name:<br/>\n";
print "<br/>\n";

print "On this page, you can handle the request by $member_name to join project $project_name<br/>\n";
print "You can accept or deny their request, or cancel (put off handling this request)\n";
print "If you accept or deny, give a reason justifying your decision (e.g. 'You are not in my class.' or 'Student in Section B.')\n";
print "If you accept the request, you must specify what role this member will have on the project.\n";

// FIXME: Explain different roles: who gets to add members? create slices? work on a slice?

print "<br/><br/>\n";

print "<b>Requestor</b>: <br/>\n";
// Show details on the requestor: name, email, institution
print "<table><tr><td>" . $member->prettyName() . "</td><td>" . $member->email() . "</td><td>" . $member->affiliation . "</td></tr></table>\n";

print "<b>Project</b>: <br/>\n";
// Show details on the project: name, purpose, lead
print "<table><tr><td>$project_name</td><td>" . $project[PA_PROJECT_TABLE_FIELDNAME::PURPOSE] . "</td><td>$lead_name</td></tr></table>\n";

print "<b>Request Explanation</b>: <br/>\n";
print "<textarea disabled='disabled'>" . $request->$reqeust_text . "</textarea>\n";

print "<br/><br/>\n";

print "<form action='handle-project-request.php'>\n";
print "<input type='hidden' name='request_id' value='$request_id'>\n";

// provide drop-down of Role
print "<b>Project Role</b>: \n";
print "<input type='radio' name='role' value='" . CS_ATTRIBUTE_TYPE::ADMIN . "'> Admin (can add/remove members)<br/>\n";
print "<input type='radio' name='role' value='" . CS_ATTRIBUTE_TYPE::MEMBER . "' checked>Member (default)<br/>\n";
print "<input type='radio' name='role' value='" . CS_ATTRIBUTE_TYPE::AUDITOR . "'>Auditor (ready only)<br/>\n";

// Provide text box of reason
print "<b>Response Explanation</b>: \n";
print "<textarea name='reason' cols='60' rows='2'></textarea>\n";

// 3 buttons: 'Accept, Deny' Cancel (put off handling)

// Buttons go to:
//	approve_request(request_id, resolution_description)
//	reject_request(request_id, resolution_description)
print "<button type=\"submit\" name='submit' value=\"approve\"><b>Approve Join Request</b></button>\n";
print "<button type=\"submit\" name='submit' value=\"reject\"><b>Deny Join Request</b></button>\n";


print "<input type=\"button\" value=\"Cancel\" onclick=\"history.back(-1)\"/>\n";
print "</form>\n";

include("footer.php");
?>
<?php

// Test procedure for request infrastructure for SA, PA, MA

require_once('util.php');
require_once('rq_constants.php');
require_once('rq_client.php');
require_once('response_format.php');
require_once('sr_constants.php');
require_once('sr_client.php');
require_once('pa_client.php');
require_once('sa_client.php');
require_once('cs_constants.php');
require_once('user.php');

error_log("RQ TEST\n");

$sr_url = get_sr_url();
$sa_url = get_first_service_of_type(SR_SERVICE_TYPE::SLICE_AUTHORITY);
$pa_url = get_first_service_of_type(SR_SERVICE_TYPE::PROJECT_AUTHORITY);
$ma_url = get_first_service_of_type(SR_SERVICE_TYPE::MEMBER_AUTHORITY);

$signer = geni_loadUser();

function dump_rows($rows)
{
  foreach($rows as $row) {
    dump_row($row);
  }
}

function dump_row($row)
{
  error_log("Row = " . print_r($row, true));
}

function test_requests_for_url($url, $context_type, $context_id)
{
  global $signer;
  $insert_result = create_request($url, $signer, $context_type, $context_id, REQUEST_TYPE::JOIN,
				  'foobar', '');
  error_log("IR = " . print_r($insert_result, true));
  $request_id = $insert_result;
  resolve_pending_request($url, $signer, $request_id, REQUEST_STATUS::APPROVED, 'resolved');
  $rows = get_requests_for_context($url, $signer, $context_type, $context_id);
  dump_rows($rows);
  $rows = get_requests_by_user($url, $signer, $signer->account_id, $context_type, $context_id);
  dump_rows($rows);
  $row = get_request_by_id($url, $signer, $request_id);
  dump_row($row);
  $num_pending = get_number_of_pending_requests_for_user($url, $signer, $signer->account_id, 
						 $context_type, $context_id);
  error_log("Num_pending = " . print_r($num_pending, true));
  $pending = get_pending_requests_for_user($url, $signer, $signer->account_id, 
						 $context_type, $context_id);
  dump_rows($pending);
}

$project_ids = lookup_projects($pa_url);
// error_log("PIDS = " . print_r($project_ids, true));
$project_id = $project_ids[0]['project_id'];
// error_log("PID = " . print_r($project_id, true));

$slice_ids = lookup_slice_ids($sa_url, $signer, $project_id);
// error_log("SIDS = " . print_r($slice_ids, true));
$slice_id = $slice_ids[0];

test_requests_for_url($sa_url, CS_CONTEXT_TYPE::SLICE, $slice_id);


relative_redirect('debug');

?>

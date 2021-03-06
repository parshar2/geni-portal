<?php
//----------------------------------------------------------------------
// Copyright (c) 2012-2016 Raytheon BBN Technologies
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

// Client-side interface to GENI Clearinghouse Slice Authority (SA)
//
// Consists of these methods:
//   get_slice_credential(slice_id, user_id)
//   slice_id <= create_slice(project_id, project_name, slice_name, owner_id);
//   slice_ids <= lookup_slices(project_id);
//   slice_details <= lookup_slice(slice_id);
//   slice_details <= lookup_slice_by_urn(slice_urn);
//   renew_slice(slice_id, expiration, owner_id);
//   get_slice_members(sa_url, slice_id, role=null) // null => Any
//   get_slices_for_member(sa_url, member_id, is_member, role=null)
//   lookup_slice_details(sa_url, slice_uuids)
//   get_slices_for_projects(sa_url, project_uuids, allow_expired=false)
//   modify_slice_membership(sa_url, slice_id,
//        members_to_add, members_to_change_role, members_to_remove)
//   add_slice_member(sa_url, project_id, member_id, role)
//   remove_slice_member(sa_url, slice_id, member_id)
//   change_slice_member_role(sa_url, slice_id, member_id, role)

require_once('sa_constants.php');
require_once('chapi.php');
require_once 'ma_client.php';
require_once 'am_client.php';
require_once('client_utils.php');

$SACHAPI2PORTAL = array('SLICE_UID' => SA_SLICE_TABLE_FIELDNAME::SLICE_ID,
			'SLICE_NAME' => SA_SLICE_TABLE_FIELDNAME::SLICE_NAME,
			'_GENI_PROJECT_UID' => SA_SLICE_TABLE_FIELDNAME::PROJECT_ID,
			'SLICE_EXPIRATION' => SA_SLICE_TABLE_FIELDNAME::EXPIRATION,
			'SLICE_CREATION' => SA_SLICE_TABLE_FIELDNAME::CREATION,
			'_GENI_SLICE_OWNER' => SA_SLICE_TABLE_FIELDNAME::OWNER_ID,
			'SLICE_URN' => SA_SLICE_TABLE_FIELDNAME::SLICE_URN,
			'_GENI_SLICE_EMAIL' => SA_SLICE_TABLE_FIELDNAME::SLICE_EMAIL,
			'SLICE_EXPIRED' => SA_SLICE_TABLE_FIELDNAME::EXPIRED,
			'SLICE_DESCRIPTION' => SA_SLICE_TABLE_FIELDNAME::SLICE_DESCRIPTION);

$SAMEMBERCHAPI2PORTAL = array('SLICE_ROLE' => SA_SLICE_MEMBER_TABLE_FIELDNAME::ROLE,
			      'SLICE_MEMBER_UID' => SA_SLICE_MEMBER_TABLE_FIELDNAME::MEMBER_ID);

$SADETAILSKEYS = array('SLICE_UID',
		     'SLICE_NAME',
		     '_GENI_PROJECT_UID',
		     'SLICE_EXPIRATION',
		     'SLICE_CREATION',
		     '_GENI_SLICE_OWNER',
		     'SLICE_URN',
		     '_GENI_SLICE_EMAIL',
		     'SLICE_EXPIRED',
		     'SLICE_DESCRIPTION');

function slice_member_chapi2portal($row)
{
  global $SAMEMBERCHAPI2PORTAL;
  return convert_row($row, $SAMEMBERCHAPI2PORTAL);
}


function slice_details_chapi2portal($row)
{
  global $SACHAPI2PORTAL;
  return convert_row($row, $SACHAPI2PORTAL);
}



/* return a slice credential for given SLICE ID and user */
function get_slice_credential($sa_url, $signer, $slice_id, $cert=NULL)
{
  $slice_urn = get_slice_urn($sa_url, $signer, $slice_id);

  $signer_cert = $signer->certificate();
  $signer_key = $signer->privateKey();
  if (is_null($cert)) {
    $cert = $signer_cert;
  }
  if (! isset($cert) || is_null($cert) || $cert == "") {
    error_log("Cannot get_slice_cred without a user cert");
    throw new Exception("Cannot get_slice_cred without a user cert");
  }
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $result = $client->get_credentials($slice_urn, $client->creds(),
                                     $client->options());
  if (!is_array($result)) {
    return Null;
  }

  $result = $result[0]['geni_value'];

  //error_log("GSC result = ".print_r($result, True));
  return $result;
}

/* Create a new slice record in database, return slice_id */
function create_slice($sa_url, $signer, $project_id, $project_name, $slice_name,
                      $owner_id, $description='')
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $lookup_project_urn_options = array('match' => array('PROJECT_UID' => $project_id), 'filter' => array('PROJECT_URN'));
  $options = array_merge($lookup_project_urn_options, $client->options());
  $lookup_project_urn_return = $client->lookup_projects($client->creds(),
                                                        $options);
  $project_urns = array_keys($lookup_project_urn_return);
  $project_urn = $project_urns[0];
  $options = array('fields' =>
		   array('SLICE_NAME' => $slice_name,
			 'SLICE_DESCRIPTION' => $description,
			 'SLICE_PROJECT_URN' => $project_urn));
  $options = array_merge($options, $client->options());
  $slice = $client->create_slice($client->creds(), $options);
  $converted_slice = slice_details_chapi2portal($slice);
  // CHAPI: TODO reformat return arguments
  return $converted_slice;
}

/* Lookup slice ids for given project */
function lookup_slice_ids($sa_url, $signer, $project_id)
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('PROJECT_URN' => $project_id),
		   'filter' => array('SLICE_UID'));
  $options = array_merge($options, $client->options());
  $slices = $client->lookup_slices($client->creds(), $options);

  return array_map(function($x) { return $x['SLICE_UID']; }, $slices);
}

/* Lookup slice ids for given project and owner */
/* function lookup_slice_ids_by_project_and_owner($sa_url, $project_id, $owner_id) */
/* { */
/*   $lookup_slice_ids_message['operation'] = 'lookup_slice_ids'; */
/*   $lookup_slice_ids_message[SA_ARGUMENT::PROJECT_ID] = $project_id; */
/*   $lookup_slice_ids_message[SA_ARGUMENT::OWNER_ID] = $owner_id; */
/*   $slice_ids = put_message($sa_url, $lookup_slice_ids_message); */
/*   return $slice_ids; */
/* } */

/* Lookup slice ids for given owner */
/* function lookup_slice_ids_by_owner($sa_url, $owner_id) */
/* { */
/*   $lookup_slice_ids_message['operation'] = 'lookup_slice_ids'; */
/*   $lookup_slice_ids_message[SA_ARGUMENT::OWNER_ID] = $owner_id; */
/*   $slice_ids = put_message($sa_url, $lookup_slice_ids_message); */
/*   return $slice_ids; */
/* } */

/* lookup slice ids by slice name, project ID */
/* function lookup_slices_by_project_and_name($sa_url, $project_id, $slice_name) */
/* { */
/*   $lookup_slice_ids_message['operation'] = 'lookup_slice_ids'; */
/*   $lookup_slice_ids_message[SA_ARGUMENT::PROJECT_ID] = $project_id; */
/*   $lookup_slice_ids_message[SA_ARGUMENT::SLICE_NAME] = $slice_name; */
/*   $slice = put_message($sa_url, $lookup_slice_ids_message); */
/*   return $slice_ids; */
/* } */

/* lookup a set of slices by name, project_id, member_id */
/* That is, the set of slices for which this member_id is a member */
function lookup_slices($sa_url, $signer, $project_id, $member_id)  //
{
  $client = XMLRPCClient::get_client($sa_url, $signer);

  if ($member_id) {
    $member_urn = get_member_urn(sa_to_ma_url($sa_url), $signer, $member_id);
    $slices = $client->lookup_slices_for_member($member_urn, $client->creds(),
                                                $client->options());
  } else {
    $match = array('_GENI_PROJECT_UID' => $project_id);
    $filter = array('SLICE_NAME', 'SLICE_URN', 'SLICE_UID', '_GENI_PROJECT_UID');
    $options = array('match' => $match);
    $options = array_merge($options, $client->options());
    $slices = $client->lookup_slices($client->creds(), $options);
  }

  $converted_slices = array();
  foreach ($slices as $slice) { $converted_slices[] = slice_details_chapi2portal($slice); }
  return $converted_slices;
}

/* lookup details of slice of given id */
// Return array(id, name, project_id, expiration, owner_id, urn)
function lookup_slice($sa_url, $signer, $slice_id)
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('SLICE_UID' => $slice_id),
		   // 'filter' => array('SLICE_URN')  // MIK: do we get everything if no filter specified?
		   );
  $options = array_merge($options, $client->options());
  $slices = $client->lookup_slices($client->creds(), $options);
  $urns = array_keys($slices);
  $urn = $urns[0];
  $slice = $slices[$urn];

  $slice = slice_details_chapi2portal($slice);
  return $slice;

}

/* lookup details of slice of given slice URN */
// Return array(id, name, project_id, expiration, owner_id, urn)
function lookup_slice_by_urn($sa_url, $signer, $slice_urn)
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('SLICE_URN' => $slice_urn),
		   // 'filter' => array('SLICE_URN')  // MIK: do we get everything if no filter specified?
		   );
  $options = array_merge($options, $client->options());
  $slices = $client->lookup_slices($client->creds(), $options);
  if ($slices) {
    $urns = array_keys($slices);
    $urn = $urns[0];
    $slice = $slices[$urn];
    return array($slice['SLICE_UID'],
  	       $slice['SLICE_NAME'],
  	       $slice['_GENI_PROJECT_UID'],
  	       $slice['SLICE_EXPIRATION'],
  	       $slice['_GENI_SLICE_OWNER'],
  	       $slice['SLICE_URN']);
  } else { // no slice with this URN was found
    return array();
  }
}

// FIXME: lookup_slice_details_by_ids($sa_url, $slice_ids_list)
// FIXME: lookup_slices_project_member($sa_url, $project_id=null, $member_id, $is_member, $role=null)

/* Renew slice of given id */
function renew_slice($sa_url, $signer, $slice_id, $expiration)
{
  $slice_urn = get_slice_urn($sa_url, $signer, $slice_id);

  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('fields' => array('SLICE_EXPIRATION' => $expiration),
		   );
  $options = array_merge($options, $client->options());
  $res = $client->update_slice($slice_urn, $client->creds(), $options);
  return $res;
}

function _conv_mid2urn_s($sa_url, $signer, $alist)
{
  return array_map(function ($mid) use ($sa_url, $signer) { return get_member_urn(sa_to_ma_url($sa_url), $signer, $mid); }, $alist);
}

function _conv_mid2urn_map_s($sa_url, $signer, $amap)
{
  global $CS_ATTRIBUTE_TYPE_NAME;
  $narr = array();
  foreach ($amap as $mid => $v) {
    $murn = get_member_urn(sa_to_ma_url($sa_url), $signer, $mid);
    $role = strtoupper($CS_ATTRIBUTE_TYPE_NAME[$v]);
    $narr[] = array('SLICE_MEMBER' => $murn, 'SLICE_ROLE' => $role);
  }
  return $narr;
}

// Make a POA geni_update_users call on all aggregates at which this slice
// has resources, updating keys for existing members, removing keys
// for removed members
function update_user_keys_on_slivers($sa_url, $signer, $slice_id, $slice_urn,
                                     $members_to_add, $members_to_change,
                                     $members_to_remove)
{
  $username = $signer->username;
  $ma_url = sa_to_ma_url($sa_url);
  $am_urls = array();

  // Get list of aggregates for slice
  $aggs_for_slice = aggregates_in_slice($sa_url, $signer, $slice_urn);
  //  error_log("SLIVER_INFO.RES = " . print_r($aggs_for_slice, true));
  foreach($aggs_for_slice as $agg_info) {
    if(!array_key_exists(SERVICE_ATTRIBUTE_TAG, $agg_info)) continue;
    $agg_attributes = $agg_info[SERVICE_ATTRIBUTE_TAG];
    $am_type = $agg_attributes[SERVICE_ATTRIBUTE_AM_TYPE];
    if ($am_type != SERVICE_ATTRIBUTE_INSTAGENI_AM) continue; // This call only works for IG/PG racks
    $am_url = $agg_info[SR_TABLE_FIELDNAME::SERVICE_URL];
    $am_urls[] = $am_url;
  }
  //  error_log("AM_URLS = " . print_r($am_urls, true));

  // If there are no AM URLs (e.g. no IG/PG AMs), then nothing to do here. Bail.
  if (count($am_urls) < 1) {
    return True;
  }

  // Generate slice_users list of dictionaries: [{"urn" : urn, "keys" : [key1, key2]}, ...]
  $slice_users_list = array();
  $slice_members = get_slice_members($sa_url, $signer, $slice_id);
  //  error_log("MEMBERS = " . print_r($slice_members, true));
  foreach($slice_members as $member_info) {
    $member_id = $member_info[MA_MEMBER_TABLE_FIELDNAME::MEMBER_ID];
    $member_urn = get_member_urn($ma_url, $signer, $member_id);
    $keys_res = lookup_public_ssh_keys($ma_url, $signer, $member_id);
    //    error_log("MEMBER_ID = " . print_r($member_id, true));
    //    error_log("KEYS_RES = " . print_r($keys_res, true));
    $member_public_keys = array();
    foreach($keys_res as $key_info) {
      $public_key = $key_info[MA_SSH_KEY_TABLE_FIELDNAME::PUBLIC_KEY];
      $member_public_keys[] = $public_key;
    }
    $member_entry = array('urn' => $member_urn, 'keys' => $member_public_keys);
    $slice_users_list[] = $member_entry;
  }

  // For each removed member, add an empty entry to list (to remove their SSH keys)
  foreach($members_to_remove as $member_to_remove) {
    $member_entry = array('urn' => $member_to_remove, 'keys' => array());
    $slice_users_list[] = $member_entry;
  }

  $slice_users = array('geni_users' => $slice_users_list);
  //  error_log("GENI_USERS = " . print_r($slice_users, true));

  // invoke omni to call the geni_update_users POA
  $slice_users_json = json_encode($slice_users);
  $slice_users_filename = writeDataToTempFile($slice_users_json);
  $args = array("--optionsfile", $slice_users_filename,
		"poa", $slice_urn, 'geni_update_users');
  $res = invoke_omni_function($am_urls, $signer, $args,
			      array(), 0, 0, false, NULL, 3);
  //   error_log("Update_user_keys_on_slivers.RES = " . print_r($res, true));

  // Clean up JSON file and invocation directory
  unlink($slice_users_filename);

  return $res;

}

// Modify slice membership according to given lists to add/change_role/remove
// $members_to_add and $members_to_change role are both
//     dictionaries of {member_id => role, ....}
// $members_to_delete is a list of member_ids
function modify_slice_membership($sa_url, $signer, $slice_id,
				 $members_to_add,
				 $members_to_change,
				 $members_to_remove)
{
  $slice_urn = get_slice_urn($sa_url, $signer, $slice_id);

  $client = XMLRPCClient::get_client($sa_url, $signer);
  $members_to_add = _conv_mid2urn_map_s($sa_url, $signer, $members_to_add);
  $members_to_change = _conv_mid2urn_map_s($sa_url, $signer, $members_to_change);
  $members_to_remove = _conv_mid2urn_s($sa_url, $signer, $members_to_remove);

  $options = array();
  if (sizeof($members_to_add)>0)    { $options['members_to_add']    = $members_to_add; }
  if (sizeof($members_to_change)>0) { $options['members_to_change'] = $members_to_change; }
  if (sizeof($members_to_remove)>0) { $options['members_to_remove'] = $members_to_remove; }
  $options = array_merge($options, $client->options());
  $res = $client->modify_slice_membership($slice_urn, $client->creds(), $options);
  //  error_log("MSM.RES = " . print_r($res, true));
  $update_user_res = update_user_keys_on_slivers($sa_url, $signer, $slice_id, $slice_urn,
						 $members_to_add, $members_to_change,
						 $members_to_remove);
  return $res;
}

// Add a member of given role to given slice
function add_slice_member($sa_url, $signer, $slice_id, $member_id, $role)
{
  $member_roles = array($member_id => $role);
  $result = modify_slice_membership($sa_url, $signer, $slice_id,
				    $member_roles, array(), array());
  return $result;
}

// Remove a member from given slice
function remove_slice_member($sa_url, $signer, $slice_id, $member_id)
{
  $member_to_remove = array($member_id);
  $result = modify_slice_membership($sa_url, $signer, $slice_id,
				    array(), array(), $member_to_remove);
  return $result;
}

// Change role of given member in given slice
function change_slice_member_role($sa_url, $signer, $slice_id, $member_id, $role)
{
  $member_roles = array($member_id => $role);
  $result = modify_slice_membership($sa_url, $signer, $slice_id,
				    array(), $member_roles, array());
  return $result;
}

// Return list of member ID's and roles associated with given slice
// If role is provided, filter to members of given role
function get_slice_members($sa_url, $signer, $slice_id, $role=null)
{
  $slice_urn = get_slice_urn($sa_url, $signer, $slice_id);

  $client = XMLRPCClient::get_client($sa_url, $signer);
  if (! is_null($role)) {
    $options = array('match' => array('SLICE_UID' => $slice_id,'ROLE'=>$role));
  } else {
    $options = array('match' => array('SLICE_UID' => $slice_id));
  }
  $options = array_merge($options, $client->options());
  $result = $client->lookup_slice_members($slice_urn, $client->creds(), $options);
  $converted_result = array();
  foreach($result as $row) {
    $converted_row = convert_role(slice_member_chapi2portal($row));
    $converted_result[] = $converted_row;
  }
  return $converted_result;
}

// Return list of slice_id's, member ID's and roles associated with slice of a given project
// If role is provided, filter to members of given role
// CHAPI: This should be [{'slice_id' => slice1, 'role' => role1, 'member_id' => mem1}*]
//

// slice-> PROJECT_URN
//
function get_slice_members_for_project($sa_url, $signer, $project_id, $role=null)
{
  // this probably wont work unless you are an operator
  //error_log("get_slice_members_for_project called");
  $client = XMLRPCClient::get_client($sa_url, $signer);

  // get all slices of project
  $options = array('match' => array('_GENI_PROJECT_UID'=>$project_id));
  $options = array_merge($options, $client->options());
  $tuples = $client->lookup_slices($client->creds(), $options);

  $member_urn = $signer->urn;
  $my_memberships = $client->lookup_slices_for_member($member_urn,
                                                      $client->creds(),
                                                      $client->options());

  // Need to pull out the slice_urn's from $my_memerships
  $my_slice_urns = array();
  foreach ($my_memberships as $member_urn => $slice_info) {
    $slice_urn = $slice_info['SLICE_URN'];
    $my_slice_urns[$slice_urn] = $slice_urn;
  }

  $results = array();
  $moptions = array();
  if (!is_null($role)) {
    $moptions['match'] = array('SLICE_ROLE'=>$role);
  }
  foreach ($tuples as $stup) {
    $surn = $stup['SLICE_URN'];
    $sid = $stup['SLICE_UID'];
    $sexp = $stup['SLICE_EXPIRED'];

    // Exclude expired slices
    if ($sexp)
      continue;

    // Exclude slices of which I'm not a member
    if (!array_key_exists($surn, $my_slice_urns))
      continue;

    $options = array_merge($moptions, $client->options());
    $mems = $client->lookup_slice_members($surn, $client->creds(), $options);
    foreach ($mems as $mtup) {
      $slice_member = array(SA_SLICE_TABLE_FIELDNAME::SLICE_ID => $sid,
			    SA_SLICE_MEMBER_TABLE_FIELDNAME::MEMBER_ID => $mtup['SLICE_MEMBER_UID'],
			    SA_SLICE_MEMBER_TABLE_FIELDNAME::ROLE => $mtup['SLICE_ROLE']);
      $slice_member = convert_role($slice_member);
      $results[] = $slice_member;
    }
  }
  return $results;
}

// Return list of slice ID's and Roles for given member_id for slices to which member belongs
// If is_member is true, return slices for which member is a member
// If is_member is false, return slices for which member is NOT a member
// If role is provided, filter on slices
//    for which member has given role (is_member = true)
//    for which member does NOT have given role (is_member = false)
// FIXME: optional project_id to constrain to a given project?
// CHAPI: okay (except for is_member=false)
function get_slices_for_member($sa_url, $signer, $member_id, $is_member, $role=null)
{
  $member_urn = get_member_urn(sa_to_ma_url($sa_url), $signer, $member_id);
  $client = XMLRPCClient::get_client($sa_url, $signer);

  if ($is_member) {
    $options = array();
    if (!is_null($role)) {
      $options = array('match'=>array('SLICE_ROLE'=>$role));
    }
    $options = array_merge($options, $client->options());
    $results = $client->lookup_slices_for_member($member_urn, $client->creds(), $options);
  } else {
    // CHAPI: TODO: implement is_member = FALSE
    error_log("get_slices_for_member using is_member=false is unimplemented.");
    return array();
  }

  // Convert columns from 'external' to 'internal' format
  $converted_results = array();
  foreach($results as $row) {
    $converted_row = array(SA_SLICE_MEMBER_TABLE_FIELDNAME::SLICE_ID => $row['SLICE_UID'],
                           SA_SLICE_MEMBER_TABLE_FIELDNAME::ROLE => $row['SLICE_ROLE'],
                           SA_SLICE_TABLE_FIELDNAME::EXPIRED => $row['SLICE_EXPIRED']);
    $converted_row = convert_role($converted_row);
    $converted_results[] = $converted_row;
  }
  $results = $converted_results;
  //  error_log("GSFM.RESULTS = " . print_r($results, true));

  return $results;
}

function lookup_slice_details($sa_url, $signer, $slice_uuids)
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('SLICE_UID'=>$slice_uuids));
  $options = array_merge($options, $client->options());
  $result = $client->lookup_slices($client->creds(), $options);
  $converted_slices = array();
  foreach ($result as $slice_uuid => $slice) {
    $converted_slices[$slice_uuid] = slice_details_chapi2portal($slice);
  }
  $result = $converted_slices;
  //  error_log("LSD.result = " . print_r($result, true));
  return $result;
}

// CHAPI: removed because it implies access that is no longer granted to all
//function get_slices_for_projects($sa_url, $signer, $project_uuids, $allow_expired=false)

// Return a dictionary of the list of slices (details) for a give
// set of project uuids, indexed by project UUID
// e.g.. [p1 => [s1_details, s2_details....], p2 => [s3_details, s4_details...]
// Optinonally, allow expired slices (default=false)
function get_slices_in_projects($sa_url, $signer, $slice_uuids, $project_uuids, $allow_expired=false)
{
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $projects = array();
  foreach($project_uuids as $project_uuid) {
    $projects[$project_uuid] = array();
  }
  //  error_log("GSFP.PROJECT_UUIDS = " . print_r($project_uuids, true));
  $options = array('match' => array('SLICE_UID' => $slice_uuids));
  //error_log("GSFP match = " . print_r($options, true));
  $options = array_merge($options, $client->options());
  $slices = $client->lookup_slices($client->creds(), $options);
  $converted_slices = array();
  foreach( $slices as $slice) {
    $converted_slices[] = slice_details_chapi2portal($slice);
  }
  $slices = $converted_slices;
  // error_log("GSFP.SLICES = " . print_r($slices, true));
  foreach($slices as $slice_urn => $slice) {
    $project_uid = $slice[SA_SLICE_TABLE_FIELDNAME::PROJECT_ID];
    if (in_array($project_uid, $project_uuids)) {
      $projects[$project_uid][] = $slice;
    }
  }

  // Convert from external to internal field names
  //  error_log("GSFP.PROJECTS = " . print_r($projects, true));
// return map of (project_uid_1 => (slice_data_1, ...),
//                project_uid_2 => (slice_data_2, ..), ..)
  return $projects;
}


// find the slice URN, given a slice UID
function get_slice_urn($sa_url, $signer, $slice_uid) {
  $cache = get_session_cached('slice_urn');
  if (array_key_exists($slice_uid, $cache)) {
    return $cache[$slice_uid];
  }

  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('SLICE_UID'=>$slice_uid),
		   'filter' => array('SLICE_URN'));
  $options = array_merge($options, $client->options());
  $result = $client->lookup_slices($client->creds(), $options);
  $urns = array_keys($result);
  $urn = $urns[0];
  $cache[$slice_uid] = $urn; // remember it
  set_session_cached('slice_urn', $cache);
  return $urn;
}

// CHAPI: convert a SA URL into a MA url
function sa_to_ma_url($sa_url) {
  return preg_replace("/SA$/", "MA", $sa_url);
}

function lookup_sliver_info_by_slice($sa_url, $signer, $slice_urn) {
  $client = XMLRPCClient::get_client($sa_url, $signer);
  $options = array('match' => array('SLIVER_INFO_SLICE_URN'=>$slice_urn));
  $options = array_merge($options, $client->options());
  $result = $client->lookup_sliver_info($client->creds(), $options);
  // results are structs by sliver_urn
  //         - SLIVER_INFO_URN
  //         - SLIVER_INFO_SLICE_URN
  //         - SLIVER_INFO_AGGREGATE_URN
  //         - SLIVER_INFO_CREATOR_URN
  //         - SLIVER_INFO_EXPIRATION
  //         - SLIVER_INFO_CREATION
  return $result;

}

/**
 * Determine the aggregates in a given slice from the sliver info data.
 *
 * Return an array of the aggregates.
 */
function aggregates_in_slice($sa_url, $user, $slice_urn) {
  $slivers = lookup_sliver_info_by_slice($sa_url, $user, $slice_urn);
  $all_aggs = get_services_of_type(SR_SERVICE_TYPE::AGGREGATE_MANAGER);

  /* Construct a map from aggregate URN to aggregate for faster
     lookup inside the sliver loop. */
  $urn2agg = Array();
  foreach ($all_aggs as $agg) {
    $agg_urn = $agg[SR_TABLE_FIELDNAME::SERVICE_URN];
    $urn2agg[$agg_urn] = $agg;
  }

  $aggs_with_resources = Array();
  // map the sliver aggregate URNs to aggregate IDs
  foreach ($slivers as $sliver) {
    $urn = $sliver[SA_SLIVER_INFO_TABLE_FIELDNAME::SLIVER_INFO_AGGREGATE_URN];
    if (! array_key_exists($urn, $aggs_with_resources)
        && array_key_exists($urn, $urn2agg)) {
        $aggs_with_resources[$urn] = $urn2agg[$urn];
    }
  }
  return array_values($aggs_with_resources);
}

?>

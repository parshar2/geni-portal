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
show_header('GENI Portal: Slices', $TAB_SLICES);
$user = geni_loadUser();
$slice = "<None>";
if (array_key_exists("id", $_GET)) {
  $slice = $_GET['id'];
}
$edit_url = 'edit-slice.php?id='.$slice;
$add_url = 'slice-add-resources.php?id='.$slice;
$res_url = 'sliceresource.php?id='.$slice;
print "<h1>GENI Slice: " . $slice . "</h1>\n";
print '<ul><li>';
print '<a href='.$edit_url.'>Edit</a>';
print '</li><li>';
print '<a href='.$add_url.'>Add Resources</a>';
print '</li><li>';
print '<a href='.$res_url.'>Resources</a>';
print '</li></ul>';

include("footer.php");
?>

<?php
/* english.inc.php */
$lang = array(
	'lang_strings' => '<!--', //hide array from debug screens
	'no_form' => 'You must define the form template in a chunk or a file',

	'dateFormat' => '%d %b %Y', //'%Y-%m-%d',
	'displayDate' => 'Date field',
	'fromDate' => 'Start date',
	'toDate' => 'End date',
	'startTime' =>'Start time',
	'endTime' =>'End time',

	'err_form' => '&amp;formtpl missing: You must define the form template in a chunk or a file',
	'err_previews' => '&amp;drafts missing: You must specify the contaner folder for preview documents',
	'err_postid' => '&amp;postid missing: You must define the page to go to after submitting the data',
	'err_tags' => 'Tag fields incomplete',
	'err_title' => 'You must enter a title',
	'err_blank' => 'May not be blank',
	'err_permission' => 'You do not have permission to post items here ',
	'err_postHeadline' => 'You must enter a headline',
	'err_eventHeadline' => 'You must enter a heading for the event',
	'err_postContent' => 'Content field is empty',
	'err_eventDetails' => 'You must describe the event details',
	'err_dateFormat' => 'Date format is incorrect; use ' . strftime('%d %b %y'),
	'err_timeFormat' => 'Time format is incorrect; use 24-hour clock',
	'err_playerName' => 'Please enter a name for the player',
	'err_sectionSelect' => 'Please select a section for the player',

	'err_noCode' => 'HTML code is not permitted in this form',
	'err_emailFormat' => 'Email does not appear to be valid',
	'err_phoneFormat' => 'Phone number may only contain numbers and spaces',
	'err_msgReply' => 'Please give a phone number or email so we can reply to you',

	'err_secureForm' => 'Error in form submission',
	'no_upload' => 'Uploaded file discarded',

	'status_unpub' => '<b style="color:#800;">Unpublished</b>',
	'status_published' => '<b>Published</b>',
	'status_preview' => '<b style="color:#800;">Draft</b>',
	'status_new' => 'New Item',

	'del_player' => 'player named ',
	'del_ctee' => 'member named ',
	'del_event'  => 'event',
	'lang_end' => '-->'
);

?>
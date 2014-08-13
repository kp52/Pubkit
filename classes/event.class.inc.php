<?php
class Event extends Resource
{
	public $defaultDate = NULL;
	public $singleDay = TRUE;

	public $tvs = array();

	public $validate = array(
		'pagetitle'=>'string||Req||title',
		'longtitle'=>'string||Req||eventHeadline',
		'tvpkRichContent'=>'string||Opt||eventDetails',
		'displayDate'=>'date||Req||displayDate',
		'eventEndDate'=>'date||Opt||toDate',
		'displayFrom'=>'date||Opt||fromDate',
		'timeStart'=>'time||Opt||startTime',
		'timeEnd'=>'time||Opt||endTime'
		);

	public $delForm = 'item-delete-tpl';
	public $delMsg;

function __construct($pid, $fields, $lang) {
// fill in values when names of TVs and fields differ
	$this->tvs['pkDateTo']       = $fields['displayTo'];
/*	$this->tvs['pkTimeStart']    = $fields['timeStart'];
	$this->tvs['pkTimeEnd']      = $fields['timeEnd'];
	$this->tvs['wslVenue']		 = $fields['venue'];
*/	$this->tvs['pkPreviewFlag']  = (isset($fields['preview'])) ? 1:0;

	parent::__construct($pid, $fields, $lang);

// create delete confirmation prompt including date
	$doc = new Document($pid, 'id, longtitle');
	$itemDate = $doc->Get('pkDate');
	$this->delMsg  = (isset($lang['del_event'])) ? $lang['del_event'] : 'Delete event';
	$this->delMsg .= '? <br />';
	$this->delMsg .= $itemDate .': ';
	$this->delMsg .= $doc->Get('longtitle');
}

function CustomFields($fields, $doc) {
// adjust for differences between field names and retrieved TV names for edit command
// set required format for dates and options sets (no widgets applied by docmanager)
	global $modx;

	$customFields = array();
    $customFields['displayDate'] = strftime($this->lang['dateFormat'],strtotime($fields['pkDate']));
    $customFields['displayTo'] = ($fields['pkDateTo'] > 0) ? strftime($this->lang['dateFormat'], strtotime($fields['pkDateTo'])) : "";
    $customFields['displayFrom']  = ($doc->Get('pub_date') > 0) ? strftime($this->lang['dateFormat'], $doc->Get('pub_date')) : "";
/*    $customFields['timeStart']    = (!empty($fields['pkTimeStart'])) ? $fields['pkTimeStart'] : "";
    $customFields['timeEnd']      = (!empty($fields['pkTimeEnd'])) ? $fields['pkTimeEnd'] : "";
    $customFields['venue']        = (!empty($fields['pkVenue'])) ? $fields['pkVenue'] : "";
*/
	return $customFields;
}

}
?>
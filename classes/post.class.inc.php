<?php
class Post extends Resource
{
	public $defaultDate; // set using function in constructor

	public $tvs = array(
		'pkDate'=>NULL,
		'pkDateTo'=>NULL
		);

	public $validate = array(
		'pagetitle'=>'string||Req||title',
		'longtitle'=>'string||Req||postHeadline',
		'tvpkRichContent'=>'string||Req||postContent',
		'displayDate'=>'date||Req||displayDate',
		'displayFrom'=>'date||Opt||fromDate',
		'displayTo'=>'date||Opt||toDate'
		);

	public $docFields = array();

function __construct($pid, $fields, $lang) {
	$this->defaultDate = strftime($lang['dateFormat']);
	$this->tvs['pkPreviewFlag'] = (isset($fields['preview'])) ? 1:0;
	parent::__construct($pid, $fields, $lang);
}

function CustomFields($fields, $doc) {
	$customFields = array();

    $customFields['displayDate'] = strftime($this->lang['dateFormat'], strtotime($fields['pkDate']));
    $customFields['displayTo']   = ($fields['pkDateTo'] > 0) ?
		strftime($this->lang['dateFormat'], strtotime($fields['pkDateTo'])) : "";
    $customFields['displayFrom'] = ($doc->Get('pub_date') > 0) ?
		strftime($this->lang['dateFormat'], $doc->Get('pub_date')) : "";

	return $customFields;
}

}
?>
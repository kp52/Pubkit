<?php
class Link extends Resource
{
	public $defaultDate; // set using function in constructor

	public $tvs = array(
		'linkImage'=>'assets/images/go-link.png'
		);

	public $validate = array(
		'pagetitle'=>'string||Req||postHeadline',
		'linkImage'=>'file||Req||file'
		);

	public $fileTV = 'linkImage';
	public $uploadFile = 'linkImage';
	public $filesHome = 'assets/files/';

function __construct($pid, $fields, $lang) { 
	$this->tvs['pkPreviewFlag'] = (isset($fields['preview'])) ? 1:0;
	if (!empty($pid)) {
		global $modx;
		$attachment = $modx->getTemplateVar($this->fileTV, 'id', $pid);
		$this->tvs[$this->fileTV] = $attachment['value'];
	}
	parent::__construct($pid, $fields, $lang);
}

function CustomFields($fields, $doc) {
	$customFields = array();

	return $customFields;
}

}
?>
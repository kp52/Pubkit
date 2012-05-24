<?php
class Player extends Record
{
	public $name = 'Player';
	public $table = 'orchestra';
	public $columns = array('id','player','mum','section','instrument','rank','updated','published');
	public $sortOrder = 'section,rank';

	public $validate = array(
		'player'=>'string||Req||playerName',
		'section'=>'string||Req||sectionSelect'
		);

	public $delMsg;

function __construct($pid=0, $fields=array(), $lang) {
	parent::__construct($pid, $fields, $lang);
	$this->delMsg = $lang['del_player'] . $this->player .'?';
}


function Save($pid=NULL, $fields=array()) {
	if (!isset($this->rank) && empty($fields['rank'])) {
		$fields['rank'] = 1;
	}
	parent::Save($pid, $fields);
}

function CustomFields($fields) {
// adjust for differences between field names and retrieved TV names for edit command
// set required format for dates and options sets (no widgets applied)
	global $modx;

	$customFields = array();
	foreach($this->columns as $column) {
		$customFields[$column] = $this->$column;
	}
	if (isset($this->tvs)) {
		foreach ($this->tvs as $tv) {
			if ($tv['field'] != $tv['column']) {
				$customFields[$tv['field']] = $this->$tv['column'];
				unset ($customFields[$tv['column']]);
			}
		}
	}
	return $customFields;
}

}
?>
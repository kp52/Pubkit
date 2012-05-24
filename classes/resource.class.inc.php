<?php
/*  Resource class
	Common functions for document-based items such as news & events posts
	Save, Delete, get/set fields etc. are defined in Document class
 */

class Resource {
	public $lang;
	public $docFields = array();
	public $tvs = array();
	public $tvListSplitter = ',';
	public $tvItemSplitter = '::';

function __construct($pid, $fields, $lang) {
	$this->lang = $lang;

/* Update item object from parameters: extra TVs and doc fields,
   change defaults for (scalar) item properties */
	$props = get_class_vars(get_class($this));

	foreach ($props as $propName => $prop) {

		if (isset($fields['params'][$propName])) {
			$newProp = $fields['params'][$propName];

			if ($propName === 'tvs' || $propName === 'docFields') {
				$workArray = $this->$propName;
				$newProps = explode($this->tvListSplitter, $newProp);

				foreach($newProps as $item) {
					$item = trim($item);
					if ($propName === 'tvs') {
						if (isset($fields[$item])) {
							$workArray[$item] = $fields[$item];
						} else {
							$elements = explode($this->tvItemSplitter, $item);
							$workArray[$elements[0]] = $elements[1];
						}
					} else {
						if (!in_array($item, $workArray)) {
							$workArray[] = $item;
						}
					}
				}

				$this->$propName = $workArray;

			} else {
				if (isset($fields[$propName])) {
					$this->$propName = $fields[$propName];
				} else {
					$this->$propName = $newProp;
				}
			}
		}
	}
}

function CheckFields($fields) {
	$lang = $this->lang;
	$errors = array();
	foreach ($this->validate as $field=>$rules) {
		$features = explode('||',$rules);
		switch ($features[0]) {
			case 'string':
			if (is_array($fields[$field])) {
				$fields[$field] = implode($fields[$field]);
			}

			if ($features[1] == 'Req' && strlen($fields[$field]) < 1) {
				$errors[] = $lang['err_' . $features[2]];
			}
			break;

			case 'date':
			if ($features[1] == 'Req' && strlen($fields[$field]) < 1) {
				$errors[] = $lang[$features[2]] . ': ' . $lang['err_blank'];
			}
			elseif (!empty($fields[$field])
				AND strtotime($fields[$field]) === FALSE) {
				$errors[] = $lang[$features[2]] . ': ' . $lang['err_dateFormat'];
			}
			break;

			case 'time':
			if ($features[1] == 'Req' && strlen($fields[$field]) < 1) {
				$errors[] = $lang[$features[2]] . ': ' . $lang['err_blank'];
			}
			elseif (timeValid($fields[$field]) === FALSE) {
				$errors[] = $lang[$features[2]] . ': ' . $lang['err_timeFormat'];
			}
			break;

			case 'file':

			$uploadResult = $this->uploadAttempt;

			if (is_numeric($uploadResult)) {;
				if ($uploadResult == 4 AND (empty($this->tvs[$this->fileTV]))) {
					$errors[] = 'BAD UPLOAD, error ' . $uploadResult ;
				}
			} else {
// discard file if other errors exist (so far - so make this the last check)
				if (isset($errors[0])) {
					$errors[] = $lang['no_upload'];
					if (file_exists($uploadResult)) {
						unlink($uploadResult);
					}
				}
			}

			case 'token':
			if (!isset($_SESSION[$field]) || empty($fields[$field]) || $fields[$field] !== $_SESSION[$field]) {
				$errors[] = $lang[$features[2]];
				unset($_SESSION[$field]);
			}
			break;
		}
	}
	return $errors;
}

}
?>
<?php
class Record
// general class for custom DB records

{
	public $recordType = 'record';
	public $lang;
	public $delForm = 'pk-item-delete-tpl';
	public $tvs = array();

    public $showImg = 'assets/images/icons/flag_show.png';
    public $hideImg = 'assets/images/icons/flag_hide.png';

function __construct($pid=0, $fields=array(), $lang='english') {
// set up table and field names
	global $modx, $table_prefix;
	$this->table = $table_prefix . $this->table;
	if (isset($fields['params']['tvs'])) {
		$tvDefs = explode(';', $fields['params']['tvs']);
		foreach ($tvDefs as $tvDef) {
			$tvElements = explode(',', $tvDef);
			$tvName = trim($tvElements[0]);
			$this->tvs[$tvName]['column'] =  $tvElements[1];
			$this->tvs[$tvName]['field'] =  isset($tvElements[2]) ? $tvElements[2] : $tvName;
			$this->tvs[$tvName]['format'] =  $tvElements[3];
		}
	}

	if (!empty ($pid)) {
		$this->Populate($pid);
	}
	$this->lang = $lang;

	if (isset($fields['params']['deleteForm'])) {
		$this->delForm = $fields['params']['deleteForm'];
	}

	if (isset($fields['params']['deleteMessage'])) {
		$this->delMsg = $fields['params']['deleteMessage'];
	}
}

function Populate($pid) {
// retrieve field values from DB
	global $modx;
    if (!is_numeric($pid) && $pid[0] != chr(39)) {
        $pid = chr(39) . $pid . chr(39);
    }
	$record = $modx->db->select('*',
		$this->table,
		"id = $pid ",
		$this->sortOrder);
	$item = $modx->db->getRow($record);
	if (!empty($item)) {
		foreach ($item as $key=>$column) {
			$this->$key = $column;
		}
	}
	return;
}

function Save($pid=NULL, $fields=array()) {
	global $modx;
	$this->id = $pid;
	$newRecord = empty($pid);
	$fields['updated'] = strftime('%Y-%m-%d %H:%M:%S');

// translate field names to column names for TVs
	if (isset($this->tvs)) {
		foreach ($this->tvs as $tv) {
			$tvField = $tv['field'];
			$tvColumn = $tv['column'];
			if ($tvField !== $tvColumn && isset($fields[$tvField])) {
				$fields[$tvColumn] = $fields[$tvField];
			}
		}
	}

	foreach ($fields as $key=>$field) {
		if (in_array($key, $this->columns)) {
			if (is_array($field)) {
				$field = implode("||",$field);
			}
			$dbFields[$key] = $modx->db->escape($field);
		}
	}

// non-FURL addresses will have set id via $_REQUEST & $fields[]
    unset ($dbFields['id']);

	if ($newRecord) {
		if (property_exists($this->name, 'nsId')) {
			$dbFields['id'] = $this->nsId;
			$modx->db->insert($dbFields, $this->table);
			$pid = $this->nsId;
		} else {
			$modx->db->insert($dbFields, $this->table);
			$pid = $modx->db->getInsertId();
		}
	} else {
		$modx->db->update($dbFields, $this->table, "id = '$pid'");
	}

	$this->populate($pid);

	return $pid;
}

function Delete($pid) {
	global $modx;
	$modx->db->delete($this->table,"id = '$pid'");
	if (property_exists($this, 'rank')) {
		resetFormRank($this->table, 1, $this->sortOrder);
	}
	return;
}

function CheckFields($fields) {
	$errors = array();

	foreach ($this->validate as $field=>$rules) {

		if (!is_array($rules)) {
			$rules = array($rules);
		}

		foreach ($rules as $rule) {
			$newErrors = $this->CheckOneField($fields, $field, $rule);
			$errors = array_merge($errors, $newErrors);
		}
	}

	return $errors;
}

function CheckOneField($fields, $field, $rule) {
	global $modx;
	$lang = $this->lang;

	$errors = array();

	$curField = $fields[$field];
	$features = explode('||',$rule);

	switch ($features[0]) {
		case 'string':
		if (($features[1] == 'Req') && (strlen(trim($curField)) < 1)) {
			$errors[] = $lang['err_' . $features[2]];
		}
		break;

		case 'date':
		if ($features[1] == 'Req' && strlen($curField) < 1) {
			$errors[] = $lang[$features[2]] . ': ' . $lang['err_blank'];
		}
		elseif (!empty($curField)
			AND strtotime($curField) === FALSE) {
			$errors[] = $lang[$features[2]] . ': ' . $lang['err_dateFormat'];
		}
		break;

		case 'time':
		if ($features[1] == 'Req' && strlen($curField) < 1) {
			$errors[] = $lang[$features[2]] . ': ' . $lang['err_blank'];
		}
		elseif (timeValid($curField) === FALSE) {
			$errors[] = $lang[$features[2]] . ': ' . $lang['err_timeFormat'];
		}
		break;

		case 'email':
		if (!empty($curField)
			&& preg_match('/^.+@.+\..{2,3}$/',$curField) < 1) {
			$errors[] = $lang['err_emailFormat'];
		}
		break;

		case 'phone':
		if (!empty($curField)
			&& preg_match('/[0-9\- ]+/', $curField) < 1) {
			$errors[] = $lang['err_phoneFormat'];
		}
		break;

		case 'alt':
// test for valid email OR phone number
		$replyFields = 0;
		$badMail = explode(',',strtolower($modx->getChunk('badmail')));
		$badPhone = explode(',',$modx->getChunk('badphone'));

		if (!empty($fields['email'])) {
			$replyFields++;
			if (preg_match('/^.+@.+\..{2,3}$/',$fields['email']) < 1) {
				$errors[] = $lang['err_emailFormat'];
			}
			if (in_array($fields['email'], $badMail)) {
				$errors[] = $lang['err_emailFormat'];
				$this->Alert($fields, FALSE, 'email');
			}
		}

		if (strlen($fields['phone']) > 1) {
			$replyFields++;
			if (preg_match('/[^0-9\(\)\-\+ ]+/', $fields['phone']) > 0) {
				$errors[] = $lang['err_phoneFormat'];
			}

			if (in_array($fields['phone'], $badPhone)) {
				$errors[] = $lang['err_msgBadwords']; // lie about reason for rejection
				$this->Alert($fields, FALSE, 'phone');
			}
		}

		if ($replyFields < 1) {
			$errors[] = $lang['err_msgReply'];
		}
		break;

		case 'badwords':
		$bad = strtolower($modx->getChunk('badwords'));

		if (!empty($bad)) {
			$msgWords = str_word_count(strtolower($curField), 1, '[');

			$badWords = explode(",", $bad);

			if ($features[1] == 'Req') {
				foreach($msgWords as $msgWord) {
					if (in_array($msgWord, $badWords)) {
						$err = (isset($features[2])) ? 'err_' . $features[2] : 'err_badwords';
						$errors[] = $lang[$err];
						$this->Alert($fields, FALSE, 'content');
						break;
					}
				}
			}
            // look for phrases (in badwords list as words joined by %)
            // add junk character at start to avoid false/zero confusion in failure test
            $msgComp = '_' . implode('_', $msgWords);

            function phrases($entry) {
                return strpos($entry, '_');
            }
            $badPhrases = array_filter($badWords, 'phrases');
            $msgComp = str_replace($badPhrases, '!!!FAIL!!!', $msgComp);

            if (!strpos($msgComp, '!!!FAIL!!!') === false) {
                $err = (isset($features[2])) ? 'err_' . $features[2] : 'err_badwords';
                $errors[] = $lang[$err];
                $this->Alert($fields, FALSE, 'content');
                break;
            }
		}
		break;

		case 'badnames':
		$bad = strtolower($modx->getChunk('badnames'));

		if (!empty($bad)) {
			$badNames = explode(",", $bad);
            $badNames = array_map('trim', $badNames);
			$msgName = trim(strtolower($curField));

            $err = (isset($features[2])) ? 'err_' . $features[2] : 'err_msgBadwords';

                $badName = trim($badName);
                if (in_array($msgName, $badNames)) {
    				$errors[] = $lang[$err];
    				$this->Alert($fields, FALSE, 'name');
                }
            }
		break;

		case 'token':
		if (!isset($_SESSION[$field]) || empty($curField) || $curField !== $_SESSION[$field]) {
			$errors[] = $lang['err_secureForm'];
			unset($_SESSION[$field]);
			$this->Alert($fields, FALSE, 'token');
		}
		break;
	}
	return $errors;
}

function PubFlag($published) {
// set published/unpublished flags as icons
    $pubStr = '<img src="%s" title="%s" alt="%2$s" />';

    if ($published == 0) {
        $pubUnpub = '1';
        $pubCmd = vsprintf($pubStr, array($this->showImg, 'Publish'));
    } else {
        $pubUnpub = '0';
        $pubCmd = vsprintf($pubStr, array($this->hideImg, 'Unpublish'));
    }

    return array('pubUnpub'=>$pubUnpub, 'pubCmd'=>$pubCmd);
}

function SetUpdated($table, $updateCol='updated', $where='', $showUnpublished=0) {
    global $modx;

    if (!empty($where)) {
        $where = "WHERE " . $where;
    }
    $result = $modx->db->getValue("SELECT MAX($updateCol) FROM $table $where");
    $modx->setPlaceholder('updated', strtotime($result));
    return;
}

}
?>
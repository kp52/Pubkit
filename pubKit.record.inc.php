<?php
#::::::::::::::::::::::::::::::::::::::::
#  Snippet Name: PubKit
#  version: 1.6
#  pubKit.record.inc.php: included file for custom table handling;
#  Big overlap with pubkit.inc.php,
#  but enough difference to merit a separate file
#
#  Snippet code sets parameters and function/class file includes;
#  it also creates instance of the object representing the DB record
#::::::::::::::::::::::::::::::::::::::::

/* Already happened back in the main snippet code:
	$item = new $class($docId, $fields, $lang);
 */

// test if input has come back from form (check for hidden field)
$isPostBack = (isset($fields[$formName])) ? true:false;

// 2-stage deletion - first prompt, delete if confirmed
$isDeletion = isset($fields['confirmDeletion']) ? true:false;

// retrieve or set up object for TVs
// tag TVs use class definition in optionsbuilder.class.php
// Note: though not a resource, record can still use MODx TVs
//
if (!empty($item->tvs)) {
	foreach ($item->tvs as $tagTv => $tagProps) {
		$fieldName = $tagProps['field'];
		$options[$tagTv] = new OptionButtons($tagTv, $fieldName);

		if (empty($fields[$fieldName])) {
		    $tagValues[$tagTv] = array();
		    $tagValues[$tagTv] = $options[$tagTv]->definition['value'];
		} else {
		    if (is_array($fields[$fieldName])) {
		      $tagValues[$tagTv] = $fields[$fieldName];
		    } else {
		      $tagValues[$tagTv] = explode($delimiter,$fields[$fieldName]);
 		    }
		}
	}
}

if (!isset($fields['displayDate'])) {
    $fields['displayDate'] = $item->defaultDate;
}

$message = NULL;

// get form template. No default - must have a chunk or file
// $formtpl will be final output of the snippet
if (isset($formtpl)) {
	if (substr($formtpl,0,5) != '@FILE') {
		$formtpl = $modx->getChunk($formtpl);
		} else {
		$formFile = $chunk_place . 'chunk.' . trim(substr($formtpl,6)) . '.html';
		$formtpl = file_get_contents($formFile);
	}
} else {
	$formtpl = $lang['err_form'];
}

// Form submission: check mandatory fields, build error message
if ($isPostBack) {

// handle file upload, flagged by class having uploadFile property
// uses Upload function in pubKit.functions.php
	if (isset($item->uploadFile)) {
		if ($_FILES[$item->uploadFile]['size'] === 0) {
			if (!empty($fields['currentFile'])) {
				$item->uploadAttempt = $fields['currentFile'];
			} else {
				$item->uploadAttempt = 4;
			}
		} else {
			$item->uploadAttempt = Upload($item->uploadFile, $item->filesHome, $item->allowedTypes);

			if (!is_numeric($item->uploadAttempt) AND isset($item->fileTV)) {
					$item->tvs[$item->fileTV] = $item->uploadAttempt;
			}
		}
	}

	$errors = $item->CheckFields($fields);
    $errors = array_unique($errors);

	if (!isset($postid)) {
		$errors[] = $lang['err_postid'];
	}

	if (!empty($errors)) {
		foreach ($errors as $error) {
			if (empty($error)) {
				$error = 'Error message not defined';
			}
			$message .= "$error <br /> \n";
		}
	}

// If error messages exist, skip create/update and redisplay form (populated)
	if (!empty($message)) {
        $message = '<div id="inputErrors">' . $message . '</div>';
		$modx->setPlaceholder('errors', $message);
// experimental (= don't remember doing this!)
		if (method_exists($item, 'FailLog')) {
			$item->FailLog($fields, $message);
		}
	} else {

// DB record processing, including ordering and sub-ordering if required
// expects table to have column called rank as main ordering parameter
		if (in_array('rank', $item->columns)) {
			If (empty($fields['rank'])) {
				$fields['rank'] = 999;
			}
			$rankAdjust = ($fields['rank'] < $item->rank) ? -1 : +1;
			$fields['rank'] = setFormRank($item->table, $fields['rank'], 2, $rankAdjust);
			$item->save($docId,$fields);

			if (property_exists($item, 'sortOrder')) {
				resetFormRank($item->table, 1, $item->sortOrder);
			} else {
				resetFormRank($item->table, 1, 'rank');
			}
		} else {
			$item->save($docId,$fields);
		}

		if (!empty($fields['returnId'])) {
			$postid = $fields['returnId'];
		}

        $redirect = TRUE;
	}
}

/******************** Form display operations *******************
* New item, re-edit, request/do deletion,
* or redisplay with error messages and entered data
****************************************************************/

if (!empty($docId) && empty($redirect) && empty($message)) {
	$item->Populate($docId);

// turn all record fields into $fields array elements
	$customFields = $item->customFields($fields, $docId);
	$fields = array_merge($fields, $customFields);

	if ($isDeletion) {
		if($fields['delete'] == 'Yes') {
			$item->Delete($docId);
		}

        $postid = $fields['returnId'];
	    $redirect = TRUE;
	}

	elseif ($cmd == 'delete') {
    	$formtpl = $modx->getChunk($item->delForm);
    	$modx->setPlaceholder('docId', $docId);
    	$modx->setPlaceholder('returnId', $fields['returnId']);
    	$modx->setPlaceholder('delMsg', $item->delMsg);
	}

	elseif ($cmd == 'move') {
		$rankAdjust = ($fields['to'] < $item->rank) ? -1 : +1;
		if ($fields['to'] != $item->rank) {

		$newRank = setFormRank($item->table, $fields['to'], 2, $rankAdjust);
		$updateFields = array('rank'=>$newRank);

		$item->save($docId, $updateFields);
		resetFormRank($item->table, 1, $item->sortOrder);
		}

        $postid = $fields['returnId'];
	    $redirect = TRUE;
	}

	elseif ($cmd == 'setfield') {
// restrict updated fields to request via second argument of Save()
		$item = new $class($docId, $fields, $item->lang);
		$update = array();
		$target = $fields['docField'];
		$update[$target] = $fields['myValue'];

		$item->Save($docId, $update);

        $postid = $fields['returnId'];
	    $redirect = TRUE;
	}
}

else {
  		$modx->setPlaceholder('itemStatus', $lang['status_new']);
}

// Create HTML code for option buttons/list (optionsbuilder.class.php)

if (!empty($item->tvs) && empty($redirect)) {
	foreach ($item->tvs as $tv => $elements) {
		$field = $elements['field'];
		$current = isset($fields[$field]) ? $fields[$field] : $options[$tv]->defaultValue;
		$modx->setPlaceholder($field, $options[$tv]->BuildSet($current, $elements['format']));
// remove from fields so placeholder doesn't get overwritten
	unset($fields[$field]);
	}
}

// redirect after item creation/update/publication
// NB no default to current document - we're not in a document!
if ($redirect) {

// clear site cache (see pubKit.functions.php) Is this needed for custom table??
    if($clearcache == 1){
    	emptyCache();
    }

// redirect to post id (with anchor #+$prefix+docId if reediting)
// postid may be array of IDs for radio button tags for different pages
// allow for alias in place of ID from preview and delete confirmation
//

    if (is_numeric($postid)) {
       	$landing = $modx->makeUrl($postid);
	} else {
		$landing = $modx->makeUrl(0, $postid);
	}

  	if (isset($fields['preview'])) {
		$landing .= ($modx->config['friendly_urls'] == 1) ? '?' : '&';
  		$landing .= 'template=preview&docid=' . $doc->Get('id');
	} else {
   		$landing .= (!empty($docId)) ? '#' . $prefix . $docId: '';
	}

    $modx->sendRedirect($landing);
}

/***************** Prepare form for display *****************/
// check user rights
if(!$allowAnyPost && !$modx->isMemberOfWebGroup($postgrp)) {
    $formtpl = $lang['err_permission'];
} else {
// populate form fields placeholders with any existing data
// convert quotes etc. so text fields are not mangled
// assuming XHTML, single quotes are left alone
	foreach($fields as $n=>$v) {
		if (is_string($v)) {
	        $v = htmlspecialchars($v);
		}
		$modx->setPlaceholder($n, $v);
	}
}

// PubKit can be used for contact forms, guestbook etc, so add some security
if ($secureForm !== FALSE) {
	$modx->runSnippet('setSecurityToken');
}

// return form to snippet call, with debug at top
$pubKit = $dbg . $formtpl;
?>

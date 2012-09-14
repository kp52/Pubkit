<?php
#::::::::::::::::::::::::::::::::::::::::
#  Snippet Name: PubKit
#  version: E1.0
#  pubKit.inc.php: included file;
#
#  See snippet code for parameters and function/class file includes;
#  it also creates instance of the object representing the resource
#::::::::::::::::::::::::::::::::::::::::

// define location of main MODx content table
$contentTable = $modx->getFullTableName('site_content');

// test if input has come back from form (check for hidden field)
$isPostBack = (isset($fields[$formName])) ? true:false;

// 2-stage deletion - first prompt, then delete if confirmed
if (isset($fields['confirmDeletion'])) {
	$cmd = 'erase';
}

// retrieve or set up array for tagging template variables
// optionsbuilder.class.php creates SELECT tag or checkbox/radio set
if (!empty($tagTvs)) {
	foreach ($tagTvs as $tagTv => $tagProps) {
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
// save in item object for create/update loop
// $item object is created at end of snippet code
		$item->tvs[$tagTv] = $tagValues[$tagTv];
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

// form submission: check mandatory fields, build error message
if ($isPostBack) {

// handle file upload, flagged by class having uploadFile property
	if (isset($item->uploadFile)) {
		$item->uploadAttempt = Upload($item->uploadFile, $item->filesHome, $item->allowedTypes);
		if (isset($item->fileTV) AND !is_numeric($item->uploadAttempt)) {
			$item->tvs[$item->fileTV] = $item->uploadAttempt;
		}
	}

// add token checking for secure forms
	if (!empty($secureForm)) {
		$item->validate['smersh'] = 'token||Req||err_secureForm';
	}

//	check mandatory tagging fields
	if (!empty($reqTags)) {
		$reqTags = explode(',', $reqTags);
		foreach ($reqTags as $reqTag) {
			$item->validate[trim($reqTag)] = 'string||Req||tags';
		}
	}

// validation uses class method
	$errors = $item->CheckFields($fields);

	if (!empty($errors)) {
		foreach ($errors as $error) {
			$message .= "$error <br /> \n";
		}
	}

// If error messages exist, skip create/update and redisplay form (populated)
	if (!empty($message)) {
        $message = '<div id="inputErrors">' . $message . '</div>';
		$modx->setPlaceholder('errors', $message);
	} else {

// Valid submission. Let's process it!
// sanitize content. $allowedTags set in snippet, extended by &extraAllowedTags
		$content     = $modx->stripTags($fields[$rtcontent], $allowedTags);
		$title       = $modx->stripTags($fields['pagetitle']);
		$longtitle   = $modx->stripTags($fields['longtitle']);
		$introtext   = $modx->stripTags($fields['introtext']);

// expand placeholder +intro+ in content
		$content = str_replace('+intro+', $introtext, $content);

// set menu index; as requested, or add to tail
		if (isset($fields['menuindex'])) {
			$mnuidx = $fields['menuindex'];
			if (empty($docId)) {
				$mnuidx = setRank($contentTable, $mnuidx, '', $folder, 2, -1);
			} else {
				$docObj =$modx->getDocumentObject('id', $docId);
				$currentMnu = $docObj['menuindex'];
				$adj = ($mnuidx < $currentMnu) ? -1 : 1;
				$mnuidx = setRank($contentTable, $mnuidx, '', $folder, 2, $adj);
			}
		} else {
			if (empty($docId)) {
				$mnuidx = $modx->db->getValue("
			       SELECT MAX(menuindex) + 1 as 'mnuidx'
			       FROM $contentTable
			       WHERE parent = '$folder' ");
			} else {
				$docObj =$modx->getDocumentObject('id', $docId);
				$mnuidx = $docObj['menuindex'];
			}
		}

	   	if($mnuidx<1) {
	   		$mnuidx = 0;
	   	}

// retrieve showInMenu setting and invert for hidemenu field
	    $showinmenu = (isset($fields['show'])) ? 1 : $showinmenu;
	    $hidemenu = 1 - $showinmenu;

// validate and format dates, set published or unpublished
// NB unpub_date = displayTo + 1 day, or startDate + 1 day if no displayTo for an event
		$startDate = strtotime($fields['displayDate']);
		$pubDate = (!empty($fields['displayFrom'])) ? strtotime($fields['displayFrom']) : 0;
	    $endDate = (!empty($fields['displayTo'])) ? strtotime($fields['displayTo']) : 0;

		$publishable = pubUnpub($startDate, $pubDate, $endDate, $item->singleDay);
		$published = $publishable['published'];
		$unpubDate = $publishable['unpub'];

// use MySQL format  to store date (unambiguous and human readable)
// NB fixed names for date TVs
		$item->tvs['pkDate'] = strftime('%Y-%m-%d',$startDate);
		$item->tvs['pkDateTo'] = ($endDate > 0) ? strftime('%Y-%m-%d',$endDate) : '';

// update alias if field or parameter requests
		$updateAlias = isset($fields['updateAlias']);

// make previews unpublished, always update alias on return from preview
		if (isset($fields['preview'])) {
			$published = 0;
			$updateAlias = TRUE;
		}

// set user for author fields; web user ID is negated
		if ($_SESSION['webValidated'] == 1) {
			$userid = $_SESSION['webInternalKey'] * -1;
		}
		else if ($_SESSION['usertype'] == 'manager') {
			$userid = $_SESSION['mgrInternalKey'];
		}

// *********** CREATE || UPDATE DOCUMENT **************
// Use DocManager class to create/update document, including TV values
// TO DO: convert introtext & content to entities if TEXTAREA, not if RT input
		$doc = new Document($docId);
// reference type for weblinks
		if ($fields['type'] === 'reference') {
			$doc->Set('type','reference');
		}
		$doc->Set('pagetitle', $modx->db->escape(htmlspecialchars($title)));
		$doc->Set('longtitle', $modx->db->escape(htmlspecialchars($longtitle)));
		$doc->Set('introtext', $modx->db->escape(htmlspecialchars($introtext)));
		$doc->Set('content', $modx->db->escape($content));

// cloned item has empty alias field
		$alias = $doc->Get('alias');
		if ($updateAlias || empty($alias)) {
			$doc->Set('alias', setAlias($docId, $title, $permalinks, $permaLength));
		}

		$doc->Set('menuindex', $mnuidx);
		$doc->Set('hidemenu', $hidemenu);
		$doc->Set('editedon', time());
		$doc->Set('editedby', $userid);
		$doc->SetTemplate($template);
		$doc->Set('pub_date', $pubDate);
		$doc->Set('published', $published);
		$doc->Set('unpub_date', $unpubDate);

// save extra fields listed in class definition
		if (property_exists($item, 'docFields')) {
			foreach ($item->docFields as $docField) {
				$docFieldValue = $modx->db->escape(htmlspecialchars($fields[$docField]));
				$doc->Set($docField, $docFieldValue);
			}
		}
// rewrite TV values
		foreach ($item->tvs as $tv=>$value) {
		    $value = (is_array($value)) ? implode('||', $value) : $value;
			$doc->Set('tv' . $tv, $modx->db->escape(htmlspecialchars($value)));
		}
// Leave previous values if updating existing document
		if (empty($docId)) {
			$doc->Set('parent', $folder);
			$doc->Set('createdon', time());
			$doc->Set('createdby', $userid);
			$doc->Set('deleted', '0');
// resource's cacheability set in &cacheItem parameter, default = 1.
			$doc->Set('cacheable', $cacheable);

			$doc->Save();
			$docId = $doc->Get('id');
			$doc->Set('alias', setAlias($docId, $title, $permalinks, $permaLength));
		}

		$doc->Save();

// tidy menuindex after deletions or manual ordering (rank)
// (see pubKit.functions.php)
		resetRank($contentTable, $folder, 1);

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
	$doc = new Document($docId);

// complete deletion (after confirmation)
	switch ($cmd) {

	case 'erase':
		if($fields['delete'] == 'Yes') {
			$doc->Delete();
// close gaps in menuindex sequence
			resetRank($contentTable, $folder, 1);
		}

        $postid = $fields['returnId'];
	    $redirect = TRUE;
		break;

// request deletion. (returnId set in management or preview screen)
	case 'delete':

// Tailor 'Confirm delete' message for item type & language via class
		if (isset($item->delForm)) {
			$formtpl = $modx->getChunk($item->delForm);
			$modx->setPlaceholder('delMsg', $item->delMsg);
		} else {
			$formtpl = $confirmDeletionChunk;
	    	$modx->setPlaceholder('title', $doc->Get('pagetitle'));
	    	$modx->setPlaceholder('headline', $doc->Get('longtitle'));
		}
    	$modx->setPlaceholder('docId', $doc->Get('id'));
    	$modx->setPlaceholder('returnId', $fields['returnId']);
		break;

// update an item TV - e.g. set the archive flag
// TV name in request variable 'tv', value to be entered in 'value'
// NO CHECK MADE IF TV EXISTS! IF NOT = CRASH, BURN!!
	case 'updateTv':

		setTemplateVar($fields['myValue'], $docId, $fields['tvName']);

        $postid = $fields['returnId'];
	    $redirect = TRUE;
		break;

// set a document field directly - e.g. Unpublish
	case 'setField':

		$doc = new Document($docId, $fields['docField']);
		$doc->Set($fields['docField'], $fields['myValue']);
		$doc->Save();

        $postid = $fields['returnId'];
	    $redirect = TRUE;
		break;

// update item
   case 'edit':

		$modx->setPlaceholder('docId',     $doc->Get('id'));
		$modx->setPlaceholder('pagetitle', $doc->Get('pagetitle'));
		$modx->setPlaceholder('longtitle', $doc->Get('longtitle'));
		$modx->setPlaceholder('introtext', $doc->Get('introtext'));
		$modx->setPlaceholder('menuindex', $doc->Get('menuindex'));

// get content into the fields
		$fields[$rtcontent] = $doc->Get('content');

// retrieve additional document fields listed in item's class
		if (property_exists($item, 'docFields')) {
			foreach ($item->docFields as $docField) {
				$modx->setPlaceholder($docField, $doc->Get($docField));
			}
		}
        $hidemenu = $doc->Get('hidemenu');
        $showinmenu = 1 - $hidemenu;

// retrieve TV values. $fields array will be made into placeholders later
		foreach($item->tvs as $tvName=>$tv) {
			$tv = $doc->Get('tv' . $tvName);
			$fields[$tvName] = $tv;
		}
// TVs retrieved in raw format, no widget processing
		if (!empty($tagTvs)) {
			foreach ($tagTvs as $tagTv => $elements) {
				$tagValues[$tagTv] = explode('||',$fields[$tagTv]);
			}
		}

// class function translates dates and renames elements by copying
		$customFields = $item->customFields($fields, $doc);
		$fields = array_merge($fields, $customFields);

// set up status placeholders
        if ($doc->Get('published') == 0) {
          $modx->setPlaceholder('itemStatus', $lang['status_unpub']);
		} else {
          $modx->setPlaceholder('itemStatus', $lang['status_published']);
        }
        if ($doc->Get('tvpkPreviewFlag') == 1) {
          $modx->setPlaceholder('previewStatus', $lang['status_preview']);
        } else {
          $modx->setPlaceholder('previewStatus', NULL);
        }
// hidden form field for redirect after submission
		$modx->setPlaceholder('postid', $postid);
		break;

// Publish (from button in preview)
// create new doc object - don't want to rewrite every field
    case 'publish':
		$doc = new Document($docId, 'published,pub_date,introtext,content');
// may not be due for publication yet; NB TV Unixtime conversion happens in function
		$publishable = pubUnpub(
            $doc->Get('tvpkDate'),
            $doc->Get('pub_date'),
            $doc->Get('tvpkDateTo'),
            $item->singleDay
			);
        $introtext = $doc->Get('introtext');
        $content   = $doc->Get('content');
// copy in text from intro if requested using +intro+
        $content   = str_replace('+intro+', $introtext, $content);
        $doc->Set('introtext', $modx->db->escape($introtext));
        $doc->Set('content', $modx->db->escape($content));
		$doc->Set('published', $publishable['published']);
// see pubKit.functions.php
		setTemplateVar(0, $docId, 'pkPreviewFlag');

		$doc->Save();

        $postid = $fields['returnId'];
        $redirect = TRUE;
		break;

	case 'move':
// see comment on 'publish' re new document object

		$doc = new Document($docId,'menuindex');
		$mnuidx = $fields['menuindex'];
		$currentMnu = $doc->Get(menuindex);
		$adj = ($mnuidx < $currentMnu) ? -1 : 1;
		$mnuidx = setRank($contentTable, $mnuidx, '', $folder, 2, $adj);
		$doc->Set('menuindex',$mnuidx);
		$doc->Save();

		resetRank($contentTable, $folder, 1);

        $postid = $fields['returnId'];
	    $redirect = TRUE;
		break;

	case 'clone':

		$doc->Duplicate();
		$doc->Set('alias', '');
		$doc->Set('tvpkDate', $item->defaultDate);
		$doc->Set('tvpkDateTo', NULL);
		$doc->Set('createdon', time());
		$doc->Set('pub_date', 0);
		$doc->Set('unpub_date', 0);
		$doc->Set('published', 0);
		$doc->Save();
		$docId = $doc->Get('id');
        $modx->setPlaceholder('itemStatus', $lang['status_clone']);

// resource now exists - make sure it shows in management screens
		emptyCache();

		$landing = $modx->makeUrl($modx->documentIdentifier);
		$landing .= '?docId=' . $docId . '&command=edit';
		$modx->sendRedirect($landing);
		break;
	}
} else {

	if (empty($docId)) {
		$modx->setPlaceholder('itemStatus', $lang['status_new']);
	}
}

// Create HTML code for option buttons/list (optionsbuilder.class.php)
if (!empty($tagTvs) && empty($redirect)) {
	foreach ($tagTvs as $tagTv => $elements) {
		$field = $elements['field'];
		$tagSet = $options[$tagTv]->BuildSet($tagValues[$tagTv], $elements['format'], $labelPos);
		$modx->setPlaceholder($field, $tagSet);
// remove from fields so placeholder doesn't get overwritten
	unset($fields[$field]);
	}
}

// Initialize show in menu checkbox
if ($showinmenu == 1) {
    $modx->setPlaceholder('showInMenu', ' checked="checked"');
}

// Initialize Update Permalinks checkbox
if (!isset($fields['updateAlias']) && !$isPostBack) {
	$fields['updateAlias'] = ($updatePermalinks == 1);
	}
if (!empty($fields['updateAlias'])) {
	$modx->setPlaceholder('selUpdateAlias', ' checked="checked"');
}

// redirect after item creation/update/publication
if ($redirect) {

// clear site cache (see pubKit.functions.php)
    if ($clearcache==1){
    	emptyCache();
    }

// redirect to post id (with anchor #+$prefix+docId if reediting)
// postid may be array of IDs for radio button tags for different pages

    if (is_array($postid)) {
              $landing = $modx->makeUrl($postid[$fields[$postNavTag]]);
    } elseif (isset($postid)) {
    		  if (is_numeric($postid)) {
              	$landing = $modx->makeUrl($postid);
			  } else {
			  	$landing = $modx->makeUrl(0, $postid);
			  }

    } else {
    	$landing = $modx->makeUrl($doc->Get('id'));
    }

  	if (isset($fields['preview'])) {
		$landing .= ($modx->config['friendly_urls'] == 1) ? '?' : '&';
  		$landing .= 'template=preview&docId=' . $doc->Get('id');
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

// set value of rich text TV assigned to editing page (Nov 2011)
if (isset($rtcontent)) {
	if (substr($rtcontent, 0, 2) == 'tv') {
		$rtTv = substr($rtcontent, 2);
	} else {
		$rtTv = $rtcontent;
	}
	$rtValue = $fields[$rtcontent];
	$pageInfo = $modx->getPageInfo($modx->documentIdentifier, '', 'id, published');
	setTemplateVar($rtValue, $pageInfo['id'], $rtTv);
	$formatted  = implode('', $modx->getTemplateVarOutput($rtTv, '', $pageInfo['published']));
	$modx->setPlaceholder($rtTv, $formatted);
}

// Create security token to check authenticity of forms
// setSecurityToken creates token, sets as placeholder 'smersh'
if (!empty($secureForm)) {
	$modx->runSnippet('setSecurityToken');
}

// return form to snippet call, with debug at top
$pubKit = $dbg . $formtpl;
?>

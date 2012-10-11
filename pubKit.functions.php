<?php
// pubKit.Functions.php
// general functions for PubKit E1.2.0
// Oct 2012

function setAlias ($id, $title, $permalinks, $permaLength) {
	if ($permalinks == 1) {
		$alias = preg_replace('#[^a-zA-Z0-9\s\-]#',"",$title);
		$alias = trim(strtolower($alias));
		$alias = preg_replace('#\s+#','-',$alias);
		$alias = '-' . substr($alias,0,$permaLength);
	} else {
		$alias = "";
	}
		$alias = $id . $alias;

	return $alias;
}

function emptyCache() {
	global $modx;
	include_once $modx->config['base_path']."manager/processors/cache_sync.class.processor.php";
	$sync = new synccache();
	$sync->setCachepath("assets/cache/");
	$sync->setReport(false);
	$sync->emptyCache();
}

function timeValid(&$t) {
//Test for blank or HHMM, HH:MM, HH.MM, return HHMM
	$t=trim($t);
	if (empty($t)) {
		$output = TRUE;
		}
	elseif (substr($t,0,1) == 2 && substr($t,1,1) > 3) {
		$output = FALSE;
		}
	elseif (strlen($t) > 5) {
		$output = FALSE;
		}
	elseif (preg_match('|^[012][0-9][0-5][0-9]$|',$t)) {
		$output = TRUE;
		}
	elseif (preg_match('|^[012][0-9][\.:][0-5][0-9]$|',$t)) {
		$t=substr($t,0,2).substr($t,3,2);
		$output = TRUE;
		}
	else {
		$output = FALSE;
	}
	return $output;
}

function pubUnpub($itemDate, $pubDate=0, $endDate=0, $oneDayEvent=false) {
	// calculate Published flag for current time vs publication date range
	// Auto unpub date of day after event start if $oneDayEvent is true
	// (for events calendar). ONE_DAY constant defined in snippet code.
    // Test for date format, convert non-number to timestamp

    if (!is_numeric($itemDate)) $itemDate = strtotime($itemDate);
    if (!is_numeric($endDate)) $endDate = strtotime($endDate);
	if ($pubDate == "") {
		$pubDate = "0";
		$published = 1;
	} else {
		$published = ($pubDate <= time()) ? 1 : 0;
	}

	if ($endDate > 0) {
		$endDate += ONE_DAY;
	}
    elseif ($oneDayEvent) {
		$endDate =  $itemDate + ONE_DAY;
	}

	if ($endDate > 0 && $endDate < time()) {
		$published = 0;
	}
	return array('published'=>$published, 'unpub'=>$endDate, 'oneD'=>$oneDayEvent);
}

function setRank($table, $index, $docId, $parent, $inc=1, $offset) {
// add or update menu index for MODX resource item
	global $modx;
	resetRank($table, $parent,$inc);
	$ins = 2 * $index + $offset;
	return $ins;
}

function resetRank($table, $parent, $inc) {
// rewrite RANK field (menuindex) in unbroken sequence
	global $modx;
	$modx->db->query("SET @rank = 0");
	$modx->db->query("SET @inc = $inc");
	$query = "
		UPDATE $table
		SET menuindex = (SELECT @rank := @rank + @inc)
		WHERE parent = $parent
		ORDER BY menuindex
	";
	$modx->db->query($query);

	$next = $modx->db->select('@rank');

	return $next;
}

function setFormRank($table, $index=0, $inc=2, $offset=-1) {
// add or update rank in custom table
	global $modx;
	$next = resetFormRank($table, $inc);
	if ($index > 0) {
		$rank = $inc * $index + $offset;
	} else {
		$rank = $next;
	}
	return $rank;
}

function resetFormRank($table, $inc=1, $order="") {
// rewrite RANK field in unbroken sequence for custom table
// allow for additional order parameters for split lists
	global $modx;
	$orderString = (empty($order)) ? 'rank' : $order;
	$modx->db->query("SET @rank = 0");
	$modx->db->query("SET @inc = $inc");
	$query = "
		UPDATE $table
		SET rank = (SELECT @rank := @rank + @inc)
		ORDER BY $orderString
	";
	$modx->db->query($query);

	$next = $modx->db->select('@rank');
	return $next;
}

/*  Upload file
	Return result code from upload if error
	Return uploaded file path if OK
 */

function Upload($upload, $filesHome='assets/files/', $allowedTypes=NULL ) {
	global $modx;

	$fileForUp = $_FILES[$upload]['name'];
	$dest = $modx->config['base_path'] . $filesHome . $fileForUp;

	$outcome = $_FILES[$upload]['error'];

	if ($outcome === 0 AND (!is_uploaded_file($_FILES[$upload]['tmp_name']))) {
		$outcome = 99;
	}

	if (isset($allowedTypes)) {
		$allowedTypes = strtolower($allowedTypes);
		$allowedTypes = explode(',', $allowedTypes);
		$ext = strtolower(substr(strrchr($fileForUp, '.'),1));

		if ($outcome === 0
			AND (!in_array($ext, $allowedTypes))) {
			$outcome = 100;
		}
	}

	if ($outcome === 0) {
		$dest = $modx->config['base_path'] . $filesHome . $fileForUp;

		if(move_uploaded_file($_FILES[$upload]['tmp_name'], $dest)) {
	// set file permissions - may be system dependent. Uploads are 0600 on my system
			chmod($dest,0644);
			$outcome = $filesHome . $fileForUp;
		} else {
			$outcome = $_FILES[$upload]['error'];
			unlink($dest); //don't leave failures lying around - may be bogus
		}
	}
	return $outcome;
}

function debug($line = '', $vars, $logfile = 'H:/pkDebug.htm') {
	$logString = "\n<pre>";
	if (!is_array($vars)) {
		$vars = explode(',', $vars);
	}
	
	foreach ($vars as $var) {
		$logString .= "$line: " . print_r($var, 1) . "\n";
	}
	$logString .=  "</pre>\n\n";
	
	file_put_contents($logfile, $logString, FILE_APPEND);
	
	}
?>
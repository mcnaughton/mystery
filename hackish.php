<?php
$filenames = array( "1", "2", "3" );
foreach($filenames as $filename) {
	$handle = fopen($filename, "rb");
	$raw = fread($handle, filesize($filename));
	fclose($handle);
	$cleaned = trim(str_ireplace( array("\x0D", "^M", "\xD2", "\xF5\xF5", "\xF5"), array("\n","", '"', '"',"'"), $raw));
	$cleaned = 'Date:' . explode('Date:', $cleaned)[1];
	$cleaned = substr($cleaned, 0, strpos($cleaned, "\x03"));
	var_dump($cleaned);
	if (false === strpos($cleaned,"###")) {
		$ps = array_filter(explode("\n", $pieces[1]), function($a){return !empty($a);});
		foreach($ps as $p) {
			if (false !== strpos($p, ":")) {
				echo "HAS MARKER $p";
			}
		}
	} else {
		$pieces = explode("###",$cleaned);
	}
	$metas = explode("\n", $pieces[0]);
	$d = array();
	foreach($metas as $meta) {
		if (false !== strpos($meta, ":")) {
			$ks = explode(":",$meta);
			$d[trim($ks[0])] = trim($ks[1]);
		}
	}
	$ps = array_filter(explode("\n", $pieces[1]), function($a){return !empty($a);});
	$results = array(
		'meta' => $d,
		'content' => $ps
	);
	var_dump($results);
	file_put_contents('hackish/' . $filename . '.txt', json_encode($results));
}

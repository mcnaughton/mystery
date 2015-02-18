<?php
ini_set('memory_limit', '1G');
date_default_timezone_set('America/Los_Angeles');
function handle_dir($directory, $basedir = '.') {
	$scanned_directory = array_diff(scandir( $basedir . '/' . $directory), array('..', '.', '.DS_Store'));
	foreach($scanned_directory as $d) {
		if ( false !== strpos($d, '.dat') ) {
			continue;
		}
		if ( false !== strpos($d, '.idx') ) {
			continue;
		}
		if ( false !== strpos($d, 'Index') ) {
			continue;
		}
		if ( false !== strpos($d, 'Icon') ) {
			continue;
		}
		if (is_dir($basedir . '/' . $directory . '/' . $d)) {
			handle_dir($d, $basedir . '/' . $directory);
		} else {
			handle_item( $d, $basedir . '/' . $directory);
		}
	}
}

function handle_item($filename, $basedir = '') {
	$basedir = preg_replace("/^\.\//", "", $basedir);
	$handle = fopen( $basedir . '/' . $filename, "rb");
	$raw = fread($handle, filesize($basedir . '/' . $filename));
	fclose($handle);
	$basedir = preg_replace("/\s/", "_", $basedir);
	$filename = preg_replace("/\s/", "-", $filename);
	$start = false;
	$end = false;
	$ps = array();
	$current = false;
	$cleaned = trim(str_replace( array("\x0D\x03", "\x0D", "\x03", "\xD2", "\xF5\xF5", "\xF5","\x07"), array("\n", "\n", "", '"', '"',"'","\n"), $raw));
        $cleaned = str_replace( array("\xD0", "\xF2", "\xF3", "\xF4","`", "\xF5", "\xD2\xD5", "\xD5\xD5", "\xD5", "\xD3", "\xD1", "\xD4", "\xA9", "\x2E", "\xA5", "\x8E", "\xFB"), array('--', '"', '"', '"', "'", "'", '"', '"', "'", '"', '--', "'", "(c)", ".", "-", "é", ""), $cleaned );
	$cleaned = str_replace( "''", '"', $cleaned );

	$d = array( 'File' => "$basedir/$filename" );
	
	if ( false === strpos($cleaned,"Date:")) {
		$bottom = substr($cleaned, strpos($cleaned, "0002"));
		$rest = substr($bottom, 0, strrpos($bottom, "0004"));
		$content = explode("0002",$rest);

		foreach ( $content as $item ) {
			$zs = array();
			$piece = substr($item, strpos($item, ",") + 1);
			$piece = explode( "0004", $piece )[0];
			
			if ( false === strpos($piece,"0000") && 0 !== strpos( $piece, "0" )) {
				$zs = explode("\n", strtr( $piece, array("\x03" => "\n", "\x0D" => "" ) ) ); //preg_replace('/[\x00-\x1F\x80-\xFF]/', '', strtr( $piece, array("\x03" => "\n", "\x0d" => "\r" ) ) ) );
			}
			if (count($zs) > 1){
				$ps = array_merge($ps, $zs);
			} else if (count($zs) > 0) {
				$d['Full Text'] = implode("\r\n", array_filter($zs,function($a){return !empty($a);}));
			}
		}
		if (count($ps)>0) {
			$os = array_filter($ps, function($a){return !empty($a);});
		} else {
			echo "SKIP $basedir/$filename\n";
		}
		
                foreach($os as $oi => $o) {
                        if ( preg_match("/^Cox News Service$/i",$o) && $oi < 5 ) {
                        	$d[ 'Author' ] = $o;
				unset($os[$oi]);
                        } else if ( preg_match("/^Scripps Howard News Service$/i",$o) && $oi < 5 ) {
                        	$d[ 'Author' ] = $o;
				unset($os[$oi]);
                        } else if ( preg_match("/^The Associated Press$/i",$o) && $oi < 5 ) {
                        	$d[ 'Author' ] = $o;
				unset($os[$oi]);
                        } else if ( preg_match("/^By\s/i",$o) && $oi < 5) {
                                if ( 0 === $oi ) {
                                        $d[ 'Author' ] = explode( '/', preg_replace("/^By\s/i","", $os[$oi]) )[0];
                                } else if ( 1 == $oi ) {
                                        if (empty($d[ 'Full Text' ])) {
                                                $d[ 'Full Text' ] = $os[($oi-1)];
                                        }
                                        $d[ 'Author' ] = explode( '/', preg_replace("/^By\s/i","", $os[$oi]) )[0];
                                        unset($os[($oi-1)]);
                                } else if ( 2 == $oi ) {
                                        if (empty($d[ 'Full Text' ])) {
                                                $d[ 'Full Text' ] = $os[($oi-2)];
                                        }
                                        $d[ 'Author' ] = explode( '/', preg_replace("/^By\s/i","", $os[$oi]) )[0];
                                }       
                        } else {       
                        	$os[$oi] = preg_replace("/^(n|u)\s/", "* ", $o );
                        }
                }

	} else {

		$cleaned = preg_replace("/Author: ([^:]*?) Location:/", "Author: \$1\nLocation:", $cleaned);
		$cleaned = preg_replace("/Author: ([^:]*?) Quick Words:/", "Author: \$1\nQuick Words:", $cleaned);
		$cleaned = preg_replace("/Category: ([^:]*?) Author:/", "Category: \$1\nAuthor:", $cleaned);
		$cleaned = preg_replace("/Publication: ([^:]*?) Category:/", "Publication: \$1\nCategory:", $cleaned);
		$cleaned = preg_replace("/Date: ([^:]*?) Publication:/", "Date: \$1\nPublication:", $cleaned);
		$cleaned = preg_replace("/Date: ([^:]*?) Author:/", "Date: \$1\nAuthor:", $cleaned);
		$cleaned = preg_replace("/Date: ([^:]*?) Category:/", "Date: \$1\nCategory:", $cleaned);
		$cleaned = preg_replace("/Quick Words:\n/m", "Quick Words: ", $cleaned);
		$cleaned = 'Date:' . explode('Date:', $cleaned)[1];

		if ( false !== strpos($cleaned,0004)) {
			$cleaned = substr($cleaned, 0, strrpos($cleaned, "0004"));
		}

		$ps = array_filter(explode("\n", $cleaned), function($a){return !empty($a);});
		$os = array();
		$m = 0;
		$fulltext = false;
		foreach($ps as $i => $p) {
			if (false !== strpos($p, ":") && !$fulltext) {
				$ks = explode(":",$p);
				if ( 'Full Text' === trim($ks[0])) {
					$fulltext = true;
				}
				#$d[trim($ks[0])] = trim(join(":", array_slice($ks,1)));
				$d[trim($ks[0])] = trim($ks[1]);
			} else {
				$p = preg_replace("/^[0-9A-F]+,/i", '', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $p));
				if ( count(explode(" ",$p)) < 5 && preg_match("/^BY\s/i", $p) ) {
					$d['Author' ] = preg_replace("/^BY\s/i", "", $p);
				} else if (!empty($p)) {
					$os[] = $p;
				}
			}
		}
	}
	if ( isset($d['Author']) && strtoupper($d['Author']) === $d['Author'] && strlen($d['Author']) > 5) {
		$d['Author'] = ucwords( strtolower( $d['Author'] ) );
	}
	if ( isset($d['Author'])) {
		$d['Author'] = trim($d['Author']);
	}
	if ( isset($d['Full Text'])) {
		$d['Full Text'] = trim($d['Full Text']);
	}
	$content = trim( implode("\r\n\n", $os) );
	if ( !empty($d['Full Text']) && empty( $content ) ) {
		$content = $d['Full Text'];
		unset($d['Full Text']);
	}
	$results = array( 'data' => array(
		'meta' => $d,
		'content' => $content
		), 'updated' => time()
	);
	var_dump($results);
	if ( count($os) === 0 ) {
		return;
	}
	$md5 = md5("$basedir/$filename");
	$pub = strtotime($d['Date']);
	$year = date('Y', $pub);
	$month = date('m', $pub);
	$day = date('d', $pub);
	@mkdir("results/$year/$month/$day/",0755,true);
	@mkdir("yearly/$year/",0755,true);
	file_put_contents("yearly/$year/$md5.json", json_encode($results));
	file_put_contents("results/$year/$month/$day/$md5.json", json_encode($results));
}

//$filenames = array( "DEArchive", "Stories1", "Stories2", "Stories3", "Stories4" );
$filenames = array( "samples" );
foreach($filenames as $filename) {
	handle_dir($filename);
}
	


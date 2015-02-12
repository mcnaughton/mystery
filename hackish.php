<?php
$filename = "1";
$handle = fopen($filename, "rb");
$raw = fread($handle, filesize($filename));
fclose($handle);
var_dump($raw);
$sans_binary_headers = explode('$$$', bin2hex($raw))[1];
$sans_the_rest = explode('000',$sans_binary_headers)[0];
$cleaned = str_replace( array("^M","^C"), array("\n","\r\n"), $sans_the_rest);
file_put_contents('hackish/' . $filename '.txt');

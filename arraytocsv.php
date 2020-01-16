<?php

function array2csv($array,$filename)
{
	// output headers so that the file is downloaded rather than displayed
	header('Content-type: text/csv');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	 
	// do not cache the file
	header('Pragma: no-cache');
	header('Expires: 0');
	$out = fopen('php://output', 'w');
	foreach ($array as $fields) {
		fputcsv($out, $fields);
	}
	fclose($out);  
	//header('Location: index.php');
}

<?php

	include( "xmlrpc.inc" );

	// AutoRecall script
	// Queues unansweredd calls on a WombatDialer campaign
	// by reading them out of Asterisk.


	// ------------------------------------------------------
	// Edit parameters here
	// ------------------------------------------------------

	// QueueMetrics configuration
	$qm_server = "10.10.5.25";
	$qm_port = 8080;
	$qm_webapp = "queuemetrics";
	$qm_login ="robot";
	$qm_pass = "robot";

	// WombatDialer configuration
	$wbt_url = "http://10.10.5.18:8080/wombat";
	$wbt_cmp = "c1";

	// run parameters
	$queue = "300";
	$lookback = 3600 * 8 ; // in seconds
	$allowedPatterns = array( 
		"/^.../",
		"/^0041.+/"
	);

	// ------------------------------------------------------
	// End of parameters
	// ------------------------------------------------------


	// called numbers
	// to refreshed from the log file via eval()
	// we have to init the structure in case no log file exists
	$arCalled = array();


	// Deciding the time period	
	echo "Finding applicable period\n";

	if ( strlen($argv[1]) == 10 ) {
		$now = strtotime( $argv[1] );
		$from_time = $now;
		$to_time = $now + 34*3600;
	} else {
		$now = time();
		$to_time = $now;
		$from_time = $now - $lookback;
	}

	$today = date( 'Y-m-d', $now );

	// loading the log file
	echo "Loading call log file for $today\n";
	$brainfile = "numbers-called-$today.txt";

	if ( file_exists( $brainfile ) ) {
		$fd = "\$arCalled = " . file_get_contents( $brainfile ) . ";\n";
		eval(  $fd );
		//print "\n\n$fd\n\n";
	} 	

	//print_r( $arCalled );	
	$qmResults = queryQmServer( $from_time, $to_time );

	foreach ( $qmResults as $num => $arV ) {

		if ( isAllowed( $num )) {

			$arK = array_keys( $arV );
			asort( $arK );
			$lastTst = array_pop( $arK );
			$lastVal = $arV[$lastTst];

			if ( $lastVal == "T" ) {
				echo "# $num - Last call is taken @ " . rpcdate($lastTst) . "\n";
			} else {
				// check if it's already on the log
				if ( strlen( $arCalled[$num]["scheduled_at"]) > 0 ) {
					echo "# $num - already recalled  @ " . $arCalled[$num]["scheduled_at"] . "\n";					
				} else {
					echo "# $num - Last call lost @ " . rpcDate($lastTst) . " - Scheduling.\n";	
					addToWombat( $wbt_cmp, $num );
					$arCalled[$num]["scheduled_at"] = rpcDate( time() );
				}				
			}

		} else {
			echo "# $num - pattern not allowed\n";
		}

	}


	// save brainfile
	echo "Saving call log\n";
	file_put_contents($brainfile, var_export( $arCalled, true) );

	exit(0);

//
// Queries the QM server and returns an array of calls, 
// each of which contains the timestamp and T if taken or L if lost
//
// 	$out = array();
//	$out["5551234"] = array( "1234"=>"T", "1238"=>"L", "1235"=>"L" );
//	$out["5551235"] = array( "1236"=>"T" );
//	$out["5551236"] = array( "1234"=>"L" );
// 

function queryQmServer( $fromTst, $toTst) {

	global 	$qm_server, $qm_port, $qm_webapp, $qm_login, $qm_pass, $queue;
	$blocks = array();

	$t0 = time();
	echo "Looking for data between " .  rpcDate( $fromTst ) . " and " . rpcDate( $toTst ) . "\n"; 
	echo " on server '$qm_server' - queues '$queue'\n";

	$blocks = queryXmlRpc( $queue, rpcDate( $fromTst ), rpcDate( $toTst ) );

	$t1 = time() - $t0;
	echo "Query took $t1 seconds.\n";

	// process calls OK and KO

	$out = array();
	$out = loadCalls( $out, $blocks, "DetailsDO.CallsOkRaw", "CALLOK_tstEnd", "CALLOK_from", "T" );
	$out = loadCalls( $out, $blocks, "DetailsDO.CallsKoRaw", "CALLKO_tstEnd", "CALLKO_from", "L" );
	return $out;
}

// 
// load  a set of calls into the hash detailing [num][tst] = type
//
function loadCalls( $arResult, $blocks, $blockName, $colNameTst, $colNameNum, $type ) {
	$myBlk = $blocks[$blockName];
	$nColTst = findColumnHdr( $myBlk, $colNameTst);
	$nColNum = findColumnHdr( $myBlk, $colNameNum);

	//echo "For $marker: $colNameTst:$nColTst $colNameNum:$nColNum\n";

	for ( $r = 1; $r < sizeof($myBlk); $r++ ) {
		$num = $myBlk[$r][$nColNum];
		$tst = $myBlk[$r][$nColTst];

		//echo "arResult[$num][$tst] = $type\n";
		$arResult[$num][$tst] = $type;
	}
	//print_r( $arResult );
	return $arResult;
}

//
// find the number of column that a specific label is for
//
function findColumnHdr( $block, $colName ) {
	$row0 = $block[0];
	for ($i = 0; $i < sizeof($row0); $i++) {
		if ( $row0[$i] == $colName ) {
			return $i;
		}
	}

	echo "Column $colName not found in block";
	print_r($row0);
	exit(100);
}

// 
// actually does the querying by retrieving blocks CAllOkRaw and CallKoRaw
// in case of errors, abort.
//
function queryXmlRpc( $queues, $dtFrom, $dtTo ) {

	global 	$qm_server, $qm_port, $qm_webapp, $qm_login, $qm_pass;


  $blkArr = array();
  array_push( $blkArr, new xmlrpcval( "DetailsDO.CallsKoRaw" ) );
  array_push( $blkArr, new xmlrpcval( "DetailsDO.CallsOkRaw" ) );

  $req_blocks = new xmlrpcval($blkArr, "array" );

  $params = array(
                 new xmlrpcval($queues),
                 new xmlrpcval($qm_login),
                 new xmlrpcval($qm_pass),
                 new xmlrpcval(""),
                 new xmlrpcval(""),
                 new xmlrpcval($dtFrom),
                 new xmlrpcval($dtTo),
                 new xmlrpcval(""),
                 $req_blocks
  );  

  $c = new xmlrpc_client("$qm_webapp/xmlrpc.do", $qm_server, (integer) $qm_port );
  $c->return_type = 'phpvals'; 
  $r =& $c->send(new xmlrpcmsg( "QM.stats", $params));  

  if($r->faultCode()) {
    echo "Error: " . $r->faultString() . "\n";
    exit (100);

  } else {

  	// controlla il risultato
  	// in block "rsult"

  	$blocks = $r->value();

  	//print_r( $blocks );

  	if ( $blocks["result"][0][1] == "OK" ) {
  		return  $blocks;
  	} else {
  		echo "Error in XML-RPC query\n";
		print_r( $blocks );
		exit(100);  		
  	}

    
	}	

}

//
// Creates a fake data block
//
function queryXmlRpc_fake( $queues, $dtFrom, $dtTo ) {
	return array( 
		"DetailsDO.CallsOkRaw" => array( 
			array( "x", "tst", "num"),
			array( "x", "100", "5551234"),
			array( "x", "110", "5552"),
			array( "x", "120", "5551"),
		),
		"DetailsDO.CallsKoRaw" => array( 
			array( "x", "tst", "num"),
			array( "x", "102", "5551234"),
			array( "x", "114", "5552"),
			array( "x", "120", "5554"),
			array( "x", "167", "00415554"),

		),
	);

}




//
// schedule a call on WombatDialer
// like: 
//  http://127.0.0.1:8080/wombat/api/calls/?op=addcall&campaign=ABC&number=45678
//
function addToWombat( $campaign, $number ) {
	global $wbt_url;

	echo "Adding $number to campaign $campaign on WombatDialer.\n";
	$ue_cmp = urlencode($campaign);
	$ue_num = urlencode($number);
	
	$urlget = "$wbt_url/api/calls/?op=addcall&campaign=$ue_cmp&number=$ue_num";
	file_get_contents($urlget);

}

//
// Returns a timestamp in the format used by QM XML-RPC
//
function rpcDate( $tst ) {
	return date('Y-m-d.H:i:s', $tst );
}

//
// Check whether the number is allowed
// by testing it against all regexps defined in
// $allowedPatterns
//
// If no regexps defined, all numbers are valid
//

function isAllowed( $num ) {
	global $allowedPatterns;
	
	if (  sizeof( $allowedPatterns) == 0 ) {
		return true;
	}

	for ( $i=0; $i < sizeof($allowedPatterns); $i++ ) {
		if ( preg_match( $allowedPatterns[$i], $num)) {
			return true;
		}
	}

	return false;
}

?>


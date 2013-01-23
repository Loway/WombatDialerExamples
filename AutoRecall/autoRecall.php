<?php

	include( "xmlrpc.inc" );

	// QueueMetrics configuration

	$qm_server = "10.10.5.25";
	$qm_port = 8080;
	$qm_webapp = "queuemetrics";
	$qm_login ="robot";
	$qm_pass = "robot";

	// WombatDialer configuration
	$wbt_url = "http://10.10.5.18:8080/wombat";

	// run parameters
	$queue = "300";


	echo "Lets run this stuff.\n";

	$now = time();

	echo date('Y-m-d.H:i:s', $now );
	echo date('Y-m-d.H:i:s', $now-3600 );

	echo "\nPrinting ARGV\n";
	print_r($argv);



        echo "\n\nFirst PHP CLI script\n";
        //echo exec('ls -l\n') . "\n";
?>


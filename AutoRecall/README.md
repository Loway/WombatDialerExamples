AutoRecall
==========

Extract from one or more QueueMetrics queues a set of lost calls, checks that no successful 
subsequent call matches them, and uploads them to a WombatDialer campaign for call-back.

Usage:
------

    php autoRecall.php [2013-01-01]

The script does the following:

- if no day is given, then runs the query on a specific queue for the last 8 hours, pulling all 
  calls taken and lost, or
- if a day is given, run a query for the whole day and return a list of calls taken and lost
- loads the call log for the day
- if there are lost calls that do not have a later taken call from the same number,
  and if they are not already present in the day's call log, then they are queued to Wombat
  on the given campaign if they match one number regexp
- the call log is written again and updated so on the next execution
  only numbers not already dialed for the day will be recalled

Call logs are stiored in the same directory under the name "numbers-called-2013-01-23.txt" and 
are simple PHP data structures that are eval'd - so they are easy to read and modify.

Example:

    array (
      5551234 => 
      array (
        'scheduled_at' => '2013-01-23.16:23:44',
      ),
      5551236 => 
      array (
        'scheduled_at' => '2013-01-23.16:23:44',
      ),
    )


Configuration
-------------

The following parameters should be edited in the script:

	$qm_server = "10.10.5.25";
	$qm_port = 8080;
	$qm_webapp = "queuemetrics";
	$qm_login ="robot";
	$qm_pass = "robot";

These parameters specify the XML-RPC connector of your QueueMetrics instance.

	$wbt_url = "http://10.10.5.18:8080/wombat";
	$wbt_cmp = "X123";

These parameters specify the URL of WombatDialer and the campaign that calls should be added to.
The dialer must be running when the calls are added and the campaign should be active (usually IDLE).
Note that the campaign you use for call-back might be paused so that call-backs are actually deferred
during periods of high activity.

	$queue = "300";
	$lookback = 3600 * 8 ; // in seconds
	$allowedPatterns = array( 
		"/^555..../",
		"/^0041.+/"
	);


These parameters decide which set of queue(s) should be scanned and how long is 
to look back for the current day. Multiple queues can be used, separated by the pipe character.

The last parameter is a set of regexps that will be used to check the numbers read from 
QueueMetrics. At least one regexp must match for the number to be queued. This is used
to avoid queueing invalid numbers or - worse - malicious numbers.

Usage
-----

To load lost calls that do not have a subsequent successful call, you simply set a cron job
to call the script e.g. every hour - as the script remembers the numbers already called during 
the day, it will not try and schedule calls that were already scheduled. No number will be called
more than once per day.

To load calls for a specific past day, you manually call the script passing the day in 
YYYY-MM-DD format.

Notes
-----

This script uses the PHP xmlrpc library - see http://phpxmlrpc.sourceforge.net

WombatDialer can be found at http://wombatdialer.com

QueueMetrics can be found at http://queuemetrics.com


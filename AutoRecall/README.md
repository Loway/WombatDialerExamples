AutoRecall
==========


Usage:

php wombatRecall.php [-d 2013-01-01] [-f recallLog]

The script does the following:
- for the given day, run a query for the whole day on QueueMetrics and return a list of 
  calls taken and lost for the given queues.
- if the day is today, the query is run from the last midnight to now minus the grace period
- loads a list of numbers already queued for dialing for the day (as in -f)
- if there are lost calls that do not have a later taken call from the same number,
  and if they are not already present in the recallLog, then they are queued to Wombat
  on the given campaign
- the recallLog is written again and updated so on the next execution
  only numbers not already dialed for the day will be recalled


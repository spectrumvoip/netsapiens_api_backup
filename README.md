# netsapiens_api_backup
Backup NetSapiens API to JSON files

This script loops through every domain pulling all the info for the following tables:

  domain
  domain-billing
  subscriber
  device
  audio-moh
  audio-greeting
  callqueue
  callidemgr
  conference
  department
  dialplan
  dialpolicy
  dialrule
  phoneconfiguration
  phonenumber-did
  phonenumber
  smsnumber
  timeframe
  
It builds a subfolder for every domain, then a separate file for each table.
  
It prefixes this file with the date to version the files since I run it once every night with the following shell script:
  
#!/bin/sh

# Pull todays backup
/usr/local/SAN/scripts/stratus_api_backup.php 1

# Delete files older than 2 days.
/usr/bin/find /usr/local/SAN/api_backups/* -mtime +2 -exec rm -v {} \;

exit;

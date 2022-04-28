# reboot-monitor

A very simple flask app which does some onitoring on when linux machines need rebooting
due to automated security updates, and as a later addition, when SSL certificates are
within 30 days of expiry. 

## Reboot status

For handling reboot status, put a file in `/etc/cron.daily` 
on the server to be monitored, containing a modified version of:-
```
#!/usr/bin/env bash
if [ -f /var/run/reboot-required ]; then
  curl "http://<monitor-address>/?machine=$hostname.dide.ic.ac.uk&status=1"
else
  curl "http://<monitor-address>/?machine=$hostname.dide.ic.ac.uk&status=0"
fi
```
(and `chmod` the file with `755` or course).

The next day (or after manually triggering the script), check the `greylist.csv` 
file for the hostname and IP address, and copy that line into `whitelist.csv`.


## Certificate checks

Add a line to `certlist.csv` containing `server,port`.

## The report

Have the server call `run.bat` in the `secret` folder as a Windows Task, 
with that same folder as its current path. This will send a combined
report to a teams channel. 

## Weirdness

This is deployed as a flask app on `monitor.dide.ic.ac.uk` in the department, 
using Xampp / Apache for Windows with mod_wsgi compiled in, which is a little
eccentric, but shows it is possible to wrangle.

```CSV files```

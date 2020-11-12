# reboot-monitor

A very simple flask app which machines can regularly call with:-

```
https://monitor/?machine=Machine_Name&status=1
```

where status 0 means the machine does not need a reboot, and any other status means that it does.

Then having the server call `run.bat` in the `secret` folder, with that same folder as its 
current path, will send a combined report of all the machines that have reported they need a reboot,
to a teams channel. The report might say:-

```
Reboot Status

bill.company.com does not need rebooting, but did not report today.
ben.company.com has needed a reboot since yesterday.
weed.company.com has needed a reboot for 31 days!
```

This is deployed as a flask app on `monitor.dide.ic.ac.uk` in the department, 
using Xampp / Apache for Windows with mod_wsgi compiled in.

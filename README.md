# reboot-monitor

A very simple bit of PHP which machines can regularly call with:-

```
https://server/monitor/?machine=Machine_Name&Status=1`
```

where status 0 means the machine does not need a reboot, and status 1 means that it does.

Then having the server call `run.bat` daily will send a combined report of all the 
machines that have reported they need a reboot, to a teams channel.

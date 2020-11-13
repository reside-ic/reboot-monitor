import csv, sys, requests, os
from pathlib import Path
from filelock import Timeout, FileLock
from datetime import date

# This script sends a report to a teams webhook, if there are any machines
# that reported they needed a reboot. Set the web hook below.

team_webhook = "<INSERT TEAM WEBHOOK HERE>"

# Further down, all files will be prefixed with $file. If we write formal
# tests, $file could be changed to "test", to avoid interfering with existing
# data.

file = "data";

os.chdir("C:/path/to/monitor/secret")

############################################################
# If a file doesn"t exist, create and write the line to it #
############################################################

def ensure_exists(fname, line):
    if not Path(fname).exists():
        file = open(fname, "w")
        file.write(line + "\n")
        file.close()

############################################################
# Create pictorial indication of associated stress level   #
############################################################

def emotional_payload(status):
    if status > 400:
        return "&#x1F631;"
    elif status > 100:
        return "&#x1F62C;"
    else:
        return ""

def main():
    data_file = file + ".csv"
    data_lock_file = file + "lock"
    ensure_exists(data_lock_file, "lock")

    text = ""
    the_date = date.today().strftime("%Y-%m-%d")

    lockfile = FileLock(data_lock_file)
    with lockfile:
        ensure_exists(data_file, "machine,status,last_date")
        with open(data_file) as csvfile:
            reader = csv.reader(csvfile, delimiter =",")
            line_count = -1
            for row in reader:
                line_count += 1
                if line_count > 0:
                    machine = row[0]
                    status = int(row[1])
                    last_date = row[2]
                    date_info = ""
                    if last_date != the_date:
                        date_info = ", but did not report today"
                    if status == 0:
                        text = text + machine + " does not need rebooting" + date_info + ".   \n"
                    elif status == 1:
                        text = text + machine + " has needed a reboot since yesterday" + date_info + ".   \n"
                    elif status > 1:
                         extra = emotional_payload(status)
                         if last_date != the_date:
                             date_info = "It also did not report today."
                         text = text + machine + " has need a reboot for **"
                         text = text + str(status) + " days!** " + extra + " " + date_info + "   \n"

    resp = requests.post(
        team_webhook,
        json = dict(channel = "#bot-reboot", title = "Reboot Status", text = text),
        verify = True)


if __name__ == "__main__":
    main()

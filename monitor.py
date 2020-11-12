from flask import Flask, request, redirect
from pathlib import Path
from filelock import Timeout, FileLock
from datetime import date
import csv, os, sys

app = Flask(__name__)

############################################################
# If a file doesn"t exist, create and write the line to it #
############################################################

def ensure_exists(fname, line):
    if (not Path(fname).exists()):
        file = open(fname, "w")
        file.write(line + "\n")
        file.close()

###########################################################################
# If any misuse of this script occurs, take the user to an enriching page #
###########################################################################

def educate_user():
    return redirect("https://www.youtube.com/watch?v=dQw4w9WgXcQ")

###########################################################################
# Check provided machine and ip against those known in the greylist       #
# Return TRUE if we"ve got match, FALSE otherwise                         #
###########################################################################

def check_greylist(grey_list, given_machine, given_ip):
    overwritten_entry = False
    new_grey_list_file = grey_list + ".new"
    new_grey_list = open(new_grey_list_file, "w")
    with open(grey_list) as csvfile:
        reader = csv.reader(csvfile, delimiter =",")
        line_count = -1
        for row in reader:
            line_count += 1
            if line_count == 0:
                new_grey_list.write(row[0] + "," + row[1] + "\n")

            elif line_count > 0:
                grey_machine = row[0]
                grey_ip = row[1]
                if ((grey_machine == given_machine) and (grey_ip == given_ip)):
                    csvfile.close()
                    new_grey_list.close()
                    os.remove(new_grey_list_file)
                    return
                elif ((grey_machine == given_machine) or (grey_ip == given_ip)):
                    new_grey_list.write(given_machine + "," + given_ip + "\n")
                    overwritten_entry = True
                else:
                    new_grey_list.write(row[0] + "," + row[1] + "\n")

    if (not overwritten_entry):
        new_grey_list.write(given_machine + "," + given_ip + "\n")
    new_grey_list.close()
    os.remove(grey_list)
    os.rename(new_grey_list_file, grey_list)


###########################################################################
# Check provided machine and ip against those known in the whitelist      #
# Return TRUE if we"ve got match, FALSE otherwise                         #
###########################################################################

def check_whitelist(white_list, grey_list, given_machine, given_ip):
    with open(white_list) as csvfile:
        reader = csv.reader(csvfile, delimiter = ",")
        line_count = -1
        for row in reader:
            line_count += 1
            if line_count > 0:
                white_machine = row[0]
                white_ip = row[1]
                if ((white_machine == given_machine) and (white_ip == given_ip)):
                    csvfile.close()
                    return True

    check_greylist(grey_list, given_machine, given_ip)
    return False


##########################
# Main usage starts here #
##########################

@app.route("/")
def hello():
    ###################################################################
    # Arguments are....                                               #
    #     machine - host-name reported by machine                     #
    #      status - 1 means "I need an update", 0 means "I don't"     #
    #        test - if 1 is for testing - prefix files with `test`    #
    #          ip - if test is enabled, pretend this is the host's ip #
    ###################################################################

    os.chdir(app.root_path)

    arg_hostname = request.args.get("machine", "")
    arg_status = request.args.get("status", "")

    if ((arg_hostname == "") and (arg_status == "")):
        return educate_user()

    if ((arg_status != "0") and (arg_status != "1")):
        return educate_user()

    arg_test = (request.args.get("test", 0) != 0)
    arg_status = int(arg_status)

    data_file = ("secret/test" if arg_test else "secret/data") + ".csv"
    data_lock_file = "secret/testlock" if arg_test else "secret/datalock"
    white_file = ("secret/testwhite" if arg_test else "secret/whitelist") + ".csv"
    grey_file = ("secret/testgrey" if arg_test else "secret/greylist") + ".csv"

    test_ip = request.args.get("ip", "")
    if ((arg_test) and (test_ip != "")):
        host_ip = test_ip
    else:
        host_ip = request.remote_addr

    the_date = date.today().strftime("%Y-%m-%d")

    #################################################
    # Update machines. Do this in a file lock mutex #
    #################################################

    ensure_exists(data_lock_file, "lock")
    lockfile = FileLock(data_lock_file)

    with lockfile:
        ensure_exists(data_file, "machine,status,last_date")
        ensure_exists(white_file, "machine,ip")
        ensure_exists(grey_file, "machine,ip")

        #####################################################
        # If machine is in the white-list, but different ip #
        #####################################################

        if (not check_whitelist(white_file, grey_file, arg_hostname, host_ip)):
            return educate_user()
            sys.exit()

        ###################################################
        # The machine and ip check out ok. Update status  #
        ###################################################

        new_data_file = data_file + ".new"
        new_data = open(new_data_file, "w")
        found_machine = False

        with open(data_file) as csvfile:
            reader = csv.reader(csvfile, delimiter =",")
            line_count = -1
            for row in reader:
                line_count += 1

                if (line_count == 0):
                    new_data.write(row[0] + "," + row[1] + "," + row[2] + "\n")

                else:
                    data_machine = row[0]
                    data_status = int(row[1])
                    if (data_machine == arg_hostname):
                        found_machine = True
                        new_status = str((1 + data_status) * arg_status)
                        new_data.write(data_machine + "," + new_status + "," + the_date + "\n")
                    else:
                        new_data.write(row[0] + "," + row[1] + "," + row[2] + "\n")

        if (not found_machine):
            new_data.write(arg_hostname + "," + str(arg_status) + "," + the_date + "\n")

        new_data.close()
        os.remove(data_file)
        os.rename(new_data_file, data_file)
    return "OK\n"

if __name__ == "__main__":
    app.run()

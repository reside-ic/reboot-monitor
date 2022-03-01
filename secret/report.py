import csv, sys, requests, os
from pathlib import Path
from filelock import Timeout, FileLock
from datetime import date, datetime, timedelta

import OpenSSL
import ssl, socket

# This script sends a report to a teams webhook, if there are any machines
# that reported they needed a reboot. Set the web hook below.

team_webhook = "https://imperiallondon.webhook.office.com/webhookb2/" \
    "ba231111-1572-42ae-981e-c8bc7aa681ef@2b897507-ee8c-4575-830b-4f8267c3d307/IncomingWebhook/" \
    "98b79cbcaefa49ef88b5d0e10cf61d16/7412aca5-7c7c-4f45-9d0e-97c9862e5945"

# Further down, all files will be prefixed with $file. If we write formal
# tests, $file could be changed to "test", to avoid interfering with existing
# data.

file = "data"
cert = "certlist"

os.chdir("C:/xampp/FlaskApps/monitor/secret")

############################################################
# If a file doesn"t exist, create and write the line to it #
############################################################

def ensure_exists(fname, line):
    if (not Path(fname).exists()):
        file = open(fname, "w")
        file.write(line + "\n")
        file.close()

############################################################
# Lookup a cert and report expiry or inaccessibility       #
############################################################

def test_cert_expiry(server, port, dt_now, dt_30):
    try:
        conn = ssl.create_connection((server, port))
        context = ssl.SSLContext(ssl.PROTOCOL_SSLv23)
        sock = context.wrap_socket(conn, server_hostname=server)
        cert = ssl.DER_cert_to_PEM_cert(sock.getpeercert(True))
    except BaseException as err:
        print(err)
        return (type(err).__name__ + " reading cert for " + server + ":" + port+ "   \n")
    x509 = OpenSSL.crypto.load_certificate(OpenSSL.crypto.FILETYPE_PEM, cert)
    expiry = x509.get_notAfter().decode('ascii')[0:8]
    expiry = datetime.strptime(expiry, "%Y%m%d")
    if (dt_now > expiry):
        return ("SSL cert " + server + " has expired!   \n")
    if (dt_30 > expiry):
        return ("SSL cert " + server + " expires on " + datetime.strftime(expiry, "%Y-%m-%d") + "   \n")
    return ""

############################################################
# Create pictorial indication of associated stress level   #
############################################################

def emotional_payload(status):
    if (status > 400):
        return "&#x1F631;"
    elif (status > 100):
        return "&#x1F62C;"
    else:
        return ""

############################################################
# Messages about how long reboot had been needed           #
############################################################

def machine_status(machine, status, the_date, last_date):
    date_info = ""
    if (last_date != the_date):
        date_info = ", but did not report today"
    if (status == 0):
        return (machine + " does not need rebooting" + date_info + ".   \n")
    if (status == 1):
        return (machine + " has needed a reboot since yesterday" + date_info + ".   \n")
    extra = emotional_payload(status)
    if (last_date != the_date):
        date_info = "It also did not report today."
    text = machine + " has needed a reboot for **"
    text = text + str(status) + " days!** " + extra + " " + date_info + "   \n"
    return(text)

############################################################
# Do the work                                              #
############################################################

def main():
    data_file = file + ".csv"
    data_lock_file = file + "lock"
    ensure_exists(data_lock_file, "lock")
    cert_file = cert + ".csv"

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
                if (line_count > 0):
                    text = text + machine_status(row[0], int(row[1]), the_date, row[2]) 

        text = text + "   \n\n"
        
        dt_now = datetime.now()
        dt_30 = dt_now + timedelta(days = 30)

        with open(cert_file) as certfile:
            reader = csv.reader(certfile, delimiter = ",")
            line_count = -1
            for row in reader:
                line_count += 1
                if (line_count > 0):
                    text = text + test_cert_expiry(row[0], row[1], dt_now, dt_30)

    resp = requests.post(
        team_webhook,
        json = dict(channel = "#bot-reboot", title = "Daily Update", text = text),
        verify = True)

if __name__ == "__main__":
    main()

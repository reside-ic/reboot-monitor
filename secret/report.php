<?php

// This script sends a report to a teams webhook, if there are any machines 
// that reported they needed a reboot. Set the web hook below.

$team_webhook = "INSERT_WEBHOOK_ADDRESS_HERE";

// Further down, all files will be prefixed with $file. If we write formal 
// tests, $file could be changed to "test", to avoid interfering with existing
// data.

$file = "data";

// Check we have a lock file available to ensure exclusive access.

function check_lockfile_exists($file) {
  if (!file_exists($file.".lock")) {
    $f = fopen($file.".lock", "w");
    fwrite($f, "lock".PHP_EOL);
    fclose($f);
  }
}

// Check we have a data file.

function check_datafile_exists($file) {
  if (!file_exists($file.".csv")) {
    $f = fopen($file.".csv", "w");
    fwrite($f, "machine,status".PHP_EOL);
    fclose($f);
  }
}

//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

check_lockfile_exists($file);

$nothing_to_do = true;    // If this remains true, then don't bother sending.
$text = "";               // Accumulate text for the message.

$fl = fopen($file.".lock", "r+");      // Acquire mutex
if (flock($fl, LOCK_EX)) {
  check_datafile_exists($file);
  $fin = fopen($file.".csv", "r");
  $str = fgets($fin);                  // Skip CSV header
  $str = fgets($fin);                  // First line (fgets returns false on EOF)
  while ($str != false) {
    list($machine, $status) = explode(",", $str);    // Split by commas.
    $status = intval($status);
    if ($status == 0) {
      $text = $text.$machine." does not need rebooting.   \n";
    } else if ($status == 1) {
      $text = $text.$machine." has needed a reboot since yesterday.   \n";
      $nothing_to_do = false;
    } else if ($status > 1) {
      $extra = "";
      if ($status > 400) $extra = "&#x1F631;";
      else if ($status > 100) $extra = "&#x1F62C;";
      $text = $text.$machine." has needed a reboot for **".$status." days!** ".$extra."   \n";
      $nothing_to_do = false;
    }
    $str = fgets($fin);                // Next line in CSV (returns FALSE on EOF)
  }
  fclose($fin);
  flock($fl, LOCK_UN);                 // Done, free file mutex.

  // Don't send a report if nothing needs a reboot
  if ($nothing_to_do) {
    exit();
  }

  // Otherwise, here's how we do the curl from PHP...

  $header = array();
  $header[] = "Content-type: application/json";

  $jsonData = Array(
    "channel" => "#bot-reboot",
    "title" => "Reboot Status",
    "text" => $text);
  
  $jsonDataEncoded = json_encode($jsonData, true);

  $c = curl_init();
  curl_setopt($c, CURLOPT_URL, $team_webhook);
  curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($c, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt($c, CURLOPT_POST, 1);
  curl_setopt($c, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  curl_setopt($c, CURLOPT_HEADER, true);
  curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($c, CURLOPT_HTTPHEADER, $header);
  curl_exec($c);
  curl_close($c);
}

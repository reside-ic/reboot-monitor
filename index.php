<?php

// Very simple script to be called by machine reporting
// whether or not they want to be rebooted following
// updates. Accepts three arguments as GET params:-

// machine : name of machine.
// status  :  0 = Doesn't need rebooting.
//          !=0 = Does need rebooting.
//
// test    : If supplied, then run in test mode, with "test"
//           files instead of "data" files.
//   ip    : In test mode, pretend this is the client ip.

// If a file doesn't exist, create it with a given firstline.

function check_file_exists($file, $firstline) {
  if (!file_exists($file)) {
    $f = fopen($file, "w");
    fwrite($f, $firstline.PHP_EOL);
    fclose($f);
  }
}

// Redirect user to appropriate documentation if the script is
// called in an invalid way.

function educate_user() {
  header('Location: https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  exit();
}

// Check the arguments are set, otherwise refer the
// caller to appropriate documentation.

function get_arguments() {

  if ((!isset($_GET['machine'])) || 
      (!isset($_GET['status']))) {
    educate_user();
  }

  // Optionally, accept a "test" argument, which will cause use
  // files starting with "test" instead of "data".

  $testing = isset($_GET['test']);
  $datafile = ($testing)? "secret/test" : "secret/data";
  $whitefile = ($testing)? "secret/testwhite" : "secret/whitelist";
  $greyfile = ($testing)? "secret/testgrey" : "secret/greylist";

  // Attempt to get the IP of client from PHP, unless we override
  // it for testing.

  $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:"";
  if (($testing) && (isset($_GET['ip']))) $ip = $_GET['ip'];
 
  return array($_GET['machine'], intval($_GET['status']), 
               $datafile, $whitefile, $greyfile, $ip);
}

function check_greylist($grey, $machine, $ip) {
  $fgin = fopen($grey.".csv", "r");    // Read existing greylist
  $fgout = fopen($grey."2.csv", "w");  // Create new greylist

  $str = fgets($fgin);                  // Read CSV header
  fwrite($fgout, $str);                 // Write to new file
  $str = fgets($fgin);                  // Read first line of data
  
  $added = FALSE;

  while ($str != false) {
    list($gmachine, $gip) = explode(",", $str);
    $gip = trim(preg_replace('/\s\s+/', '', $gip)); // Remove all space/nl
    
    if (($machine == $gmachine) && ($gip == $ip)) { // Found in greyist
      fclose($fgin);
      fclose($fgout);                               // Close all files
      unlink($grey."2.csv");                        // Delete unwanted new
      return;                                       // And we're done.
    }

    if (($machine == $gmachine) || ($gip == $ip)) { // Partial match
      fwrite($fgout, $gmachine.",".$gip.PHP_EOL);   // update greylist
      $added = TRUE;
  
    } else {
      fwrite($fgout, $str);
    }
    $str = fgets($fgin);
  }
  fclose($fgin);

  if (!$added) {  // No match or partial match found.
    fwrite($fgout, $machine.",".$ip.PHP_EOL);
  }
  fclose($fgout);
  unlink($grey.".csv");                // Kill the old,
  rename($grey."2.csv", $grey.".csv"); // Rename the new to the old
}

// Check a (machine,ip) is on the whitelist. If it is, 
// then return TRUE. It not, then add to grey list.
// replacing any existing machine with that name or ip, and
// exit without making a report.

function check_whitelist($white, $grey, $machine, $ip) {
  $fwin = fopen($white.".csv", "r");    // Read existing whitelist

  $str = fgets($fwin);                  // Read CSV header
  $str = fgets($fwin);                  // Read first line of data

  while ($str != false) {
    list($wmachine, $wip) = explode(",", $str);
    $wip = trim(preg_replace('/\s\s+/', '', $wip)); // Remove all space/nl
    
    if (($machine == $wmachine) && ($wip == $ip)) { // Found in whitelist
      fclose($fwin);
      return TRUE;                                  // And we're done.
    }
    $str = fgets($fwin);
  }
  fclose($fwin);

  // Not found - so update grey-list.

  check_greylist($grey, $machine, $ip);
  return FALSE;
}


//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

list ($arg_machine, $arg_status, $datafile, 
      $whitefile, $greyfile, $ip) = get_arguments();

check_file_exists($datafile.".lock", "lock");
$fl = fopen($datafile.".lock", "r+");
if (flock($fl, LOCK_EX)) {
  
  check_file_exists($datafile.".csv", "machine,status");
  check_file_exists($whitefile.".csv", "machine,ip");
  check_file_exists($greyfile.".csv", "machine,ip");

  if (!check_whitelist($whitefile, $greyfile, $arg_machine, $ip)) {
    flock($fl, LOCK_UN);
    fclose($fl);
    educate_user();
  }
  
  $found_machine = false;

  $fin = fopen($datafile.".csv", "r");     // Create a new version of the data
  $fout = fopen($datafile."2.csv", "w");   // file, based on the original.
  
  $str = fgets($fin);                  // Read CSV header
  fwrite($fout, $str);                 // and write into new file..
  $str = fgets($fin);                  // Read first line of data

  while ($str != false) {              // While (!eof) effectively

    list($machine, $status) = explode(",", $str);  // Split by comma.
    $status = intval($status);         // Remove newline from $status

    if ($machine == $arg_machine) {    // If machine on this line matches arg,
      $found_machine = true;           // then we've found it...
      if ($arg_status == 0) {          // If status arg is 0, reset day count.
        fwrite($fout, $machine.
               ",0".PHP_EOL);
      } else {                         // Otherwise, increase day count by 1.
        fwrite($fout, $machine.",".    
              (1 + $status).PHP_EOL);  
      }
    } else {                           // Machine didn't match arg - copy line
      fwrite($fout, $str);             // into new file.
    }
    $str = fgets($fin);                // Read next line - returns FALSE on eof.
  }

  if (!$found_machine) {               // First time of machine (on whitelist)
    fwrite($fout, $arg_machine.",".$arg_status.PHP_EOL);
  }

  fflush($fout);
  fclose($fin);
  fclose($fout);                       // All done - close both files,
  unlink($datafile.".csv");                // Kill the old,
  rename($datafile."2.csv", $datafile.".csv"); // Rename the new to the old
  flock($fl, LOCK_UN);                 // And then free the mutex
  fclose($fl);
}

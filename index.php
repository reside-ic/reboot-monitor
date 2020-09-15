<?php

// Very simple script to be called by machine reporting
// whether or not they want to be rebooted following
// updates. Accepts three arguments as GET params:-

// machine : name of machine.
// status  :  0 = Doesn't need rebooting.
//           >0 = Does need rebooting.
//           <0 = Stop monitoring this machine.
// test    : If supplied, then run in test mode, with "test"
//           files instead of "data" files. Not used yet...

// Here, check a lockfile exists, which we'll use to ensure
// queuing with exclusive access, if multiple machines report
// simultaneously. Probably Apache ensures this anyway, but
// still...

function check_lockfile_exists($file) {
  if (!file_exists($file.".lock")) {
    $f = fopen($file.".lock", "w");
    fwrite($f, "lock".PHP_EOL);
    fclose($f);
  }
}

// And also create an empty CSV file if there
// isn't one already.

function check_datafile_exists($file) {
  if (!file_exists($file.".csv")) {
    $f = fopen($file.".csv", "w");
    fwrite($f, "machine,status".PHP_EOL);
    fclose($f);
  }
}

// Check the arguments are set, otherwise refer the
// caller to appropriate documentation.

function get_arguments() {
  if (!(isset($_GET['machine'])) || (!isset($_GET['status']))) {
    header('Location: https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    exit();
  }

  // Optionally, accept a "test" argument, which will cause use
  // files starting with "test" instead of "data".

  $file = (isset($_GET['test'])? "test" : "data");
  return array($_GET['machine'], intval($_GET['status']), $file);
}

//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

list ($arg_machine, $arg_status, $file) = get_arguments();

check_lockfile_exists($file);

$fl = fopen($file.".lock", "r+");
if (flock($fl, LOCK_EX)) {
  check_datafile_exists($file);
  $found_machine = false;

  $fin = fopen($file.".csv", "r");     // Create a new version of the data
  $fout = fopen($file."2.csv", "w");   // file, based on the original.
  
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

      } else if ($arg_status > 0) {    // Positive status: increment day count by 1.
        fwrite($fout, $machine.",".    // (and implicitly, negative status = omit
              (1 + $status).PHP_EOL);  // machine from new file - ie, stop reporting it)
      }

    } else {                           // Machine didn't match arg - copy line
      fwrite($fout, $str);             // into new file.
    }

    $str = fgets($fin);                // Read next line - returns FALSE on eof.

  }

  // If the machine on the arg wasn't found in the file, 
  // then we need to add it (provided status was >=0)

  if ((!$found_machine) && ($arg_status >= 0)) {
    fwrite($fout, $arg_machine.",".$arg_status.PHP_EOL);
  }
  fflush($fout);
  fclose($fin);
  fclose($fout);                       // All done - close both files,
  unlink($file.".csv");                // Kill the old,
  rename($file."2.csv", $file.".csv"); // Rename the new to the old
  flock($fl, LOCK_UN);                 // And then free the mutex
  fclose($fl);
}

?>
<?php

// A couple of tests for the monitor...

$home = "https://mrcdata.dide.ic.ac.uk/monitor/";

function clean_up($create_lock) {
  if (file_exists("test.csv")) {
    unlink("test.csv");
  }
  if (file_exists("test.lock")) {
    unlink("test.lock");
  }
  if (file_exists("runtest.lock")) {
    unlink("runtest.lock");
  }
  if ($create_lock) {
    $f = fopen("runtest.lock", "w");
    fputs($f, "lock");
    fclose($f);
  }
}

function compare_data($lines) {
  $result = true;
  $f = fopen("test.csv", "r");

  for ($i = 0; $i < count($lines); $i++) {
    $s = rtrim(fgets($f));
    if (!($s == $lines[$i])) $result = false;
  }
  fclose($f);
  return ($result)?"OK":"FAIL";
}

function test_php($machine, $status, $page) {
  global $home;
  $get = http_build_query(array(
    'machine' => $machine,
    'status' => $status,
    'test' => 1));
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $home.$page."?".$get);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_exec($ch);
  curl_close($ch);
}

function test_new_machine() {
  // Add new machine without needing reboot
  test_php("machine1", 0, "index.php");
  echo "test_new_machine (1): ".compare_data(array("machine,status","machine1,0"))."\n";

  // Add new machine needing reboot
  test_php("machine2", 1, "index.php");
  echo "test_new_machine (2): ".compare_data(array("machine,status","machine1,0","machine2,1"))."\n";

  // Add machine with negative status (should get ignored)
  test_php("machine3", -1, "index.php");
  echo "test_new_machine (3): ".compare_data(array("machine,status","machine1,0","machine2,1"))."\n";
}

function test_remove_machine() {
  test_php("machine1", -1, "index.php");
  echo "test_remove_machine (1): ".compare_data(array("machine,status","machine2,1"))."\n";
  test_php("machine2", -1, "index.php");
  echo "test_remove_machine (2): ".compare_data(array("machine,status"))."\n";
}

function test_add_day() {
  test_php("machine1", 0, "index.php");
  test_php("machine2", 1, "index.php");
  test_php("machine1", 0, "index.php");
  test_php("machine2", 1, "index.php");
  test_php("machine1", 1, "index.php");
  test_php("machine2", 1, "index.php");
  echo "test_add_day (1): ".compare_data(array("machine,status", "machine1,1", "machine2,3"))."\n";
}

function test_reset_day() {
  test_php("machine2", 0, "index.php");
  echo "test_reset_day (1): ".compare_data(array("machine,status", "machine1,1", "machine2,0"))."\n";
  
}

//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

clean_up(true);

$fl = fopen("runtest.lock", "r+");
if (flock($fl, LOCK_EX)) {
  $fn = fopen("test.csv", "w");
  fwrite($fn,"machine,status\n");
  fclose($fn);

  test_new_machine();
  test_remove_machine();
  test_add_day();
  test_reset_day();

  flock($fl, LOCK_UN);
}

fclose($fl);
clean_up(false);
?>
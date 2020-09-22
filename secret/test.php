<?php

// A couple of tests for the monitor...

$home = "https://mrcdata.dide.ic.ac.uk/monitor/";

function check_file_exists($file, $firstline) {
  if (!file_exists($file)) {
    $f = fopen($file, "w");
    fwrite($f, $firstline.PHP_EOL);
    fclose($f);
  }
}

function clean_up($create_lock) {
  if (file_exists("test.csv")) unlink("test.csv");
  if (file_exists("test.lock")) unlink("test.lock");
  if (file_exists("runtest.lock")) unlink("runtest.lock");
  if (file_exists("testwhite.csv")) unlink("testwhite.csv");
  if (file_exists("testgrey.csv")) unlink("testgrey.csv");
  if ($create_lock) {
    $f = fopen("runtest.lock", "w");
    fputs($f, "lock");
    fclose($f);
  }
}

function compare_data($file, $data) {
  $result = true;
  $f = fopen($file, "r");

  for ($i = 0; $i < count($data); $i++) {
    $s = rtrim(fgets($f));
    if (!($s == $data[$i])) $result = false;
  }
  fclose($f);
  return ($result)?"OK":"FAIL";
}

function add_to_whitelist($machine, $ip) {
  check_file_exists("testwhite.csv", "machine,ip");
  $f = fopen("testwhite.csv", "a");
  fwrite($f, $machine.",".$ip.PHP_EOL);
  fclose($f);
}

function test_php($machine, $status, $page, $ip, $prepare_whitelist) {
  global $home;
  if ($prepare_whitelist) {
    add_to_whitelist($machine,$ip);
  }

  $get = http_build_query(array(
    'machine' => $machine,
    'status' => $status,
    'test' => 1,
    'ip' => $ip));
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $home.$page."?".$get);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_exec($ch);
  curl_close($ch);
}

function test_new_machine() {
  // Add new machine without needing reboot

  test_php("machine1", 0, "index.php", "1.2.3.4", TRUE);
  echo "test_new_machine (1): ".compare_data("test.csv", array("machine,status","machine1,0"))."\n";

  // Add new machine needing reboot
  test_php("machine2", 1, "index.php", "5.6.7.8", TRUE);
  echo "test_new_machine (2): ".compare_data("test.csv", array("machine,status","machine1,0","machine2,1"))."\n";

}

function test_add_day() {
  test_php("machine2", 1, "index.php", "5.6.7.8", FALSE);
  test_php("machine1", 0, "index.php", "1.2.3.4", FALSE);
  test_php("machine2", 1, "index.php", "5.6.7.8", FALSE);
  test_php("machine1", 1, "index.php", "1.2.3.4", FALSE);
  test_php("machine2", 1, "index.php", "5.6.7.8", FALSE);
  echo "test_add_day (1): ".compare_data("test.csv", array("machine,status", "machine1,1", "machine2,4"))."\n";
}

function test_reset_day() {
  test_php("machine2", 0, "index.php", "5.6.7.8", FALSE);
  echo "test_reset_day (1): ".compare_data("test.csv", array("machine,status", "machine1,1", "machine2,0"))."\n";
}

function test_vandalism() {
  test_php("machine3", 0, "index.php", "1.2.3.4", FALSE);
  test_php("machine4", 0, "index.php", "9.10.11.12", FALSE);
  test_php("machine1", 1, "index.php", "1.2.3.4", FALSE);
  echo "test_vandalism (1): ".compare_data("test.csv", array("machine,status", "machine1,2", "machine2,0"))."\n";
  echo "test_vandalism (2): ".compare_data("testgrey.csv", array("machine,ip", "machine3,1.2.3.4","machine4,9.10.11.12"));
}

//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

clean_up(true);
echo "\n";
$fl = fopen("runtest.lock", "r+");
if (flock($fl, LOCK_EX)) {
  check_file_exists("test.csv", "machine,status");
  test_new_machine();
  test_add_day();
  test_reset_day();
  test_vandalism();
  flock($fl, LOCK_UN);
}

fclose($fl);
clean_up(false);
echo "\n";

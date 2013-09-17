<?php

// config.php
// configuration parameters for app.

class Config {
  const APP_NAME          = 'LLAnim.us';
  
  const DB_HOST           = 'localhost';
  const DB_PORT           = 3306;
  const DB_USERNAME       = 'db_username';
  const DB_PASSWORD       = 'db_pass';
  public static $DB_NAMES = ['accessor_name' => 'db_name'];
  
  const ETI_USERNAME      = 'LLamaGuy';
  const ETI_PASSWORD      = 'hunter2';
  
  const FS_ROOT           = '/path/to/app/root';
  const URL_ROOT          = 'http://app.domain.here';
  
  const LOG_FILE          = "/path/to/app/error/log";
  const LOG_LEVEL         = PEAR_LOG_ERR;
  
  const SERVER_TZ         = "Europe/Paris";
  const OUTPUT_TZ         = "America/Chicago";
  
  const SPHINX_PORT       = 9312;
  
  const DEBUG_ON          = False;
}
?>
<?php

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
             || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$scriptName = rtrim($scriptName, '/');

$BASE_URL = $protocol . $host . $scriptName;

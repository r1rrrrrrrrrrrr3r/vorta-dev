<?php
// lib/config.php

// ambil protokol (http / https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
             || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// ambil domain + port (jika ada)
$host = $_SERVER['HTTP_HOST'];

// ambil folder project (misal: /prodtracker/public)
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$scriptName = rtrim($scriptName, '/');

// base url lengkap
$BASE_URL = $protocol . $host . $scriptName;

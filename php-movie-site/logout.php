<?php
require_once __DIR__ . '/config.php';
session_destroy();
header('Location: ' . SITE_URL . '/index.php');
exit;

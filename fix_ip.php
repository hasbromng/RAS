<?php
require 'config/config.php';
$pdo = getDbConnection();
$pdo->exec("UPDATE activity_logs SET ip_address = '127.0.0.1' WHERE ip_address = '::1'");
echo 'Done.';

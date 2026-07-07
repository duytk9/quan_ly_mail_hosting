<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '161.248.4.210';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

try {
    require '/opt/mailpanel/public/index.php';
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}


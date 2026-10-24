<?php
spl_autoload_register(function ($class) {
    // PHPMailer classes live under the PHPMailer\PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/PHPMailer-master/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not a PHPMailer class – let other autoloaders handle it
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
?>


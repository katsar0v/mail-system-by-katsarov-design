<?php
require 'vendor/autoload.php';
$classes = get_declared_classes();
foreach($classes as $class) { 
    if(strpos($class, 'Test') !== false && strpos($class, 'MSKD') !== false) echo $class . PHP_EOL; 
}
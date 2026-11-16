<?php
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache');
echo '@layer legacy, relink;' . "\n";
foreach (['legacy'=>'site.css','relink'=>'phase5.css'] as $layer=>$file) {
    echo '@import url("./' . $file . '?v=' . (int) filemtime(__DIR__ . '/' . $file) . '") layer(' . $layer . ');' . "\n";
}
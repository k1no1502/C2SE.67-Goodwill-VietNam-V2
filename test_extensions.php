<?php
echo "ZipArchive: " . (extension_loaded('zip') ? 'YES' : 'NO') . "\n";
echo "SimpleXML: " . (extension_loaded('simplexml') ? 'YES' : 'NO') . "\n";
echo "Class ZipArchive exists: " . (class_exists('ZipArchive') ? 'YES' : 'NO') . "\n";
echo "\nPHP Version: " . phpversion() . "\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";
?>

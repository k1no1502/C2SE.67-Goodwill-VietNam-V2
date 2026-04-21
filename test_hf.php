<?php
    require_once 'includes/moderation.php';
    $files = glob('c:/xampp/htdocs/uploads/donations/*');
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    foreach (array_slice($files, -10) as $f) {
        $r = checkNsfwImageHuggingFace($f);
        echo basename($f) . ': ' . ($r['violate'] ? 'NSFW' : 'SAFE') . ' | ' . $r['reason'] . "\n";
    }

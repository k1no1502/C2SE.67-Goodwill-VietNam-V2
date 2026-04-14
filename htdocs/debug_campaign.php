<?php
require 'config/database.php';

$campaign = Database::fetch('SELECT campaign_id, name, video_type, video_facebook, video_tiktok, video_youtube, video_file FROM campaigns WHERE campaign_id = 1');

echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>

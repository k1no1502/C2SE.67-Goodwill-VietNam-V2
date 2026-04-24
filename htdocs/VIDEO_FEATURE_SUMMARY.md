# Video Campaign Feature - Implementation Summary

## What's Been Added

### 1. Database Migration
**File:** `database/migrations/20260322_add_campaign_video.sql`
- Adds 3 new columns to `campaigns` table:
  - `video_type` (ENUM): Stores 'none', 'upload', or 'youtube'
  - `video_file` (VARCHAR): Path to uploaded video file
  - `video_youtube` (VARCHAR): YouTube video ID/link
- Creates index for better query performance

**Action Required:** Run this migration:
```sql
ALTER TABLE campaigns 
ADD COLUMN video_type ENUM('none', 'upload', 'youtube') DEFAULT 'none',
ADD COLUMN video_file VARCHAR(255),
ADD COLUMN video_youtube VARCHAR(255);

CREATE INDEX idx_campaigns_video ON campaigns(video_type);
```

### 2. Frontend Updates
**File:** `create-campaign.php` (Updated)

#### New Form Section Added:
- **Video Type Selection:** Three radio button options
  - ✗ Không có video (No video)
  - 📤 Upload video (Upload from computer)
  - ▶️ YouTube link (Embed from YouTube)

#### Upload Video Option:
- File input accepts: MP4, AVI, MOV, WebM, MKV, FLV
- Maximum file size: 500MB
- Files saved to: `uploads/campaigns/videos/`
- Client-side validation for file size

#### YouTube Option:
- Accepts YouTube URLs in multiple formats:
  - `https://www.youtube.com/watch?v=VIDEO_ID`
  - `https://youtu.be/VIDEO_ID`
  - `https://www.youtube.com/embed/VIDEO_ID`
- Automatically extracts video ID from URL
- Stores only the video ID for efficient retrieval

### 3. Backend Processing
**File:** `create-campaign.php` (Updated)

#### New PHP Code:
- Validates video type selection
- Handles video file uploads with error checking
- Extracts YouTube video ID from various URL formats
- Stores all video data in database during campaign creation
- Transaction support ensures data consistency

#### File Upload Function:
- Uses existing `uploadFile()` function with video format support
- Creates `uploads/campaigns/videos/` directory if needed
- Returns clear error messages if upload fails

### 4. JavaScript Features Added:
- Dynamic form section visibility based on video type selection
- Automatic clearing of unused input fields when switching types
- File size validation (alerts user if > 500MB)
- Smooth UI transitions between video options

## How to Use

### For Users - Creating Campaign with Video:

1. **No Video Option (Default):**
   - Leave as default, no video fields displayed

2. **Upload Video:**
   - Select "📤 Upload video" button
   - Click file input to choose video file
   - File must be < 500MB
   - Formats: MP4, AVI, MOV, WebM, MKV, FLV

3. **YouTube Video:**
   - Select "▶️ YouTube link" button
   - Paste YouTube URL in input field
   - Accepts any standard YouTube URL format
   - System extracts video ID automatically

## Database Schema

```php
// Display video in campaign detail
if ($campaign['video_type'] === 'upload' && $campaign['video_file']) {
    echo '<video width="100%" controls>';
    echo '<source src="uploads/campaigns/videos/' . $campaign['video_file'] . '" type="video/mp4">';
    echo 'Your browser does not support the video tag.';
    echo '</video>';
} elseif ($campaign['video_type'] === 'youtube' && $campaign['video_youtube']) {
    $youtubeId = $campaign['video_youtube'];
    echo '<iframe width="100%" height="600" src="https://www.youtube.com/embed/' . $youtubeId . '" frameborder="0" allowfullscreen></iframe>';
}
```

## Files Modified:
1. ✅ `create-campaign.php` - Form and backend processing
2. ✅ `database/migrations/20260322_add_campaign_video.sql` - Database schema

## Next Steps:
1. Run the migration SQL to add columns to database
2. Create `uploads/campaigns/videos/` directory (or it will be auto-created)
3. Update campaign-detail.php and campaigns.php to display videos
4. Update admin panels to show and manage videos

## Validation Rules:
- Video is optional (defaults to 'none')
- If upload selected: file must be provided
- If YouTube selected: link must be provided and valid
- File size max: 500MB
- Supported formats: MP4, AVI, MOV, WebM, MKV, FLV

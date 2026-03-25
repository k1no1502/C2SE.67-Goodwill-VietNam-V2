# FIX: Video Campaign Feature - Setup & Troubleshooting

## ⚠️ IMPORTANT: Run Migration First

**Before testing the feature, you MUST run the database migration:**

### Option 1: Using PHP Script (Recommended)
1. Open: `http://localhost/GW_VN%20Ver%20Final/run_migrations.php` in your browser
2. You should see:
   ```
   ✓ Executed: ALTER TABLE campaigns...
   ✓ Migration completed: 20260322_add_campaign_video.sql
   ```

### Option 2: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Paste and execute:
```sql
ALTER TABLE campaigns 
ADD COLUMN video_type ENUM('none', 'upload', 'youtube') DEFAULT 'none',
ADD COLUMN video_file VARCHAR(255),
ADD COLUMN video_youtube VARCHAR(255);

CREATE INDEX idx_campaigns_video ON campaigns(video_type);
```

### Option 3: Using MySQL Command Line
```bash
mysql -u your_user -p your_database < database/migrations/20260322_add_campaign_video.sql
```

## ✅ After Migration: Verify

Check your database has the new columns:
```sql
DESC campaigns;
```

You should see:
- `video_type` - ENUM('none','upload','youtube')
- `video_file` - VARCHAR(255)
- `video_youtube` - VARCHAR(255)

## 🎯 Changes Made (To Fix The Error)

### 1. Updated uploadFile() function
**File:** `includes/functions.php`

**Changes:**
- Added support for video MIME types (mp4, avi, mov, webm, mkv, flv)
- Increased file size limit from 5MB to 500MB for video uploads
- Improved error messages

### 2. Improved Video Handling in create-campaign.php
**File:** `create-campaign.php`

**Fixed:**
- Better validation for YouTube URL format extraction
- Proper error messages for missing video file or YouTube link
- Uses `$uploadResult['message']` instead of `$uploadResult['error']`
- Validates YouTube URL format before extraction

### 3. Updated Migration Runner
**File:** `run_migrations.php`

**Added:** `20260322_add_campaign_video.sql` to the migrations list

## 🧪 Test Now

1. **Run Migration** (see options above)
2. Go to: `http://localhost/GW_VN%20Ver%20Final/create-campaign.php`
3. Fill in basic info:
   - Tên chiến dịch: "Test Campaign"
   - Mô tả: "Test description"
   - Ngày bắt đầu: Today
   - Ngày kết thúc: Tomorrow
   - Mục tiêu: 100

4. **Test Option 1 - No Video:**
   - Keep "Không có video" selected
   - Submit form
   - ✅ Should work now

5. **Test Option 2 - Upload Video:**
   - Select "📤 Upload video"
   - Upload a small video file (MP4 recommended)
   - Submit form
   - ✅ Video should upload and save

6. **Test Option 3 - YouTube Link:**
   - Select "▶️ YouTube link"
   - Paste: `https://www.youtube.com/watch?v=dQw4w9WgXcQ`
   - Submit form
   - ✅ YouTube link should extract and save

## 🐛 Troubleshooting

### "There was an error creating the campaign"
**Possible causes:**
1. ❌ Migration not run → Run migration first
2. ❌ File upload directory problem → Create `uploads/campaigns/videos/` folder manually
3. ❌ Database connection → Check `config/database.php`
4. ❌ Video form mismatch → Check form input name is `video`, not `video_file`

### "File too large"
- Max video size is now **500MB** (was 5MB for images)
- Check file size is under 500MB

### "Invalid file type"
- Only supports: MP4, AVI, MOV, WebM, MKV, FLV
- Try converting video to MP4

### "Invalid YouTube link"
- Must be one of:
  - `https://www.youtube.com/watch?v=VIDEO_ID`
  - `https://youtu.be/VIDEO_ID`
  - `https://www.youtube.com/embed/VIDEO_ID`

### Check Errors in Logs
```php
// Add this temporarily to create-campaign.php to see exact error:
catch (Exception $e) {
    Database::rollback();
    error_log("Create campaign error: " . $e->getMessage());
    echo "DEBUG: " . $e->getMessage(); // Temporarily show error
    $error = 'Có lỗi xảy ra: ' . $e->getMessage(); // Show to user
}
```

## 📁 Required Directories

Make sure these exist (or will be auto-created):
```
uploads/
  └── campaigns/
       └── videos/  <- For uploaded videos
```

If upload fails, create them manually:
```bash
mkdir -p uploads/campaigns/videos
chmod 755 uploads/campaigns/videos
```

## ✨ Next Steps After Fix

Once working, you should also update:
1. **campaign-detail.php** - Display video when viewing campaign
2. **campaigns.php** - Show video thumbnail in listings
3. **admin/campaigns.php** - Manage videos in admin panel

See `VIDEO_FEATURE_SUMMARY.md` for display code examples.

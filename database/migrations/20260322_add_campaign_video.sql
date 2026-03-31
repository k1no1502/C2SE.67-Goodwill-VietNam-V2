-- Add video support to campaigns table
ALTER TABLE campaigns 
ADD COLUMN video_type ENUM('none', 'upload', 'youtube') DEFAULT 'none',
ADD COLUMN video_file VARCHAR(255),
ADD COLUMN video_youtube VARCHAR(255);

-- Create index for better performance
CREATE INDEX idx_campaigns_video ON campaigns(video_type);

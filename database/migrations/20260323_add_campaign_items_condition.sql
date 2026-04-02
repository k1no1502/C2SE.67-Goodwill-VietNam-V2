-- Add condition_status field to campaign_items table

ALTER TABLE campaign_items ADD COLUMN IF NOT EXISTS condition_status ENUM('new', 'like_new', 'good', 'fair', 'old') DEFAULT 'good' AFTER unit;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_campaign_items_condition ON campaign_items(condition_status);

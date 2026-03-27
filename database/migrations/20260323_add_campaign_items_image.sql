-- Add image field to campaign_items table

ALTER TABLE campaign_items ADD COLUMN IF NOT EXISTS image VARCHAR(255) NULL AFTER description;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_campaign_items_image ON campaign_items(campaign_id, image);

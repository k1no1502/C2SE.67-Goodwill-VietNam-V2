-- Add Estimated Value and Image Links to Donations Table

ALTER TABLE donations ADD COLUMN IF NOT EXISTS estimated_value DECIMAL(12, 2) DEFAULT 0 AFTER product_condition;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS image_links TEXT NULL AFTER estimated_value;

-- Create index for estimated_value for faster queries
CREATE INDEX IF NOT EXISTS idx_donations_estimated_value ON donations(estimated_value);

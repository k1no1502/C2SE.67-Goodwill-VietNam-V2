-- Add Location and Product Condition Fields to Donations Table

ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_city VARCHAR(100) NULL AFTER pickup_address;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_district VARCHAR(100) NULL AFTER pickup_city;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_ward VARCHAR(100) NULL AFTER pickup_district;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS product_condition ENUM('new', 'like_new', 'good', 'fair', 'old') DEFAULT 'good' AFTER condition_detail;

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_donations_location ON donations(pickup_city, pickup_district);
CREATE INDEX IF NOT EXISTS idx_donations_product_condition ON donations(product_condition);

-- Add Pickup Information and Details to Donations Table

ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_address TEXT NULL AFTER condition_status;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_date DATE NULL AFTER pickup_address;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS pickup_time TIME NULL AFTER pickup_date;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER pickup_time;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS address_status ENUM('residential', 'office', 'organization', 'school', 'hospital', 'other') NULL AFTER delivery_date;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(20) NULL AFTER address_status;
ALTER TABLE donations ADD COLUMN IF NOT EXISTS condition_detail TEXT NULL AFTER contact_phone;

-- Create index for faster query lookups
CREATE INDEX IF NOT EXISTS idx_donations_pickup_date ON donations(pickup_date);
CREATE INDEX IF NOT EXISTS idx_donations_contact_phone ON donations(contact_phone);

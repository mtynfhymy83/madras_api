<?php
// Add page_number to product_contents (for book-style navigation)
?>
ALTER TABLE product_contents ADD COLUMN IF NOT EXISTS page_number INT;
CREATE INDEX IF NOT EXISTS idx_contents_product_page ON product_contents(product_id, page_number) WHERE page_number IS NOT NULL;

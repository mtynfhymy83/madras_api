-- Display info in products.attributes (denormalized cache for book details API).
-- No new column: we use the existing JSONB "attributes" and the key "display_info".
-- Run this manually or via: php run_display_info_backfill.php

COMMENT ON COLUMN products.attributes IS 'JSONB. Optional key display_info for book details API: authors, translators (arrays), category, publisher (objects or null). Cache only; no schema change.';

UPDATE products p
SET attributes = COALESCE(p.attributes, '{}'::jsonb) || jsonb_build_object(
  'display_info', jsonb_build_object(
    'authors', COALESCE(
      (SELECT to_jsonb(array_agg(per.full_name ORDER BY per.full_name)) FROM product_contributors pc JOIN persons per ON per.id = pc.person_id WHERE pc.product_id = p.id AND pc.role = 'author'),
      '[]'::jsonb
    ),
    'translators', COALESCE(
      (SELECT to_jsonb(array_agg(per.full_name ORDER BY per.full_name)) FROM product_contributors pc JOIN persons per ON per.id = pc.person_id WHERE pc.product_id = p.id AND pc.role = 'translator'),
      '[]'::jsonb
    ),
    'category', (SELECT jsonb_build_object('id', c.id, 'title', c.title, 'path', c.full_path) FROM categories c WHERE c.id = p.category_id),
    'publisher', (SELECT jsonb_build_object('id', pub.id, 'title', pub.title) FROM publishers pub WHERE pub.id = p.publisher_id)
  )
)
WHERE p.type = 'book' AND p.deleted_at IS NULL;

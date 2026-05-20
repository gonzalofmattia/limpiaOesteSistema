ALTER TABLE ml_listings
    ADD COLUMN ml_pictures_count INT UNSIGNED NULL DEFAULT NULL AFTER ml_thumbnail;

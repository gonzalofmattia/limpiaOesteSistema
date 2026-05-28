CREATE TABLE product_store_categories (
    product_id INT NOT NULL,
    store_category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, store_category_id),
    CONSTRAINT fk_psc_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_psc_store_category FOREIGN KEY (store_category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

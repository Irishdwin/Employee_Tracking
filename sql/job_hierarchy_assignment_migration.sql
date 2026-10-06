-- Additive MariaDB migration. Existing records remain unclassified until explicitly mapped.
ALTER TABLE joborder ADD COLUMN IF NOT EXISTS classification_id INT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS sub_classification_id INT UNSIGNED NULL;
ALTER TABLE job_order_details ADD COLUMN IF NOT EXISTS classification_id INT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS sub_classification_id INT UNSIGNED NULL;
ALTER TABLE applicants ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS classification_id INT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS sub_classification_id INT UNSIGNED NULL;
-- Existing hierarchy IDs are unsigned and use InnoDB.
ALTER TABLE joborder ADD CONSTRAINT fk_jo_classification FOREIGN KEY (classification_id) REFERENCES job_classifications(id),
 ADD CONSTRAINT fk_jo_subclassification FOREIGN KEY (sub_classification_id) REFERENCES job_sub_classifications(id);
ALTER TABLE job_order_details ADD CONSTRAINT fk_jod_classification FOREIGN KEY (classification_id) REFERENCES job_classifications(id),
 ADD CONSTRAINT fk_jod_subclassification FOREIGN KEY (sub_classification_id) REFERENCES job_sub_classifications(id);
ALTER TABLE applicants ADD CONSTRAINT fk_app_category FOREIGN KEY (category_id) REFERENCES job_categories(id),
 ADD CONSTRAINT fk_app_classification FOREIGN KEY (classification_id) REFERENCES job_classifications(id),
 ADD CONSTRAINT fk_app_subclassification FOREIGN KEY (sub_classification_id) REFERENCES job_sub_classifications(id);


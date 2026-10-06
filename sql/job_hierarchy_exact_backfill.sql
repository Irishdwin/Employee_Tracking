-- Only exact, category-scoped Admin dictionary names. No fuzzy title inference.
UPDATE joborder jo JOIN job_classifications k ON k.category_id=jo.category_id
 JOIN job_sub_classifications s ON s.classification_id=k.id
 SET jo.classification_id=k.id,jo.sub_classification_id=s.id
 WHERE jo.classification_id IS NULL AND jo.sub_classification_id IS NULL
 AND LOWER(TRIM(jo.Position))=LOWER(TRIM(s.sub_classification_name))
 AND k.is_active=1 AND s.is_active=1
 AND (SELECT COUNT(*) FROM job_sub_classifications sx JOIN job_classifications kx ON kx.id=sx.classification_id WHERE kx.category_id=jo.category_id AND LOWER(TRIM(sx.sub_classification_name))=LOWER(TRIM(jo.Position)))=1;
UPDATE job_order_details d JOIN job_classifications k ON k.category_id=d.category_id
 JOIN job_sub_classifications s ON s.classification_id=k.id
 SET d.classification_id=k.id,d.sub_classification_id=s.id
 WHERE d.classification_id IS NULL AND d.sub_classification_id IS NULL
 AND LOWER(TRIM(d.position_title))=LOWER(TRIM(s.sub_classification_name))
 AND k.is_active=1 AND s.is_active=1
 AND (SELECT COUNT(*) FROM job_sub_classifications sx JOIN job_classifications kx ON kx.id=sx.classification_id WHERE kx.category_id=d.category_id AND LOWER(TRIM(sx.sub_classification_name))=LOWER(TRIM(d.position_title)))=1;
UPDATE applicants a JOIN job_categories c ON c.category_name=a.JobCategory
 JOIN job_classifications k ON k.category_id=c.id AND k.classification_name=a.PreferredClassification
 JOIN job_sub_classifications s ON s.classification_id=k.id AND s.sub_classification_name=a.PreferredSubClassification
 SET a.category_id=c.id,a.classification_id=k.id,a.sub_classification_id=s.id
 WHERE a.category_id IS NULL AND a.classification_id IS NULL AND a.sub_classification_id IS NULL;

-- Retain applicant sex in the employee record; existing values are preserved.
ALTER TABLE employee ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL;
UPDATE employee e JOIN users u ON LOWER(TRIM(u.Email))=LOWER(TRIM(e.Email)) JOIN applicants a ON a.UserID=u.UsersID
SET e.gender=a.Gender WHERE e.gender IS NULL AND a.Gender IS NOT NULL AND a.Gender<>'';

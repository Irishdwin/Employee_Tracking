-- Optional: canonical workflow status names for job_applications.StatusID
-- Safe to run multiple times; inserts only when missing.

INSERT INTO status (Name)
SELECT 'Scheduled for Interview' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM status WHERE Name = 'Scheduled for Interview');

INSERT INTO status (Name)
SELECT 'Endorsed to Client' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM status WHERE Name = 'Endorsed to Client');

INSERT INTO status (Name)
SELECT 'Hired' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM status WHERE Name = 'Hired');

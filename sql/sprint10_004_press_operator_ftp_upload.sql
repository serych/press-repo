USE press;

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'press_operator'
  AND p.code = 'ftp.upload'
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

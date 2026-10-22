<?php
function ensureAuditLogTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            audit_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NULL,
            entity_id INT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_audit_logs_user_id (user_id),
            KEY idx_audit_logs_created_at (created_at)
        ) ENGINE=InnoDB"
    );
}

function logAuditEvent(PDO $pdo, int $userId, string $action, string $details = '', ?string $entityType = null, ?int $entityId = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent, NOW())"
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':details' => $details,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);
}

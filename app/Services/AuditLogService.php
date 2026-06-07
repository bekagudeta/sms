<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an admin action
     */
    public static function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $changes = null,
        ?string $description = null
    ): AuditLog {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'description' => $description,
        ]);
    }

    /**
     * Log a login attempt
     */
    public static function logLogin(string $email, bool $success = true): AuditLog
    {
        return AuditLog::create([
            'user_email' => $email,
            'action' => $success ? 'login' : 'failed_login',
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'description' => $success ? 'User logged in successfully' : 'Failed login attempt',
        ]);
    }

    /**
     * Log a data modification by admin
     */
    public static function logDataModification(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $changes = null;
        if ($oldValues || $newValues) {
            $changes = [
                'old' => $oldValues,
                'new' => $newValues,
            ];
        }

        return self::log(
            action: $action,
            modelType: $entityType,
            modelId: $entityId,
            changes: $changes,
            description: "Admin {$action} on {$entityType} ID {$entityId}"
        );
    }
}

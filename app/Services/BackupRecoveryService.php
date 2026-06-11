<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Backup and Recovery Service for Data Integrity
 * 
 * Features:
 * - Create database backups
 * - Point-in-time recovery
 * - Restore from audit logs
 * - Export/import safety copies
 * 
 * @category Services
 * @package App\Services
 */
class BackupRecoveryService
{
    private string $backupDisk = 'backups';
    private string $auditLogsTable = 'audit_logs';

    /**
     * Create a full database backup
     *
     * @param string $name Optional backup name
     * @return array Backup information
     */
    public function createFullBackup(string $name = null): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupName = $name ?? "backup_{$timestamp}";

        try {
            // Export students with their types
            $students = Student::select([
                'id', 'student_id', 'first_name', 'last_name', 'email', 'phone',
                'department_id', 'academic_section', 'student_type', 'level', 'status'
            ])->get();

            $backupData = [
                'timestamp' => now(),
                'version' => config('app.version'),
                'total_students' => $students->count(),
                'by_type' => [
                    'regular' => $students->where('student_type', 'regular')->count(),
                    'weekend' => $students->where('student_type', 'weekend')->count(),
                ],
                'students' => $students->toArray(),
                'metadata' => [
                    'created_at' => now()->toIso8601String(),
                    'description' => "Full backup including all student type data",
                ]
            ];

            // Save backup
            $backupPath = "full/{$backupName}.json";
            Storage::disk($this->backupDisk)->put($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));

            return [
                'success' => true,
                'name' => $backupName,
                'path' => $backupPath,
                'size' => strlen(json_encode($backupData)),
                'created_at' => now(),
                'total_records' => $students->count(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore from a backup
     *
     * @param string $backupName The backup name to restore from
     * @param bool $dryRun If true, only validate without restoring
     * @return array Restoration result
     */
    public function restoreFromBackup(string $backupName, bool $dryRun = true): array
    {
        try {
            $backupPath = "full/{$backupName}.json";

            if (!Storage::disk($this->backupDisk)->exists($backupPath)) {
                return [
                    'success' => false,
                    'error' => "Backup '{$backupName}' not found",
                ];
            }

            $backupData = json_decode(
                Storage::disk($this->backupDisk)->get($backupPath),
                true
            );

            if ($dryRun) {
                return [
                    'success' => true,
                    'dry_run' => true,
                    'message' => "Dry run complete - would restore {$backupData['total_students']} students",
                    'created_at' => $backupData['metadata']['created_at'],
                    'total_records' => $backupData['total_students'],
                ];
            }

            // Execute restoration
            DB::beginTransaction();

            try {
                $restored = 0;
                foreach ($backupData['students'] as $studentData) {
                    $student = Student::find($studentData['id']);

                    if (!$student) {
                        $student = new Student($studentData);
                    } else {
                        $student->update($studentData);
                    }

                    $restored++;
                }

                DB::commit();

                return [
                    'success' => true,
                    'dry_run' => false,
                    'message' => "Restored {$restored} students from backup",
                    'restored_count' => $restored,
                    'created_at' => $backupData['metadata']['created_at'],
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get point-in-time state of students (from audit logs)
     *
     * @param Carbon $timestamp The point in time to recover to
     * @return array State at that time
     */
    public function recoverToPointInTime(Carbon $timestamp): array
    {
        try {
            // Get all audit logs up to the timestamp
            $auditLogs = AuditLog::where('created_at', '<=', $timestamp)
                ->where('model', 'Student')
                ->orderBy('created_at', 'desc')
                ->get();

            $studentStates = [];

            foreach ($auditLogs as $log) {
                $studentId = $log->model_id;

                if (!isset($studentStates[$studentId])) {
                    // Get the student's current state and reverse changes
                    $student = Student::find($studentId);
                    if ($student) {
                        $studentStates[$studentId] = $student->toArray();
                    }
                }

                // Reverse this change
                if ($log->action === 'updated' && $log->changes) {
                    $changes = json_decode($log->changes, true);
                    foreach ($changes as $field => $change) {
                        $studentStates[$studentId][$field] = $change['old'] ?? null;
                    }
                }
            }

            return [
                'success' => true,
                'recovered_at' => $timestamp,
                'current_time' => now(),
                'total_records' => count($studentStates),
                'students' => array_values($studentStates),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List available backups
     *
     * @return array List of backups
     */
    public function listBackups(): array
    {
        try {
            $files = Storage::disk($this->backupDisk)->files('full');

            $backups = array_map(function ($file) {
                $name = basename($file, '.json');
                $path = Storage::disk($this->backupDisk)->path($file);
                $size = Storage::disk($this->backupDisk)->size($file);
                $lastModified = Storage::disk($this->backupDisk)->lastModified($file);

                return [
                    'name' => $name,
                    'file' => $file,
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'created_at' => Carbon::createFromTimestamp($lastModified),
                ];
            }, $files);

            // Sort by creation time (newest first)
            usort($backups, function ($a, $b) {
                return $b['created_at'] <=> $a['created_at'];
            });

            return [
                'success' => true,
                'total' => count($backups),
                'backups' => $backups,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete an old backup
     *
     * @param string $backupName
     * @return array Result
     */
    public function deleteBackup(string $backupName): array
    {
        try {
            $backupPath = "full/{$backupName}.json";

            if (!Storage::disk($this->backupDisk)->exists($backupPath)) {
                return [
                    'success' => false,
                    'error' => "Backup not found",
                ];
            }

            Storage::disk($this->backupDisk)->delete($backupPath);

            return [
                'success' => true,
                'message' => "Backup deleted successfully",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get audit trail for a specific student
     *
     * @param int $studentId
     * @return array Audit history
     */
    public function getStudentAuditTrail(int $studentId): array
    {
        try {
            $logs = AuditLog::where('model', 'Student')
                ->where('model_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'changes' => $log->changes ? json_decode($log->changes, true) : null,
                        'user_id' => $log->user_id,
                        'created_at' => $log->created_at,
                    ];
                });

            return [
                'success' => true,
                'student_id' => $studentId,
                'total_entries' => $logs->count(),
                'audit_trail' => $logs->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1 << (10 * $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

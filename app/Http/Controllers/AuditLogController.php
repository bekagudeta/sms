<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs with search and filter
     */
    public function index(Request $request)
    {
        // Verify user is admin (protected by middleware, but double-check)
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        // Get filter parameters
        $search = $request->get('search', '');
        $actionFilter = $request->get('action', '');
        $userFilter = $request->get('user_email', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $ipFilter = $request->get('ip_address', '');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Build query
        $query = AuditLog::query();

        // Search by description, action, user_email, IP
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Filter by action
        if ($actionFilter && $actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        // Filter by user email
        if ($userFilter && $userFilter !== 'all') {
            $query->where('user_email', $userFilter);
        }

        // Filter by date range
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Filter by IP
        if ($ipFilter) {
            $query->where('ip_address', $ipFilter);
        }

        // Apply sorting
        if ($sortBy && in_array($sortBy, ['created_at', 'action', 'user_email', 'ip_address'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results
        $logs = $query->paginate(50)->appends($request->query());

        // Get unique values for filter dropdowns
        $actions = AuditLog::distinct('action')->pluck('action')->toArray();
        $users = AuditLog::distinct('user_email')->whereNotNull('user_email')->pluck('user_email')->toArray();
        $ips = AuditLog::distinct('ip_address')->pluck('ip_address')->toArray();

        // Format logs for display
        $formattedLogs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user_email' => $log->user_email ?? 'Unknown',
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $this->getUserAgentSummary($log->user_agent),
                'changes' => $log->changes,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_at_raw' => $log->created_at,
                'is_suspicious' => $this->isSuspiciousActivity($log),
            ];
        });

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $formattedLogs,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
            ],
            'filters' => [
                'search' => $search,
                'action' => $actionFilter,
                'user' => $userFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'ip' => $ipFilter,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'filter_options' => [
                'actions' => $actions,
                'users' => $users,
                'ips' => $ips,
            ],
            'stats' => [
                'total_logs' => AuditLog::count(),
                'todays_logs' => AuditLog::whereDate('created_at', today())->count(),
                'failed_logins' => AuditLog::where('action', 'failed_login')->count(),
                'suspicious_ips_count' => count($this->getSuspiciousIPs()),
                'suspicious_ips' => $this->getSuspiciousIPs(),
                'suspicious_ips_threshold' => 5,
            ],
        ]);
    }

    /**
     * Export audit logs to CSV
     */
    public function export(Request $request)
    {
        // Verify user is admin (protected by middleware, but double-check)
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $query = AuditLog::query();

        // Apply same filters as index
        $search = $request->get('search', '');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $actionFilter = $request->get('action', '');
        if ($actionFilter && $actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        $dateFrom = $request->get('date_from', '');
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        $dateTo = $request->get('date_to', '');
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Filter by user email
        $userFilter = $request->get('user_email', '');
        if ($userFilter) {
            $query->where('user_email', $userFilter);
        }

        // Filter by IP address
        $ipFilter = $request->get('ip_address', '');
        if ($ipFilter) {
            $query->where('ip_address', $ipFilter);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        if ($sortBy && in_array($sortBy, ['created_at', 'action', 'user_email', 'ip_address'])) {
            $logs = $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc')->get();
        } else {
            $logs = $query->orderBy('created_at', 'desc')->get();
        }

        // Create CSV
        $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';
        $handle = fopen('php://memory', 'r+');

        // Write header
        fputcsv($handle, ['Date/Time', 'User Email', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address', 'User Agent', 'Changes']);

        // Write data
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user_email ?? 'Unknown',
                $log->action,
                $log->model_type,
                $log->model_id,
                $log->description,
                $log->ip_address,
                $log->user_agent,
                json_encode($log->changes),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Get summary of user agent
     */
    private function getUserAgentSummary(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown';
        }

        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            return 'Edge';
        } else {
            return 'Other';
        }
    }

    /**
     * Check if activity is suspicious
     */
    private function isSuspiciousActivity(AuditLog $log): bool
    {
        // Failed login attempts
        if ($log->action === 'failed_login') {
            return true;
        }

        // Bulk deletions
        if ($log->action === 'delete' && str_contains($log->description ?? '', 'bulk')) {
            return true;
        }

        // Grade modifications (if applicable)
        if ($log->action === 'update' && str_contains($log->model_type ?? '', 'Grade')) {
            return true;
        }

        // Activity at unusual hours (after 10 PM or before 6 AM)
        if ($log->created_at) {
            $hour = $log->created_at->hour;
            if ($hour >= 22 || $hour <= 6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get suspicious IP addresses
     */
    private function getSuspiciousIPs(): array
    {
        // Find IPs with multiple failed login attempts
        $suspiciousIPs = AuditLog::where('action', 'failed_login')
            ->groupBy('ip_address')
            ->selectRaw('ip_address, COUNT(*) as count')
            ->having('count', '>', 5)
            ->pluck('ip_address')
            ->toArray();

        return $suspiciousIPs;
    }
}

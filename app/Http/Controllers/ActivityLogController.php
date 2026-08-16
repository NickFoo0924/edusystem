<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BUILD PRIORITY ITEM 5 -- the audit trail, filterable and exportable
 * (EduSystem.md 1A).
 *
 * Guarded by can:activitylog.view route middleware. The rows themselves are
 * never editable from anywhere in the application: ActivityLog::record() is the
 * only writer and there is no update or destroy action here, which is what
 * makes the trail worth trusting.
 */
class ActivityLogController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * How many rows one export may contain, so a stray request cannot try to
     * stream the entire table.
     */
    private const EXPORT_LIMIT = 10000;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $logs = ActivityLog::with('user')
            ->filter($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('activity_logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            // Only actions that actually occur are offered, so the dropdown
            // never lists a filter that would return nothing.
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    /**
     * Stream the filtered trail as CSV.
     *
     * Streamed and chunked rather than built in memory: an audit trail only
     * grows, and this must not fall over once it is large.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'activity-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Timestamp', 'Actor', 'Role', 'Action', 'Target', 'IP address', 'User agent']);

            ActivityLog::with('user')
                ->filter($filters)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::EXPORT_LIMIT)
                ->chunk(500, function ($chunk) use ($handle) {
                    foreach ($chunk as $log) {
                        fputcsv($handle, array_map([$this, 'csvSafe'], [
                            $log->id,
                            $log->created_at?->format('Y-m-d H:i:s'),
                            $log->user?->name ?? 'system',
                            $log->user?->role ?? '',
                            $log->action,
                            $log->targetLabel() ?? '',
                            $log->ip_address,
                            $log->user_agent,
                        ]));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * Neutralise spreadsheet formula injection.
     *
     * A user agent is attacker-controlled text. Excel and Google Sheets execute
     * any cell beginning =, +, - or @, so those values are prefixed with an
     * apostrophe and land as literal text instead of a formula.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}

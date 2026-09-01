<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
// We will need export classes later, defined in App\Exports namespace

class ReportService
{
    /**
     * Generate report based on parameters.
     */
    public function generate(array $params)
    {
        $startDate = Carbon::parse($params['period_start']);
        $endDate = Carbon::parse($params['period_end']);
        $format = $params['format'] ?? 'pdf';
        $contentTypes = $params['content'] ?? [];

        $data = $this->collectData($startDate, $endDate, $contentTypes);
        $data['period'] = [
            'start' => $startDate->format('d F Y'),
            'end' => $endDate->format('d F Y'),
            'range' => $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d'),
        ];
        $data['style'] = $params['style'] ?? 'detailed';

        if ($format === 'pdf') {
            return $this->generatePdf($data);
        } elseif ($format === 'xlsx') {
            return $this->generateExcel($data);
        } elseif ($format === 'csv') {
            return $this->generateCsv($data);
        }

        throw new \InvalidArgumentException("Format laporan tidak didukung: $format");
    }

    protected function collectData(Carbon $start, Carbon $end, array $contentTypes)
    {
        $data = [];

        if (in_array('upload_stats', $contentTypes)) {
            $data['upload_stats'] = $this->getUploadStatistics($start, $end);
        }

        if (in_array('qc_metrics', $contentTypes)) {
            $data['qc_metrics'] = $this->getQCPerformanceMetrics($start, $end);
        }

        if (in_array('doc_status', $contentTypes)) {
            $data['doc_status'] = $this->getDocumentStatusOverview($start, $end);
        }

        if (in_array('user_activity', $contentTypes)) {
            $data['user_activity'] = $this->getUserActivitySummary($start, $end);
        }

        if (in_array('trend_analysis', $contentTypes)) {
            $data['trend_analysis'] = $this->getTrendAnalysis($start, $end);
        }

        // Executive summary is always included or handled separately
        $data['executive_summary'] = $this->getExecutiveSummary($start, $end);

        return $data;
    }

    public function getUploadStatistics(Carbon $start, Carbon $end)
    {
        return Document::whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getQCPerformanceMetrics(Carbon $start, Carbon $end)
    {
        $totalVerified = AuditLog::whereBetween('created_at', [$start, $end])
            ->whereIn('action', [AuditAction::VERIFY_DOCUMENT->value, AuditAction::REJECT_DOCUMENT->value])
            ->count();

        $approved = AuditLog::whereBetween('created_at', [$start, $end])
            ->where('action', AuditAction::VERIFY_DOCUMENT->value)
            ->count();

        $approvalRate = $totalVerified > 0 ? ($approved / $totalVerified) * 100 : 0;

        // Average verification time logic would require more complex queries matching upload time with verification time
        // For now, we return placeholder or simplified metric

        return [
            'total_verified' => $totalVerified,
            'approval_rate' => round($approvalRate, 2),
            // 'avg_verification_time' => '2.5 hours', // Placeholder
        ];
    }

    public function getQcPerformanceReport(Carbon $start, Carbon $end, int $perPage = 10, int $page = 1)
    {
        $usersQuery = User::whereIn('role', [\App\Enums\UserRole::QC->value, \App\Enums\UserRole::MANAGER->value])
            ->orderBy('name', 'asc');

        $paginated = $usersQuery->paginate($perPage, ['*'], 'page', $page);

        $staffData = collect($paginated->items())->map(function ($staff) use ($start, $end) {
            $verifiedCount = Document::where('verified_by', $staff->id)
                ->where('status', DocumentStatus::VERIFIED->value)
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $rejectedCount = Document::where('verified_by', $staff->id)
                ->where('status', DocumentStatus::REJECTED->value)
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $totalHandled = $verifiedCount + $rejectedCount;
            $accuracyRate = $totalHandled > 0 ? round(($verifiedCount / $totalHandled) * 100, 1) : 100.0;
            $isOnline = $staff->last_seen_at && $staff->last_seen_at->diffInMinutes(now()) <= 5;

            // Calculate actual average verification time from created_at to verified_at
            $verifiedDocs = Document::where('verified_by', $staff->id)
                ->whereNotNull('verified_at')
                ->whereBetween('updated_at', [$start, $end])
                ->get(['created_at', 'verified_at']);

            if ($verifiedDocs->isNotEmpty()) {
                $totalMinutes = $verifiedDocs->reduce(function ($carry, $doc) {
                    return $carry + ($doc->created_at && $doc->verified_at ? $doc->created_at->diffInMinutes($doc->verified_at) : 0);
                }, 0);

                $avgHours = round(($totalMinutes / $verifiedDocs->count()) / 60.0, 1);
                $avgTime = $avgHours >= 24
                    ? round($avgHours / 24, 1) . ' Hari'
                    : $avgHours . ' Jam';
            } else {
                $avgTime = '-';
            }

            return [
                'id' => $staff->id,
                'staff' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role instanceof \App\Enums\UserRole ? $staff->role->label() : $staff->role,
                'terverifikasi' => $verifiedCount,
                'ditolak' => $rejectedCount,
                'false_rejections_count' => 0,
                'avg_time' => $avgTime,
                'accuracy_rate' => $accuracyRate . '%',
                'is_online' => (bool) $isOnline,
                'last_seen_at' => $staff->last_seen_at ? $staff->last_seen_at->toIso8601String() : null,
            ];
        });

        return [
            'status' => 'success',
            'data' => $staffData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function getDocumentStatusOverview(Carbon $start, Carbon $end)
    {
        return Document::whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status, // Enum value
                    'label' => DocumentStatus::tryFrom($item->status)?->label() ?? $item->status,
                    'count' => $item->count,
                ];
            });
    }

    public function getUserActivitySummary(Carbon $start, Carbon $end)
    {
        return AuditLog::whereBetween('created_at', [$start, $end])
            ->with('user')
            ->select('user_id', DB::raw('count(*) as activity_count'))
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user_name' => $item->user->name ?? 'Unknown',
                    'activity_count' => $item->activity_count,
                ];
            });
    }

    public function getTrendAnalysis(Carbon $start, Carbon $end)
    {
        // Compare with previous period
        $previousStart = $start->copy()->subDays($start->diffInDays($end));
        $previousEnd = $start->copy();

        $currentCount = Document::whereBetween('created_at', [$start, $end])->count();
        $previousCount = Document::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        $growth = $previousCount > 0 ? (($currentCount - $previousCount) / $previousCount) * 100 : 0;

        return [
            'current_period_total' => $currentCount,
            'previous_period_total' => $previousCount,
            'growth_percentage' => round($growth, 2),
        ];
    }

    private function getExecutiveSummary(Carbon $start, Carbon $end)
    {
        $totalDocs = Document::whereBetween('created_at', [$start, $end])->count();
        $pendingDocs = Document::whereBetween('created_at', [$start, $end])->where('status', DocumentStatus::PENDING->value)->count();

        return [
            'total_documents' => $totalDocs,
            'pending_documents' => $pendingDocs,
            // Add more key metrics here
        ];
    }

    protected function generatePdf(array $data)
    {
        // Implementation for dompdf
        // We need a blade view for this
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('reports.generic', ['data' => $data]);
        return $pdf->download('report.pdf');
    }

    protected function generateExcel(array $data)
    {
        // TODO: Implement Excel export using Maatwebsite\Excel
        throw new \BadMethodCallException('Format laporan XLSX belum diimplementasi.');
    }

    protected function generateCsv(array $data)
    {
        // TODO: Implement CSV export
        throw new \BadMethodCallException('Format laporan CSV belum diimplementasi.');
    }
}

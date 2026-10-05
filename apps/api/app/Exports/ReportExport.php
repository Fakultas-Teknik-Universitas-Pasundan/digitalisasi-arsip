<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

class ReportExport implements FromArray
{
    public function __construct(protected array $data) {}

    /**
     * Tabular rows shared by every non-PDF format (XLSX via FromArray,
     * CSV via ReportService). One block per section that
     * ReportService::collectData() actually produced.
     */
    public function rows(): array
    {
        $data = $this->data;
        $rows = [];

        $rows[] = ['Ringkasan Eksekutif'];
        $rows[] = ['Metrik', 'Nilai'];
        $rows[] = ['Total Dokumen', $data['executive_summary']['total_documents'] ?? 0];
        $rows[] = ['Dokumen Menunggu Verifikasi', $data['executive_summary']['pending_documents'] ?? 0];
        $rows[] = [];

        if (! empty($data['upload_stats'])) {
            $rows[] = ['Statistik Unggahan'];
            $rows[] = ['Tanggal', 'Jumlah'];
            foreach ($this->items($data['upload_stats']) as $item) {
                $rows[] = [$item['date'] ?? null, $item['count'] ?? null];
            }
            $rows[] = [];
        }

        if (! empty($data['qc_metrics'])) {
            $rows[] = ['Metrik QC'];
            $rows[] = ['Metrik', 'Nilai'];
            $rows[] = ['Total Verifikasi', $data['qc_metrics']['total_verified'] ?? 0];
            $rows[] = ['Tingkat Persetujuan (%)', $data['qc_metrics']['approval_rate'] ?? 0];
            $rows[] = [];
        }

        if (! empty($data['doc_status'])) {
            $rows[] = ['Ringkasan Status Dokumen'];
            $rows[] = ['Status', 'Label', 'Jumlah'];
            foreach ($this->items($data['doc_status']) as $item) {
                $rows[] = [$item['status'] ?? null, $item['label'] ?? null, $item['count'] ?? null];
            }
            $rows[] = [];
        }

        if (! empty($data['user_activity'])) {
            $rows[] = ['Aktivitas Pengguna (10 Teratas)'];
            $rows[] = ['Pengguna', 'Jumlah Aktivitas'];
            foreach ($this->items($data['user_activity']) as $item) {
                $rows[] = [$item['user_name'] ?? null, $item['activity_count'] ?? null];
            }
            $rows[] = [];
        }

        if (! empty($data['trend_analysis'])) {
            $rows[] = ['Analisis Tren'];
            $rows[] = ['Metrik', 'Nilai'];
            $rows[] = ['Total Periode Ini', $data['trend_analysis']['current_period_total'] ?? 0];
            $rows[] = ['Total Periode Sebelumnya', $data['trend_analysis']['previous_period_total'] ?? 0];
            $rows[] = ['Pertumbuhan (%)', $data['trend_analysis']['growth_percentage'] ?? 0];
        }

        return $rows;
    }

    public function array(): array
    {
        return $this->rows();
    }

    protected function items(Collection|array $items): array
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? $item : (array) $item)
            ->all();
    }
}

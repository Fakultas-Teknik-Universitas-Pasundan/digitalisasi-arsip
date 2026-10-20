"use client";

import React, { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { dashboardQueries } from "@/features/dashboard/queries/dashboard.queries";
import {
  PageDescription,
  PageHeader,
  PageTitle,
} from "@/shared/components/page-header";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@repo/ui/card";
import { Button } from "@repo/ui/button";
import { Input } from "@repo/ui/input";
import { Badge } from "@repo/ui/badge";
import { Skeleton } from "@repo/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@repo/ui/table";
import {
  Search,
  Download,
  History,
  CheckCircle2,
  RotateCcw,
  Calendar,
} from "lucide-react";
import { toast } from "sonner";
import type { DocumentItem } from "@/features/dashboard/types/dashboard.types";

export default function DownloadHistoryPage() {
  const [searchTerm, setSearchTerm] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");

  // Directly fetch verified documents from API
  const docsQuery = useQuery(
    dashboardQueries.documents({ status: "terverifikasi", per_page: 50 }),
  );
  const isLoading = docsQuery.isLoading;

  const rawDocs: DocumentItem[] = docsQuery.data?.data || [];

  const filteredLogs = useMemo(() => {
    return rawDocs.filter((item) => {
      if (searchTerm.trim()) {
        const term = searchTerm.trim().toLowerCase();
        const matchesFile = (item.title || item.file_name || "")
          .toLowerCase()
          .includes(term);
        const matchesNpm = (item.npm || item.student_number || "")
          .toLowerCase()
          .includes(term);
        const matchesProdi = (item.prodi || "").toLowerCase().includes(term);
        if (!matchesFile && !matchesNpm && !matchesProdi) return false;
      }

      return true;
    });
  }, [rawDocs, searchTerm]);

  const handleReDownload = (item: DocumentItem) => {
    toast.success(`Mengunduh ulang "${item.title || item.file_name}"`);
    window.open(`/api/documents/${item.id}/download`, "_blank");
  };

  const handleResetFilters = () => {
    setSearchTerm("");
    setStartDate("");
    setEndDate("");
  };

  return (
    <div className="flex flex-col gap-6 w-full pb-10">
      <PageHeader>
        <PageTitle>Riwayat Unduhan Dokumen</PageTitle>
        <PageDescription>
          Catatan log aktivitas pengunduhan arsip digital terverifikasi yang
          dilakukan oleh Pegawai SBAP
        </PageDescription>
      </PageHeader>

      {/* Filter Card */}
      <Card className="border border-border/60 bg-card shadow-sm">
        <CardHeader className="p-5 pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <CardTitle className="text-base font-semibold text-foreground flex items-center gap-2">
              <History className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
              Filter Log Unduhan
            </CardTitle>
            {(searchTerm || startDate || endDate) && (
              <Button
                variant="ghost"
                size="sm"
                onClick={handleResetFilters}
                className="h-8 text-xs text-muted-foreground hover:text-foreground gap-1"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                Reset Filter
              </Button>
            )}
          </div>
        </CardHeader>

        <CardContent className="p-5 pt-2">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {/* Search Input */}
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-2.5 text-muted-foreground" />
              <Input
                placeholder="Cari file, NPM, atau prodi..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>

            {/* Tanggal Dari */}
            <div className="flex items-center gap-2">
              <Calendar className="w-4 h-4 text-muted-foreground shrink-0" />
              <Input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            {/* Tanggal Sampai */}
            <div className="flex items-center gap-2">
              <Calendar className="w-4 h-4 text-muted-foreground shrink-0" />
              <Input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Log Table Card */}
      <Card className="border border-border/60 bg-card shadow-sm">
        <CardHeader className="p-5 pb-3">
          <CardTitle className="text-lg font-semibold text-foreground">
            Daftar Log Unduhan ({filteredLogs.length})
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground mt-0.5">
            Daftar lengkap arsip terverifikasi yang pernah diunduh
          </CardDescription>
        </CardHeader>

        <CardContent className="p-5 pt-0">
          {isLoading ? (
            <div className="space-y-3 pt-2">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          ) : filteredLogs.length > 0 ? (
            <div className="rounded-xl border border-border/60 overflow-hidden">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Nama File Dokumen
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      NPM Mahasiswa
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Program Studi
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Waktu Unduh
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Status
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground text-right">
                      Aksi
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredLogs.map((item) => (
                    <TableRow
                      key={item.id}
                      className="hover:bg-muted/30 transition-colors"
                    >
                      <TableCell className="font-medium text-xs text-foreground">
                        {item.title || item.file_name}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {item.npm || item.student_number || "-"}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {item.prodi || "Teknik Informatika"}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {new Date(item.created_at).toLocaleDateString("id-ID", {
                          day: "numeric",
                          month: "short",
                          year: "numeric",
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant="secondary"
                          className="bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-200 text-[10px] gap-1"
                        >
                          <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                          Terverifikasi
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleReDownload(item)}
                          className="h-8 text-xs gap-1 text-[#F54A00] hover:text-[#d64100] hover:bg-orange-50 dark:hover:bg-orange-950/20"
                        >
                          <Download className="w-3.5 h-3.5" />
                          Download Ulang
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          ) : (
            <div className="h-44 flex flex-col items-center justify-center text-muted-foreground text-xs rounded-xl border border-dashed p-6">
              <span>Belum ada riwayat unduhan dokumen yang sesuai.</span>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

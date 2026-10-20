"use client";

import React, { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { dashboardQueries } from "@/features/dashboard/queries/dashboard.queries";
import {
  PageDescription,
  PageHeader,
  PageTitle,
} from "@/shared/components/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@repo/ui/card";
import { Button } from "@repo/ui/button";
import { Input } from "@repo/ui/input";
import { Badge } from "@repo/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@repo/ui/select";
import { Skeleton } from "@repo/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@repo/ui/table";
import { Checkbox } from "@repo/ui/checkbox";
import { ViewArchiveModal } from "@/features/archives/components/ui/view-archive-modal";
import { Search, Download, FileCheck, RefreshCw } from "lucide-react";
import { toast } from "sonner";
import type { DocumentItem } from "@/features/dashboard/types/dashboard.types";

const PRODI_OPTIONS = [
  "Teknik Informatika",
  "Teknologi Pangan",
  "Teknik Industri",
  "Teknik Lingkungan",
  "Teknik Mesin",
  "Perencanaan Wilayah & Kota",
];

const DOC_TYPES = [
  { value: "transkrip", label: "Transkrip Nilai" },
  { value: "ijazah", label: "Ijazah" },
  { value: "berita_acara_sidang", label: "Berita Acara Sidang" },
  { value: "nilai", label: "Rekap Nilai" },
];

export default function AvailableArchivesPage() {
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedProdi, setSelectedProdi] = useState<string>("all");
  const [selectedDocType, setSelectedDocType] = useState<string>("all");
  const [selectedDocIds, setSelectedDocIds] = useState<number[]>([]);

  // Directly fetch verified documents from API
  const docsQuery = useQuery(
    dashboardQueries.documents({ status: "terverifikasi", per_page: 50 }),
  );
  const isLoading = docsQuery.isLoading;

  const rawDocs: DocumentItem[] = docsQuery.data?.data || [];

  const filteredDocs = useMemo(() => {
    return rawDocs.filter((doc) => {
      if (searchTerm.trim()) {
        const titleStr = doc.title || doc.file_name || "";
        if (!titleStr.toLowerCase().includes(searchTerm.trim().toLowerCase())) {
          return false;
        }
      }

      if (selectedDocType !== "all") {
        const categoryStr =
          doc.category || doc.document_type || doc.file_name || "";
        if (
          !categoryStr.toLowerCase().includes(selectedDocType.toLowerCase())
        ) {
          return false;
        }
      }

      return true;
    });
  }, [rawDocs, searchTerm, selectedDocType]);

  const toggleSelectAll = () => {
    if (selectedDocIds.length === filteredDocs.length) {
      setSelectedDocIds([]);
    } else {
      setSelectedDocIds(filteredDocs.map((d) => d.id));
    }
  };

  const toggleSelectDoc = (id: number) => {
    setSelectedDocIds((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
    );
  };

  const handleDownloadSingle = (doc: DocumentItem) => {
    toast.success(`Mengunduh arsip "${doc.title || doc.file_name}"`);
    window.open(`/api/documents/${doc.id}/download`, "_blank");
  };

  const handleBulkDownload = () => {
    if (selectedDocIds.length === 0) return;
    toast.success(
      `Mengunduh ${selectedDocIds.length} arsip terverifikasi (ZIP Batch)...`,
    );
  };

  return (
    <div className="flex flex-col gap-6 w-full pb-10">
      <PageHeader>
        <PageTitle>Katalog Arsip Tersedia (Terverifikasi)</PageTitle>
        <PageDescription>
          Seluruh dokumen resmi terverifikasi yang siap diunduh dan dicetak oleh
          Pegawai SBAP
        </PageDescription>
      </PageHeader>

      {/* Filter & Action Card */}
      <Card className="border border-border/60 bg-card shadow-sm">
        <CardHeader className="p-5 pb-3">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-3 flex-1">
              <div className="relative w-full sm:w-64">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-muted-foreground" />
                <Input
                  placeholder="Cari nama dokumen..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-9 h-9 text-xs"
                />
              </div>

              <Select
                value={selectedProdi}
                onValueChange={(val) => val && setSelectedProdi(val)}
              >
                <SelectTrigger className="h-9 text-xs w-full sm:w-48">
                  <SelectValue placeholder="Pilih Program Studi" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all" className="text-xs">
                    Semua Program Studi
                  </SelectItem>
                  {PRODI_OPTIONS.map((prodi) => (
                    <SelectItem key={prodi} value={prodi} className="text-xs">
                      {prodi}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select
                value={selectedDocType}
                onValueChange={(val) => val && setSelectedDocType(val)}
              >
                <SelectTrigger className="h-9 text-xs w-full sm:w-44">
                  <SelectValue placeholder="Jenis Dokumen" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all" className="text-xs">
                    Semua Jenis
                  </SelectItem>
                  {DOC_TYPES.map((type) => (
                    <SelectItem
                      key={type.value}
                      value={type.value}
                      className="text-xs"
                    >
                      {type.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => docsQuery.refetch()}
                className="h-9 text-xs gap-1.5"
              >
                <RefreshCw className="w-3.5 h-3.5" />
                Refresh
              </Button>
              {selectedDocIds.length > 0 && (
                <Button
                  onClick={handleBulkDownload}
                  className="h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
                >
                  <Download className="w-3.5 h-3.5" />
                  Unduh Batch ({selectedDocIds.length} ZIP)
                </Button>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="p-5 pt-0">
          {isLoading ? (
            <div className="space-y-3 pt-2">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          ) : filteredDocs.length > 0 ? (
            <div className="rounded-xl border border-border/60 overflow-hidden">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead className="w-12 text-center">
                      <Checkbox
                        checked={
                          filteredDocs.length > 0 &&
                          selectedDocIds.length === filteredDocs.length
                        }
                        onCheckedChange={toggleSelectAll}
                      />
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Nama Dokumen
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Jenis Dokumen
                    </TableHead>
                    <TableHead className="font-semibold text-xs text-foreground">
                      Program Studi
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
                  {filteredDocs.map((doc) => {
                    const isChecked = selectedDocIds.includes(doc.id);
                    const categoryName =
                      doc.category || doc.document_type || "Dokumen";

                    return (
                      <TableRow
                        key={doc.id}
                        className="hover:bg-muted/30 transition-colors"
                      >
                        <TableCell className="text-center">
                          <Checkbox
                            checked={isChecked}
                            onCheckedChange={() => toggleSelectDoc(doc.id)}
                          />
                        </TableCell>
                        <TableCell className="font-medium text-xs text-foreground">
                          {doc.title || doc.file_name}
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground capitalize">
                          {categoryName}
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {doc.prodi ||
                            (selectedProdi !== "all" ? selectedProdi : "-")}
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant="secondary"
                            className="bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-200 text-[10px]"
                          >
                            <FileCheck className="w-3 h-3 mr-1" />
                            TERVERIFIKASI
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-2">
                            <ViewArchiveModal id={doc.id} />
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleDownloadSingle(doc)}
                              className="h-8 text-xs gap-1"
                            >
                              <Download className="w-3.5 h-3.5" />
                              Download
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>
          ) : (
            <div className="h-44 flex flex-col items-center justify-center text-muted-foreground text-xs rounded-xl border border-dashed p-6">
              <span>
                Belum ada dokumen terverifikasi yang tersedia saat ini.
              </span>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

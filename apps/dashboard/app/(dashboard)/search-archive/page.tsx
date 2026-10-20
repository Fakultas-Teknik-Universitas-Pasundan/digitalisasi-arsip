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
import { Search, Download, RotateCcw, FileCheck, Sparkles } from "lucide-react";
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

const SEMESTER_OPTIONS = [
  "2025/2026 Ganjil",
  "2025/2026 Genap",
  "2024/2025 Ganjil",
  "2024/2025 Genap",
];

export default function SearchArchivePage() {
  const [npmSearch, setNpmSearch] = useState("");
  const [nameSearch, setNameSearch] = useState("");
  const [selectedProdi, setSelectedProdi] = useState<string>("all");
  const [selectedDocType, setSelectedDocType] = useState<string>("all");
  const [selectedSemester, setSelectedSemester] = useState<string>("all");

  const [selectedDocIds, setSelectedDocIds] = useState<number[]>([]);

  const docsQuery = useQuery(
    dashboardQueries.documents({ status: "terverifikasi", per_page: 50 }),
  );
  const isLoading = docsQuery.isLoading;

  const rawDocs: DocumentItem[] = docsQuery.data?.data || [];

  const filteredDocs = useMemo(() => {
    return rawDocs.filter((doc) => {
      if (npmSearch.trim()) {
        const docNpm = doc.npm || doc.student_number || "";
        if (!docNpm.toLowerCase().includes(npmSearch.trim().toLowerCase())) {
          return false;
        }
      }

      if (nameSearch.trim()) {
        const titleStr = doc.title || doc.file_name || "";
        if (!titleStr.toLowerCase().includes(nameSearch.trim().toLowerCase())) {
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
  }, [rawDocs, npmSearch, nameSearch, selectedDocType]);

  const handleResetFilters = () => {
    setNpmSearch("");
    setNameSearch("");
    setSelectedProdi("all");
    setSelectedDocType("all");
    setSelectedSemester("all");
    setSelectedDocIds([]);
  };

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
        <PageTitle>Pencarian Arsip Digital</PageTitle>
        <PageDescription>
          Pencarian cepat dan akurat dokumen terverifikasi berdasarkan NPM,
          Nama, Prodi, Jenis Dokumen, &amp; Tahun Ajaran
        </PageDescription>
      </PageHeader>

      {/* 1. Multi-Criteria Filter Card */}
      <Card className="border border-border/60 bg-card shadow-sm">
        <CardHeader className="p-5 pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-base font-semibold text-foreground flex items-center gap-2">
              <Sparkles className="w-4 h-4 text-purple-600 dark:text-purple-400" />
              Form Filter Multi-Kriteria
            </CardTitle>
            <Button
              variant="ghost"
              size="sm"
              onClick={handleResetFilters}
              className="h-8 text-xs text-muted-foreground hover:text-foreground gap-1"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              Reset Filter
            </Button>
          </div>
        </CardHeader>

        <CardContent className="p-5 pt-2 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* Input NPM */}
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-foreground">
                NPM / NIM Mahasiswa
              </label>
              <Input
                placeholder="Contoh: 203040001"
                value={npmSearch}
                onChange={(e) => setNpmSearch(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            {/* Input Nama Mahasiswa */}
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-foreground">
                Nama Mahasiswa / Judul
              </label>
              <Input
                placeholder="Contoh: Ahmad Subagja"
                value={nameSearch}
                onChange={(e) => setNameSearch(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            {/* Select Program Studi */}
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-foreground">
                Program Studi
              </label>
              <Select
                value={selectedProdi}
                onValueChange={(val) => val && setSelectedProdi(val)}
              >
                <SelectTrigger className="h-9 text-xs">
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
            </div>

            {/* Select Jenis Dokumen */}
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-foreground">
                Jenis Dokumen
              </label>
              <Select
                value={selectedDocType}
                onValueChange={(val) => val && setSelectedDocType(val)}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="Pilih Jenis Dokumen" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all" className="text-xs">
                    Semua Jenis Dokumen
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

            {/* Select Semester / Tahun Ajaran */}
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-foreground">
                Tahun Ajaran / Semester
              </label>
              <Select
                value={selectedSemester}
                onValueChange={(val) => val && setSelectedSemester(val)}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="Pilih Semester" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all" className="text-xs">
                    Semua Semester
                  </SelectItem>
                  {SEMESTER_OPTIONS.map((sem) => (
                    <SelectItem key={sem} value={sem} className="text-xs">
                      {sem}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Search Trigger */}
            <div className="flex items-end">
              <Button className="w-full h-9 text-xs bg-[#F54A00] hover:bg-[#d64100] text-white font-medium gap-2">
                <Search className="w-4 h-4" />
                Cari Arsip
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* 2. Results Table */}
      <Card className="border border-border/60 bg-card shadow-sm">
        <CardHeader className="p-5 pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle className="text-lg font-semibold text-foreground">
                Hasil Pencarian Arsip ({filteredDocs.length})
              </CardTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Daftar arsip terverifikasi yang cocok dengan kriteria pencarian
              </p>
            </div>

            {selectedDocIds.length > 0 && (
              <Button
                onClick={handleBulkDownload}
                className="h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
              >
                <Download className="w-3.5 h-3.5" />
                Unduh Selected ({selectedDocIds.length} ZIP)
              </Button>
            )}
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
                      Jenis
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
            <div className="h-48 flex flex-col items-center justify-center text-muted-foreground text-xs rounded-xl border border-dashed p-6">
              <span>
                Tidak ditemukan arsip terverifikasi yang sesuai dengan kriteria
                pencarian Anda.
              </span>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

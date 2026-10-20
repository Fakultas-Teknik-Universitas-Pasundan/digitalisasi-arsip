"use client";

import React, { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  Search,
  SlidersHorizontal,
  Clock,
  FileText,
  Sparkles,
  RotateCcw,
  Trash2,
  X,
  Eye,
  ChevronDown,
  ChevronUp,
  User,
  BookOpen,
  Calendar,
  TrendingUp,
  FileStack,
} from "lucide-react";
import { Dialog, DialogContent, DialogTitle } from "@repo/ui/dialog";
import { Button } from "@repo/ui/button";
import { Input } from "@repo/ui/input";
import { Badge } from "@repo/ui/badge";
import { Skeleton } from "@repo/ui/skeleton";
import { dashboardQueries } from "@/features/dashboard/queries/dashboard.queries";
import { ViewArchiveModal } from "@/features/archives/components/ui/view-archive-modal";

interface SearchCommandDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface AdvancedFilters {
  nim?: string;
  student_name?: string;
  mata_kuliah?: string;
  tahun_akademik?: string;
  semester?: string;
  document_type?: string;
  status?: string;
  date_from?: string;
  date_to?: string;
}

const RECENT_SEARCHES_KEY = "digital_archive_recent_searches";

const QUICK_FILTERS = [
  {
    label: "Menunggu Verifikasi",
    type: "status",
    value: "menunggu_verifikasi",
  },
  { label: "Terverifikasi", type: "status", value: "terverifikasi" },
  { label: "Ditolak", type: "status", value: "tidak_terverifikasi" },
  { label: "Nilai", type: "document_type", value: "nilai" },
  { label: "Transkrip", type: "document_type", value: "transkrip" },
  { label: "Ijazah", type: "document_type", value: "ijazah" },
  {
    label: "Berita Acara Sidang",
    type: "document_type",
    value: "berita_acara_sidang",
  },
  { label: "Semester Genap", type: "semester", value: "genap" },
  { label: "Semester Ganjil", type: "semester", value: "ganjil" },
];

export function SearchCommandDialog({
  open,
  onOpenChange,
}: SearchCommandDialogProps) {
  const [searchTerm, setSearchTerm] = useState("");
  const [activeSearch, setActiveSearch] = useState("");
  const [selectedStatus, setSelectedStatus] = useState<string | undefined>(
    undefined,
  );
  const [selectedDocType, setSelectedDocType] = useState<string | undefined>(
    undefined,
  );

  // Filter panel visibility toggle
  const [showFilterPanel, setShowFilterPanel] = useState(false);

  // Advanced filter form fields
  const [filterNim, setFilterNim] = useState("");
  const [filterNamaMahasiswa, setFilterNamaMahasiswa] = useState("");
  const [filterMataKuliah, setFilterMataKuliah] = useState("");
  const [filterTahunAkademik, setFilterTahunAkademik] = useState("");
  const [filterSemester, setFilterSemester] = useState("");
  const [filterJenisDokumen, setFilterJenisDokumen] = useState("");
  const [filterStatusVerifikasi, setFilterStatusVerifikasi] = useState("");
  const [filterTanggalDari, setFilterTanggalDari] = useState("");
  const [filterTanggalSampai, setFilterTanggalSampai] = useState("");

  // Applied filters (used for query)
  const [appliedFilters, setAppliedFilters] = useState<AdvancedFilters>({});

  const [viewDocId, setViewDocId] = useState<number | null>(null);
  const [viewModalOpen, setViewModalOpen] = useState(false);

  const [recentSearches, setRecentSearches] = useState<string[]>(() => {
    if (typeof window === "undefined") return [];
    try {
      const saved = localStorage.getItem(RECENT_SEARCHES_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) return parsed;
      }
    } catch {
      // ignore
    }
    return [];
  });

  const saveRecentSearch = React.useCallback((query: string) => {
    const trimmed = query.trim();
    if (!trimmed || trimmed.length < 2) return;
    setRecentSearches((prev) => {
      const updated = [
        trimmed,
        ...prev.filter((item) => item !== trimmed),
      ].slice(0, 5);
      try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(updated));
      } catch (error) {
        if (process.env.NODE_ENV !== "production") {
          console.warn("Failed to save recent search to localStorage:", error);
        }
      }
      return updated;
    });
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      setActiveSearch(searchTerm);
      if (searchTerm.trim().length >= 2) {
        saveRecentSearch(searchTerm);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchTerm, saveRecentSearch]);

  const removeRecentSearch = (query: string, e: React.MouseEvent) => {
    e.stopPropagation();
    const updated = recentSearches.filter((item) => item !== query);
    setRecentSearches(updated);
    try {
      localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(updated));
    } catch (error) {
      if (process.env.NODE_ENV !== "production") {
        console.warn(
          "Failed to remove recent search from localStorage:",
          error,
        );
      }
    }
  };

  const clearAllRecentSearches = () => {
    setRecentSearches([]);
    try {
      localStorage.removeItem(RECENT_SEARCHES_KEY);
    } catch (error) {
      if (process.env.NODE_ENV !== "production") {
        console.warn(
          "Failed to clear recent searches from localStorage:",
          error,
        );
      }
    }
  };

  const queryParams: Record<string, string | number> = {
    per_page: 8,
  };
  if (activeSearch.trim()) queryParams.search = activeSearch.trim();
  if (selectedStatus) queryParams.status = selectedStatus;
  if (selectedDocType) queryParams.document_type = selectedDocType;

  if (appliedFilters.nim) queryParams.nim = appliedFilters.nim;
  if (appliedFilters.student_name)
    queryParams.student_name = appliedFilters.student_name;
  if (appliedFilters.mata_kuliah)
    queryParams.mata_kuliah = appliedFilters.mata_kuliah;
  if (appliedFilters.tahun_akademik)
    queryParams.tahun_akademik = appliedFilters.tahun_akademik;
  if (appliedFilters.semester) queryParams.semester = appliedFilters.semester;
  if (appliedFilters.document_type && !selectedDocType)
    queryParams.document_type = appliedFilters.document_type;
  if (appliedFilters.status && !selectedStatus)
    queryParams.status = appliedFilters.status;
  if (appliedFilters.date_from)
    queryParams.date_from = appliedFilters.date_from;
  if (appliedFilters.date_to) queryParams.date_to = appliedFilters.date_to;

  const activeAppliedCount =
    Object.values(appliedFilters).filter(Boolean).length;
  const isQueryActive =
    !!activeSearch.trim() ||
    !!selectedStatus ||
    !!selectedDocType ||
    activeAppliedCount > 0;

  const searchResultsQuery = useQuery({
    ...dashboardQueries.documents(queryParams),
    enabled: isQueryActive,
  });

  const handleSelectRecent = (term: string) => {
    setSearchTerm(term);
    setActiveSearch(term);
    saveRecentSearch(term);
  };

  const handleTogglePill = (filter: {
    label: string;
    type: string;
    value: string;
  }) => {
    if (filter.type === "status") {
      setSelectedStatus((prev) =>
        prev === filter.value ? undefined : filter.value,
      );
    } else if (filter.type === "document_type") {
      setSelectedDocType((prev) =>
        prev === filter.value ? undefined : filter.value,
      );
    } else if (filter.type === "semester") {
      setAppliedFilters((prev) => ({
        ...prev,
        semester: prev.semester === filter.value ? undefined : filter.value,
      }));
    }
  };

  const handleApplyFilters = () => {
    setAppliedFilters({
      nim: filterNim || undefined,
      student_name: filterNamaMahasiswa || undefined,
      mata_kuliah: filterMataKuliah || undefined,
      tahun_akademik: filterTahunAkademik || undefined,
      semester: filterSemester || undefined,
      document_type: filterJenisDokumen || undefined,
      status: filterStatusVerifikasi || undefined,
      date_from: filterTanggalDari || undefined,
      date_to: filterTanggalSampai || undefined,
    });
    setShowFilterPanel(false);
  };

  const handleResetAllFilters = () => {
    setFilterNim("");
    setFilterNamaMahasiswa("");
    setFilterMataKuliah("");
    setFilterTahunAkademik("");
    setFilterSemester("");
    setFilterJenisDokumen("");
    setFilterStatusVerifikasi("");
    setFilterTanggalDari("");
    setFilterTanggalSampai("");
    setAppliedFilters({});
    setSelectedStatus(undefined);
    setSelectedDocType(undefined);
    setSearchTerm("");
    setActiveSearch("");
  };

  const handleResetFilters = () => {
    handleResetAllFilters();
  };

  const handleOpenDocView = (docId: number, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    if (searchTerm.trim()) {
      saveRecentSearch(searchTerm);
    }
    // Tutup search dialog dahulu, lalu buka preview setelah delay animasi close
    onOpenChange(false);
    setTimeout(() => {
      setViewDocId(docId);
      setViewModalOpen(true);
    }, 150);
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-5xl md:max-w-5xl sm:max-w-4xl w-[90vw] md:w-[75vw] lg:w-[67vw] max-w-[1150px] p-0 overflow-hidden border-border/60 shadow-2xl rounded-2xl">
          {/* HEADER */}
          <div className="bg-gradient-to-r from-[#F54A00] to-[#D94200] p-6 text-white relative">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-white/20 backdrop-blur shrink-0">
                  <Search className="w-5 h-5 text-white" />
                </div>
                <div>
                  <DialogTitle className="text-xl font-bold text-white tracking-tight">
                    Pencarian Lanjutan Dokumen
                  </DialogTitle>
                  <p className="text-xs text-white/80 mt-0.5">
                    Live searching berdasarkan NIM, Nama Mahasiswa, Mata Kuliah,
                    Tipe Dokumen, atau Status
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div className="p-6 space-y-6">
            {/* SEARCH BAR SECTION */}
            <div className="space-y-3">
              <div className="relative flex items-center">
                <Search className="w-5 h-5 absolute left-4 text-muted-foreground pointer-events-none" />
                <Input
                  placeholder="Ketik NIM, Nama, atau Mata Kuliah (pencarian langsung)..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      setActiveSearch(searchTerm);
                      if (searchTerm.trim()) saveRecentSearch(searchTerm);
                    }
                  }}
                  className="pl-12 pr-44 h-12 text-sm rounded-xl border-border/60 focus-visible:ring-[#F54A00]"
                  autoFocus
                />
                <div className="absolute right-3 flex items-center gap-2">
                  {searchResultsQuery.isFetching && (
                    <Badge
                      variant="secondary"
                      className="bg-[#F54A00]/10 text-[#F54A00] text-[10px] animate-pulse"
                    >
                      Mencari...
                    </Badge>
                  )}
                  {isQueryActive && (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={handleResetFilters}
                      className="h-8 text-xs px-2 text-muted-foreground hover:text-foreground"
                      title="Reset Filter"
                    >
                      <RotateCcw className="w-3.5 h-3.5 mr-1" />
                      Reset
                    </Button>
                  )}
                  <Button
                    size="sm"
                    onClick={() => setShowFilterPanel((prev) => !prev)}
                    className="h-8 text-xs gap-1.5 rounded-lg font-medium bg-[#F54A00] text-white hover:bg-[#d64100]"
                  >
                    <SlidersHorizontal className="w-3.5 h-3.5" />
                    Filter
                    {activeAppliedCount > 0 && (
                      <span className="ml-0.5 bg-white/20 text-[10px] px-1 rounded-full">
                        {activeAppliedCount}
                      </span>
                    )}
                    {showFilterPanel ? (
                      <ChevronUp className="w-3 h-3" />
                    ) : (
                      <ChevronDown className="w-3 h-3" />
                    )}
                  </Button>
                  <Button
                    size="sm"
                    onClick={() => {
                      setActiveSearch(searchTerm);
                      if (searchTerm.trim()) saveRecentSearch(searchTerm);
                    }}
                    className="h-8 text-xs px-3 rounded-lg bg-[#F54A00] text-white hover:bg-[#d64100] font-medium"
                  >
                    Cari
                  </Button>
                </div>
              </div>

              {/* QUICK FILTER PILLS */}
              <div className="flex flex-wrap items-center gap-2 pt-1">
                <span className="text-xs text-muted-foreground font-medium mr-1 flex items-center gap-1">
                  <SlidersHorizontal className="w-3 h-3" />
                  Filter Cepat:
                </span>
                {QUICK_FILTERS.map((pill, idx) => {
                  const isActive =
                    (pill.type === "status" && selectedStatus === pill.value) ||
                    (pill.type === "document_type" &&
                      selectedDocType === pill.value) ||
                    (pill.type === "semester" &&
                      appliedFilters.semester === pill.value);

                  return (
                    <Button
                      key={idx}
                      variant={isActive ? "default" : "outline"}
                      size="sm"
                      onClick={() => handleTogglePill(pill)}
                      className={
                        isActive
                          ? "h-7 text-xs rounded-lg bg-[#F54A00] text-white font-medium shadow-xs"
                          : "h-7 text-xs rounded-lg bg-muted/40 hover:bg-muted font-normal text-muted-foreground hover:text-foreground"
                      }
                    >
                      {pill.label}
                    </Button>
                  );
                })}
              </div>

              {/* ACTIVE FILTER CHIPS */}
              {activeAppliedCount > 0 && (
                <div className="flex flex-wrap gap-1.5 pt-1">
                  <span className="text-[10px] text-muted-foreground self-center">
                    Filter aktif:
                  </span>
                  {appliedFilters.nim && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      NIM: {appliedFilters.nim}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({ ...p, nim: undefined }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.student_name && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Nama: {appliedFilters.student_name}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            student_name: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.mata_kuliah && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      MK: {appliedFilters.mata_kuliah}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            mata_kuliah: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.tahun_akademik && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Tahun: {appliedFilters.tahun_akademik}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            tahun_akademik: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.semester && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Semester: {appliedFilters.semester}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            semester: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.document_type && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Jenis: {appliedFilters.document_type}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            document_type: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.status && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Status: {appliedFilters.status}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            status: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.date_from && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Dari: {appliedFilters.date_from}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            date_from: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                  {appliedFilters.date_to && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] gap-1 pl-2 pr-1 py-0.5 bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800"
                    >
                      Sampai: {appliedFilters.date_to}
                      <button
                        onClick={() =>
                          setAppliedFilters((p) => ({
                            ...p,
                            date_to: undefined,
                          }))
                        }
                        className="hover:text-rose-600"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </Badge>
                  )}
                </div>
              )}
            </div>

            {/* COLLAPSIBLE FILTER PANEL */}
            {showFilterPanel && (
              <div className="border-t border-border/60 bg-[#FAFAFA] dark:bg-muted/30 p-5 rounded-xl space-y-4">
                {/* Row 1: Text Inputs */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  {/* NIM */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <User className="w-3.5 h-3.5 text-[#F54A00]" />
                      NIM
                    </label>
                    <Input
                      placeholder="Contoh: 123456789"
                      value={filterNim}
                      onChange={(e) => setFilterNim(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === "Enter") handleApplyFilters();
                      }}
                      className="h-9 text-xs rounded-lg border-border/60"
                    />
                  </div>
                  {/* Nama Mahasiswa */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <User className="w-3.5 h-3.5 text-[#F54A00]" />
                      Nama Mahasiswa
                    </label>
                    <Input
                      placeholder="Contoh: Ahmad Fauzi"
                      value={filterNamaMahasiswa}
                      onChange={(e) => setFilterNamaMahasiswa(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === "Enter") handleApplyFilters();
                      }}
                      className="h-9 text-xs rounded-lg border-border/60"
                    />
                  </div>
                  {/* Mata Kuliah */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <BookOpen className="w-3.5 h-3.5 text-[#F54A00]" />
                      Mata Kuliah
                    </label>
                    <Input
                      placeholder="Contoh: Kalkulus 1"
                      value={filterMataKuliah}
                      onChange={(e) => setFilterMataKuliah(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === "Enter") handleApplyFilters();
                      }}
                      className="h-9 text-xs rounded-lg border-border/60"
                    />
                  </div>
                </div>

                {/* Row 2: Dropdowns */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  {/* Tahun Akademik */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <Calendar className="w-3.5 h-3.5 text-[#F54A00]" />
                      Tahun Akademik
                    </label>
                    <select
                      value={filterTahunAkademik}
                      onChange={(e) => setFilterTahunAkademik(e.target.value)}
                      className="w-full h-9 text-xs rounded-lg border border-border/60 bg-background px-3 text-foreground focus:outline-none focus:ring-1 focus:ring-[#F54A00]"
                    >
                      <option value="">Semua Tahun</option>
                      {Array.from({ length: 12 }, (_, i) => 2015 + i).map(
                        (year) => (
                          <option key={year} value={`${year}/${year + 1}`}>
                            {year}/{year + 1}
                          </option>
                        ),
                      )}
                    </select>
                  </div>
                  {/* Semester */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <Calendar className="w-3.5 h-3.5 text-[#F54A00]" />
                      Semester
                    </label>
                    <select
                      value={filterSemester}
                      onChange={(e) => setFilterSemester(e.target.value)}
                      className="w-full h-9 text-xs rounded-lg border border-border/60 bg-background px-3 text-foreground focus:outline-none focus:ring-1 focus:ring-[#F54A00]"
                    >
                      <option value="">Semua Semester</option>
                      <option value="ganjil">Semester Ganjil</option>
                      <option value="genap">Semester Genap</option>
                    </select>
                  </div>
                  {/* Jenis Dokumen */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <FileStack className="w-3.5 h-3.5 text-[#F54A00]" />
                      Jenis Dokumen
                    </label>
                    <select
                      value={filterJenisDokumen}
                      onChange={(e) => setFilterJenisDokumen(e.target.value)}
                      className="w-full h-9 text-xs rounded-lg border border-border/60 bg-background px-3 text-foreground focus:outline-none focus:ring-1 focus:ring-[#F54A00]"
                    >
                      <option value="">Semua Jenis</option>
                      <option value="nilai">Nilai</option>
                      <option value="transkrip">Transkrip</option>
                      <option value="ijazah">Ijazah</option>
                      <option value="berita_acara_sidang">
                        Berita Acara Sidang
                      </option>
                    </select>
                  </div>
                </div>

                {/* Row 3: Status & Dates */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  {/* Status Verifikasi */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <TrendingUp className="w-3.5 h-3.5 text-[#F54A00]" />
                      Status Verifikasi
                    </label>
                    <select
                      value={filterStatusVerifikasi}
                      onChange={(e) =>
                        setFilterStatusVerifikasi(e.target.value)
                      }
                      className="w-full h-9 text-xs rounded-lg border border-border/60 bg-background px-3 text-foreground focus:outline-none focus:ring-1 focus:ring-[#F54A00]"
                    >
                      <option value="">Semua Status</option>
                      <option value="menunggu_verifikasi">
                        Menunggu Verifikasi
                      </option>
                      <option value="terverifikasi">Terverifikasi</option>
                      <option value="tidak_terverifikasi">Ditolak</option>
                    </select>
                  </div>
                  {/* Tanggal Upload Dari */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <Calendar className="w-3.5 h-3.5 text-[#F54A00]" />
                      Tanggal Upload (Dari)
                    </label>
                    <Input
                      type="date"
                      value={filterTanggalDari}
                      onChange={(e) => setFilterTanggalDari(e.target.value)}
                      className="h-9 text-xs rounded-lg border-border/60"
                    />
                  </div>
                  {/* Tanggal Upload Sampai */}
                  <div className="space-y-1.5">
                    <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                      <Calendar className="w-3.5 h-3.5 text-[#F54A00]" />
                      Tanggal Upload (Sampai)
                    </label>
                    <Input
                      type="date"
                      value={filterTanggalSampai}
                      onChange={(e) => setFilterTanggalSampai(e.target.value)}
                      className="h-9 text-xs rounded-lg border-border/60"
                    />
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex items-center gap-2 pt-1">
                  <Button
                    onClick={handleApplyFilters}
                    className="h-9 px-5 text-xs font-medium bg-[#F54A00] hover:bg-[#d64100] text-white rounded-lg"
                  >
                    Terapkan Filter
                  </Button>
                  <Button
                    variant="outline"
                    onClick={handleResetAllFilters}
                    className="h-9 px-4 text-xs font-medium rounded-lg border-border/60"
                  >
                    Reset
                  </Button>
                </div>
              </div>
            )}

            {/* RESULTS OR RECENT SEARCHES */}
            {isQueryActive ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5 text-[#F54A00]" />
                    Hasil Pencarian Real-Time (
                    {searchResultsQuery.data?.data?.length ?? 0})
                  </span>
                  <span className="text-[11px] text-muted-foreground">
                    Klik item untuk melihat langsung detail dokumen
                  </span>
                </div>

                {searchResultsQuery.isLoading ? (
                  <div className="space-y-2">
                    <Skeleton className="h-14 w-full rounded-xl" />
                    <Skeleton className="h-14 w-full rounded-xl" />
                    <Skeleton className="h-14 w-full rounded-xl" />
                  </div>
                ) : searchResultsQuery.data?.data?.length ? (
                  <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                    {searchResultsQuery.data.data.map((doc) => {
                      const statusStr = String(doc.status).toLowerCase();
                      const isVerified =
                        statusStr.includes("terverifikasi") &&
                        !statusStr.includes("tidak");
                      const isRejected =
                        statusStr.includes("tidak") ||
                        statusStr.includes("rejected") ||
                        statusStr.includes("ditolak");

                      return (
                        <div
                          key={doc.id}
                          onClick={(e) => handleOpenDocView(doc.id, e)}
                          className="p-3.5 rounded-xl border border-border/60 hover:bg-muted/50 transition-colors flex items-center justify-between cursor-pointer group"
                        >
                          <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-xl bg-orange-500/10 text-orange-600 dark:bg-orange-950 dark:text-orange-400 shrink-0">
                              <FileText className="w-4 h-4" />
                            </div>
                            <div className="space-y-0.5">
                              <p className="text-xs font-semibold text-foreground group-hover:text-[#F54A00] transition-colors">
                                {doc.title ||
                                  doc.file_name ||
                                  `Dokumen #${doc.id}`}
                              </p>
                              <p className="text-[11px] text-muted-foreground">
                                {doc.category ||
                                  doc.document_type ||
                                  "Dokumen Arsip"}{" "}
                                • Uploaded{" "}
                                {new Date(doc.created_at).toLocaleDateString(
                                  "id-ID",
                                )}
                                {doc.uploader?.name
                                  ? ` oleh ${doc.uploader.name}`
                                  : ""}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center gap-3">
                            <Badge
                              variant="secondary"
                              className={
                                isVerified
                                  ? "bg-emerald-100 text-emerald-700 border-emerald-200 text-[10px]"
                                  : isRejected
                                    ? "bg-rose-100 text-rose-700 border-rose-200 text-[10px]"
                                    : "bg-amber-100 text-amber-700 border-amber-200 text-[10px]"
                              }
                            >
                              {statusStr.toUpperCase()}
                            </Badge>

                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={(e) => handleOpenDocView(doc.id, e)}
                              className="h-8 text-xs gap-1.5 text-[#F54A00] hover:bg-[#F54A00]/10 font-medium"
                            >
                              <Eye className="w-3.5 h-3.5" />
                              View
                            </Button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="p-8 text-center text-xs text-muted-foreground border border-dashed rounded-xl space-y-1">
                    <p className="font-medium text-foreground">
                      Dokumen tidak ditemukan
                    </p>
                    <p>Tidak ada dokumen yang sesuai dengan kata kunci.</p>
                  </div>
                )}
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-semibold text-foreground flex items-center gap-1.5">
                    <Clock className="w-4 h-4 text-muted-foreground" />
                    Pencarian Terakhir
                  </span>
                  {recentSearches.length > 0 && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={clearAllRecentSearches}
                      className="h-6 text-[11px] text-muted-foreground hover:text-rose-600 px-2"
                    >
                      <Trash2 className="w-3 h-3 mr-1" />
                      Hapus Riwayat
                    </Button>
                  )}
                </div>

                {recentSearches.length > 0 ? (
                  <div className="space-y-2">
                    {recentSearches.map((item, index) => (
                      <div
                        key={index}
                        onClick={() => handleSelectRecent(item)}
                        className="p-3 rounded-xl border border-border/50 hover:bg-muted/50 transition-colors flex items-center justify-between cursor-pointer group"
                      >
                        <div className="flex items-center gap-3">
                          <Search className="w-4 h-4 text-muted-foreground group-hover:text-[#F54A00] transition-colors" />
                          <span className="text-xs text-foreground font-medium">
                            {item}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={(e) => removeRecentSearch(item, e)}
                            className="h-6 w-6 text-muted-foreground hover:text-rose-600 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Hapus"
                          >
                            <X className="w-3.5 h-3.5" />
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="p-6 text-center text-xs text-muted-foreground border border-dashed rounded-xl">
                    Belum ada riwayat pencarian. Ketik kata kunci di atas untuk
                    mencari secara langsung.
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="p-3.5 bg-muted/40 border-t border-border/60 flex items-center justify-between text-[11px] text-muted-foreground px-6">
            <div className="flex items-center gap-5">
              <span className="flex items-center gap-1.5">
                <kbd className="px-2 py-0.5 rounded bg-background border border-border/80 text-[10px] font-mono shadow-xs">
                  Ctrl + K
                </kbd>
                Buka pencarian
              </span>
              <span className="flex items-center gap-1.5">
                <kbd className="px-2 py-0.5 rounded bg-background border border-border/80 text-[10px] font-mono shadow-xs">
                  Esc
                </kbd>
                Tutup
              </span>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {viewDocId && (
        <ViewArchiveModal
          id={viewDocId}
          open={viewModalOpen}
          onOpenChange={setViewModalOpen}
        />
      )}
    </>
  );
}

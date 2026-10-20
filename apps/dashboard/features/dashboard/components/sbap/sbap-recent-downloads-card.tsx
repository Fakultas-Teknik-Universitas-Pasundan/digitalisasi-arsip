import React from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { dashboardQueries } from "../../queries/dashboard.queries";
import { Download, ExternalLink, FileText, CheckCircle2 } from "lucide-react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@repo/ui/card";
import { Button } from "@repo/ui/button";
import { Badge } from "@repo/ui/badge";
import { Skeleton } from "@repo/ui/skeleton";
import type { DocumentItem } from "../../types/dashboard.types";

interface SBAPRecentDownloadsCardProps {
  onDownload: (docId: number) => void;
}

export function SBAPRecentDownloadsCard({
  onDownload,
}: SBAPRecentDownloadsCardProps) {
  // Directly fetch verified documents from API
  const docsQuery = useQuery(
    dashboardQueries.documents({ status: "terverifikasi", per_page: 5 }),
  );
  const isLoading = docsQuery.isLoading;

  const downloads: DocumentItem[] = docsQuery.data?.data || [];

  return (
    <Card className="border border-border/60 bg-card shadow-sm">
      <CardHeader className="p-5 pb-3 flex flex-row items-center justify-between">
        <div>
          <CardTitle className="text-lg font-semibold text-foreground">
            Riwayat Download Terbaru
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground mt-0.5">
            Dokumen terverifikasi yang tersedia untuk diunduh
          </CardDescription>
        </div>
        <Button
          render={<Link href="/download-history" />}
          variant="outline"
          size="sm"
          className="text-xs h-8 gap-1.5 border-border/60"
        >
          Lihat Semua
          <ExternalLink className="w-3.5 h-3.5" />
        </Button>
      </CardHeader>

      <CardContent className="p-5 pt-0">
        {isLoading ? (
          <div className="space-y-3 pt-2">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : downloads.length > 0 ? (
          <div className="divide-y divide-border/40 rounded-xl border border-border/60 overflow-hidden bg-card">
            {downloads.map((item) => (
              <div
                key={item.id}
                className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-muted/30 transition-colors"
              >
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 shrink-0 mt-0.5">
                    <FileText className="w-4 h-4" />
                  </div>
                  <div className="space-y-1">
                    <p className="text-xs font-semibold text-foreground leading-snug">
                      {item.title || item.file_name}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                      NPM: {item.npm || item.student_number || "-"} •{" "}
                      {item.prodi || "Teknik Informatika"} •{" "}
                      {new Date(item.created_at).toLocaleDateString("id-ID", {
                        day: "numeric",
                        month: "short",
                        year: "numeric",
                      })}
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-3 self-end sm:self-center shrink-0">
                  <Badge
                    variant="secondary"
                    className="bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 text-[10px] gap-1 px-2.5 py-0.5"
                  >
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                    Terverifikasi
                  </Badge>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onDownload(item.id)}
                    className="h-8 text-xs text-[#F54A00] hover:text-[#d64100] hover:bg-orange-50 dark:hover:bg-orange-950/20 font-medium gap-1"
                  >
                    <Download className="w-3.5 h-3.5" />
                    Download Lagi
                  </Button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="h-32 flex flex-col items-center justify-center text-muted-foreground text-xs rounded-xl border border-dashed p-6">
            <span>Belum ada riwayat dokumen yang baru diunduh.</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

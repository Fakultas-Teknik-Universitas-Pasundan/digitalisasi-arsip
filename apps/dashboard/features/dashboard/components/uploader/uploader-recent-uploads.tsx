import React from "react";
import { Upload, RefreshCw, Eye } from "lucide-react";
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
import { getDocCategory } from "../../utils/document-mappers";

interface UploaderRecentUploadsProps {
  isLoading?: boolean;
  documents: DocumentItem[];
  onRefetch: () => void;
  onOpenDetail: (id: number) => void;
}

export function UploaderRecentUploads({
  isLoading,
  documents,
  onRefetch,
  onOpenDetail,
}: UploaderRecentUploadsProps) {
  return (
    <Card className="border border-border/60 bg-card shadow-sm">
      <CardHeader className="p-5 pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-lg font-semibold text-foreground">
              Upload Terbaru
            </CardTitle>
            <CardDescription className="text-xs text-muted-foreground mt-0.5">
              5 dokumen terakhir yang Anda upload
            </CardDescription>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={onRefetch}
            className="gap-1.5 text-xs h-8"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            Refresh
          </Button>
        </div>
      </CardHeader>

      <CardContent className="p-5 pt-0">
        {isLoading ? (
          <div className="space-y-3 pt-2">
            <Skeleton className="h-14 w-full rounded-xl" />
            <Skeleton className="h-14 w-full rounded-xl" />
            <Skeleton className="h-14 w-full rounded-xl" />
          </div>
        ) : documents.length > 0 ? (
          <div className="divide-y divide-border/60">
            {documents.map((doc) => {
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
                  className="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-muted/30 px-2 rounded-lg transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-[#DBEAFE] text-[#155DFC] dark:bg-blue-950 dark:text-blue-300 flex items-center justify-center shrink-0">
                      <Upload className="w-5 h-5" />
                    </div>
                    <div className="space-y-0.5">
                      <p className="text-xs font-semibold text-foreground">
                        {doc.title || doc.file_name || `Dokumen #${doc.id}`}
                      </p>
                      <p className="text-[11px] text-muted-foreground">
                        {new Date(doc.created_at).toLocaleString("id-ID", {
                          day: "numeric",
                          month: "short",
                          year: "numeric",
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-3 self-end sm:self-center">
                    <Badge
                      variant="outline"
                      className="bg-muted text-muted-foreground text-[11px] rounded-full px-2.5"
                    >
                      {getDocCategory(doc)}
                    </Badge>

                    <Badge
                      variant="secondary"
                      className={
                        isVerified
                          ? "bg-[#DCFCE7] text-[#00A63E] border-emerald-200 text-[11px] rounded-full px-2.5"
                          : isRejected
                            ? "bg-[#FFE2E2] text-[#E7000B] border-rose-200 text-[11px] rounded-full px-2.5"
                            : "bg-[#FEF9C2] text-[#D08700] border-amber-200 text-[11px] rounded-full px-2.5"
                      }
                    >
                      {isVerified
                        ? "Terverifikasi"
                        : isRejected
                          ? "Ditolak"
                          : "Menunggu Verifikasi"}
                    </Badge>

                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => onOpenDetail(doc.id)}
                      className="text-xs text-[#F54A00] hover:text-[#d64100] hover:bg-[#F54A00]/10 font-medium h-8 gap-1"
                    >
                      <Eye className="w-3.5 h-3.5" />
                      Lihat Detail
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="h-40 flex flex-col items-center justify-center text-muted-foreground text-xs rounded-xl border border-dashed p-6">
            <span>Belum ada dokumen yang diunggah.</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

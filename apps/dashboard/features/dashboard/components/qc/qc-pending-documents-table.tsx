"use client";

import React, { useState, useMemo } from "react";
import Link from "next/link";
import { RefreshCw, Clock, ArrowRight } from "lucide-react";
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
import { QCPendingDocumentItem } from "./qc-pending-document-item";
import { QCVerifyModal } from "./qc-verify-modal";
import { QCRejectModal } from "./qc-reject-modal";
import { ViewArchiveModal } from "@/features/archives/components/ui/view-archive-modal";

interface QCPendingDocumentsTableProps {
  pendingDocs: DocumentItem[];
  isLoading: boolean;
  onRefresh: () => void;
  onVerify: (args: {
    id: number;
    status: "terverifikasi" | "tidak terverifikasi";
    note?: string;
  }) => void;
  isVerifying: boolean;
}

// Helper to determine if a document is Urgent strictly based on API status, title, or file name
function checkIsUrgent(doc: DocumentItem): boolean {
  const d = doc as unknown as Record<string, unknown>;
  if (
    d.is_urgent === true ||
    doc.status === "urgent" ||
    doc.status === "menunggu_verifikasi_urgent"
  )
    return true;
  const fileName = doc.file_name?.toLowerCase() || "";
  const title = doc.title?.toLowerCase() || "";
  return (
    fileName.includes("urgent") ||
    title.includes("urgent") ||
    fileName.includes("penting") ||
    title.includes("penting")
  );
}

export function QCPendingDocumentsTable({
  pendingDocs,
  isLoading,
  onRefresh,
  onVerify,
  isVerifying,
}: QCPendingDocumentsTableProps) {
  // Modal states
  const [verifyModalDoc, setVerifyModalDoc] = useState<DocumentItem | null>(
    null,
  );
  const [rejectModalDoc, setRejectModalDoc] = useState<DocumentItem | null>(
    null,
  );
  const [viewModalDocId, setViewModalDocId] = useState<number | null>(null);

  // Sort documents so Urgent items appear grouped at the very top
  const sortedPendingDocs = useMemo(() => {
    return [...pendingDocs]
      .map((doc) => ({
        doc,
        isUrgent: checkIsUrgent(doc),
      }))
      .sort((a, b) => {
        if (a.isUrgent && !b.isUrgent) return -1;
        if (!a.isUrgent && b.isUrgent) return 1;
        return 0;
      });
  }, [pendingDocs]);

  const handleConfirmVerify = (id: number, note?: string) => {
    onVerify({ id, status: "terverifikasi", note });
  };

  const handleConfirmReject = (id: number, reason: string) => {
    onVerify({ id, status: "tidak terverifikasi", note: reason });
  };

  return (
    <>
      <Card className="border border-border/60 bg-card shadow-sm overflow-hidden">
        {/* Header Section */}
        <CardHeader className="p-5 sm:p-6 border-b border-border/50 bg-card">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle className="text-lg font-bold tracking-tight text-foreground flex items-center gap-2">
                <span>Dokumen Menunggu Verifikasi</span>
                {sortedPendingDocs.length > 0 && (
                  <Badge
                    variant="secondary"
                    className="rounded-full px-2.5 py-0.5 text-xs bg-amber-500/10 text-amber-600 dark:bg-amber-950 dark:text-amber-400"
                  >
                    {sortedPendingDocs.length}
                  </Badge>
                )}
              </CardTitle>
              <CardDescription className="text-xs text-muted-foreground mt-1">
                Prioritaskan dokumen dengan label &quot;Urgent&quot; (diurutkan
                paling atas)
              </CardDescription>
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={onRefresh}
                className="gap-1.5 text-xs h-9"
              >
                <RefreshCw className="w-3.5 h-3.5" />
                Refresh
              </Button>
              <Link href="/verification?status=menunggu_verifikasi">
                <Button
                  size="sm"
                  className="bg-[#F54A00] hover:bg-[#d64100] text-white shadow-xs font-medium text-xs gap-1.5 h-9"
                >
                  <span>Lihat Semua</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </Button>
              </Link>
            </div>
          </div>
        </CardHeader>

        {/* Content Section */}
        <CardContent className="p-0">
          {isLoading ? (
            <div className="p-6 space-y-4">
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="flex items-center justify-between gap-4"
                >
                  <div className="flex items-center gap-3">
                    <Skeleton className="w-10 h-10 rounded-xl" />
                    <div className="space-y-2">
                      <Skeleton className="h-4 w-48" />
                      <Skeleton className="h-3 w-32" />
                    </div>
                  </div>
                  <Skeleton className="h-8 w-24 rounded-lg" />
                </div>
              ))}
            </div>
          ) : sortedPendingDocs.length > 0 ? (
            <div className="divide-y divide-border/50">
              {sortedPendingDocs.map(({ doc, isUrgent }, idx) => (
                <QCPendingDocumentItem
                  key={doc.id}
                  doc={doc}
                  idx={idx}
                  isUrgent={isUrgent}
                  onOpenVerify={(d) => setVerifyModalDoc(d)}
                  onOpenReject={(d) => setRejectModalDoc(d)}
                  onOpenView={(d) => setViewModalDocId(d.id)}
                  isVerifying={isVerifying}
                />
              ))}
            </div>
          ) : (
            <div className="py-12 px-6 text-center">
              <Clock className="w-10 h-10 text-muted-foreground mx-auto mb-2 opacity-50" />
              <p className="text-sm font-semibold text-foreground">
                Tidak ada dokumen pending
              </p>
              <p className="text-xs text-muted-foreground mt-1">
                Seluruh dokumen telah selesai diverifikasi oleh tim QC.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Modal Dialogs */}
      <QCVerifyModal
        doc={verifyModalDoc}
        onClose={() => setVerifyModalDoc(null)}
        onConfirm={handleConfirmVerify}
        isVerifying={isVerifying}
      />
      <QCRejectModal
        doc={rejectModalDoc}
        onClose={() => setRejectModalDoc(null)}
        onConfirm={handleConfirmReject}
        isVerifying={isVerifying}
      />
      {viewModalDocId && (
        <ViewArchiveModal
          id={viewModalDocId}
          open={!!viewModalDocId}
          onOpenChange={(open) => {
            if (!open) setViewModalDocId(null);
          }}
        />
      )}
    </>
  );
}

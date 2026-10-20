"use client";

import React, { useState, useMemo } from "react";
import { Clock, RefreshCw, FileCheck } from "lucide-react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@repo/ui/card";
import { Button } from "@repo/ui/button";
import { Skeleton } from "@repo/ui/skeleton";
import { useQCDashboard } from "../../hooks/use-qc-dashboard";
import { QCVerificationCardItem } from "../qc/qc-verification-card-item";
import { QCVerifyModal } from "../qc/qc-verify-modal";
import { QCRejectModal } from "../qc/qc-reject-modal";
import { ViewArchiveModal } from "@/features/archives/components/ui/view-archive-modal";
import type { DocumentItem } from "../../types/dashboard.types";

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

export function QCVerificationPageView() {
  const {
    isLoading,
    pendingDocs,
    refetchPending,
    verifyDocument,
    isVerifying,
  } = useQCDashboard();

  // Modal state management
  const [verifyModalDoc, setVerifyModalDoc] = useState<DocumentItem | null>(
    null,
  );
  const [rejectModalDoc, setRejectModalDoc] = useState<DocumentItem | null>(
    null,
  );
  const [viewModalDocId, setViewModalDocId] = useState<number | null>(null);

  // Sort documents by urgency (Urgent first)
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

  // Urgent count for top metrics
  const urgentCount = useMemo(() => {
    return sortedPendingDocs.filter((item) => item.isUrgent).length;
  }, [sortedPendingDocs]);

  const handleConfirmVerify = (id: number, note?: string) => {
    verifyDocument({ id, status: "terverifikasi", note });
  };

  const handleConfirmReject = (id: number, reason: string) => {
    verifyDocument({ id, status: "tidak terverifikasi", note: reason });
  };

  return (
    <div className="flex flex-col gap-6 w-full pb-12">
      {/* 1. Page Header (Figma Node 1714:17656) */}
      <Card className="border border-border/60 bg-card shadow-xs">
        <CardHeader className="p-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle className="text-2xl font-bold tracking-tight text-foreground">
                Verifikasi Dokumen
              </CardTitle>
              <CardDescription className="text-sm text-muted-foreground mt-1">
                Review dan verifikasi dokumen yang diunggah oleh Tim Uploader
              </CardDescription>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() => refetchPending()}
              className="gap-1.5 text-xs h-9 self-start sm:self-center"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Refresh Data</span>
            </Button>
          </div>
        </CardHeader>
      </Card>

      {/* 2. Top Summary Metric Cards (Figma Node 1714:17661) */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {/* Card 1: Total Pending */}
        <Card className="border border-border/60 bg-card shadow-xs p-5 flex flex-col justify-between">
          <div className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">
              Total Pending
            </span>
            <div className="text-3xl font-extrabold text-[#D08700]">
              {isLoading ? (
                <Skeleton className="h-9 w-12" />
              ) : (
                sortedPendingDocs.length
              )}
            </div>
          </div>
        </Card>

        {/* Card 2: Prioritas Tinggi */}
        <Card className="border border-border/60 bg-card shadow-xs p-5 flex flex-col justify-between">
          <div className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">
              Prioritas Tinggi
            </span>
            <div className="text-3xl font-extrabold text-[#E7000B]">
              {isLoading ? <Skeleton className="h-9 w-12" /> : urgentCount}
            </div>
          </div>
        </Card>

        {/* Card 3: Status Antrean */}
        <Card className="border border-border/60 bg-card shadow-xs p-5 flex flex-col justify-between">
          <div className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">
              Status Antrean
            </span>
            <div className="text-xl font-bold text-foreground flex items-center gap-2 pt-1">
              <Clock className="w-5 h-5 text-amber-500" />
              <span>
                {sortedPendingDocs.length > 0
                  ? "Memerlukan Verifikasi"
                  : "Antrean Kosong"}
              </span>
            </div>
          </div>
        </Card>
      </div>

      {/* 3. Document Cards Queue */}
      <div className="space-y-4">
        <div className="flex items-center justify-between px-1">
          <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
            <span>Daftar Dokumen Menunggu Verifikasi</span>
            <span className="text-xs font-normal text-muted-foreground">
              (Diurutkan dari prioritas urgent)
            </span>
          </h3>
        </div>

        {isLoading ? (
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} className="p-6 space-y-4 border border-border/50">
                <div className="flex justify-between">
                  <div className="flex gap-3">
                    <Skeleton className="w-12 h-12 rounded-xl" />
                    <div className="space-y-2">
                      <Skeleton className="h-5 w-64" />
                      <Skeleton className="h-3 w-40" />
                    </div>
                  </div>
                  <Skeleton className="h-6 w-24 rounded-full" />
                </div>
                <Skeleton className="h-24 w-full rounded-xl" />
              </Card>
            ))}
          </div>
        ) : sortedPendingDocs.length > 0 ? (
          <div className="space-y-4">
            {sortedPendingDocs.map(({ doc, isUrgent }, idx) => (
              <QCVerificationCardItem
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
          <Card className="p-12 text-center border border-border/60">
            <CardContent className="flex flex-col items-center justify-center p-0">
              <div className="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center mb-3">
                <FileCheck className="w-7 h-7" />
              </div>
              <h4 className="text-lg font-semibold text-foreground">
                Semua Dokumen Telah Diverifikasi
              </h4>
              <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                Saat ini tidak ada dokumen pending yang membutuhkan tindakan
                verifikasi.
              </p>
            </CardContent>
          </Card>
        )}
      </div>

      {/* 4. Verification & Rejection Modal Dialogs */}
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
    </div>
  );
}

"use client";

import React, { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@repo/ui/dialog";
import { Button } from "@repo/ui/button";
import { Label } from "@repo/ui/label";
import { Textarea } from "@repo/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@repo/ui/select";
import { XCircle, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { http } from "@/shared/lib/http";
import { useQueryClient } from "@tanstack/react-query";

// Backend has no dedicated rejection_reason field: the selected reason is
// embedded into verification_note so the information is never lost.
const REJECTION_REASON_LABELS: Record<string, string> = {
  unclear_scan: "File Scan Tidak Jelas / Buram",
  mismatch_data: "NIM / Nama Mahasiswa Tidak Cocok",
  corrupt_file: "File Rusak / Tidak Bisa Dibuka",
  wrong_type: "Kategori Dokumen Salah",
  other: "Lainnya",
};

interface RejectDocumentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  documentId: number;
  documentTitle: string;
}

export const RejectDocumentDialog: React.FC<RejectDocumentDialogProps> = ({
  open,
  onOpenChange,
  documentId,
  documentTitle,
}) => {
  const [loading, setLoading] = useState(false);
  const [reason, setReason] = useState("unclear_scan");
  const [notes, setNotes] = useState("");
  const queryClient = useQueryClient();

  const handleReject = async () => {
    try {
      setLoading(true);
      await http.patch(`/api/documents/${documentId}/verify`, {
        status: "tidak terverifikasi",
        verification_note: [
          REJECTION_REASON_LABELS[reason] ?? reason,
          notes.trim(),
        ]
          .filter(Boolean)
          .join(" - ")
          .slice(0, 500),
      });
      toast.success("Dokumen ditolak!");
      queryClient.invalidateQueries({ queryKey: ["documents"] });
      onOpenChange(false);
    } catch (err: unknown) {
      const errorMsg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || "Terjadi kesalahan.";
      toast.error("Gagal menolak dokumen", {
        description: errorMsg,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-red-100 text-red-600 rounded-full dark:bg-red-950 dark:text-red-400">
              <XCircle className="size-6" />
            </div>
            <div className="text-left">
              <DialogTitle className="text-base">Penolakan Dokumen</DialogTitle>
              <DialogDescription className="text-xs">
                {documentTitle}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-1.5">
            <Label htmlFor="reason" className="text-xs font-semibold">
              Alasan Penolakan <span className="text-red-500">*</span>
            </Label>
            <Select
              value={reason}
              onValueChange={(val) => setReason(val || "")}
            >
              <SelectTrigger className="h-9">
                <SelectValue placeholder="Pilih Alasan Penolakan" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="unclear_scan">
                  File Scan Tidak Jelas / Buram
                </SelectItem>
                <SelectItem value="mismatch_data">
                  NIM / Nama Mahasiswa Tidak Cocok
                </SelectItem>
                <SelectItem value="corrupt_file">
                  File Rusak / Tidak Bisa Dibuka
                </SelectItem>
                <SelectItem value="wrong_type">
                  Kategori Dokumen Salah
                </SelectItem>
                <SelectItem value="other">Lainnya</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="notes" className="text-xs font-semibold">
              Catatan Penolakan (Opsional)
            </Label>
            <Textarea
              id="notes"
              placeholder="Jelaskan detail perbaikan yang diperlukan oleh uploader..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="text-xs resize-none"
              rows={3}
            />
          </div>
        </div>

        <DialogFooter className="gap-2 pt-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onOpenChange(false)}
            disabled={loading}
          >
            Batal
          </Button>
          <Button
            variant="destructive"
            size="sm"
            onClick={handleReject}
            disabled={loading}
          >
            {loading ? (
              <>
                <Loader2 className="size-4 animate-spin mr-2" />
                Memproses...
              </>
            ) : (
              <>
                <XCircle className="size-4 mr-2" />
                Konfirmasi Penolakan
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

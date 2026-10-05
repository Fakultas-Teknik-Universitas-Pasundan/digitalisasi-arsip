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
import { CheckCircle2, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { http } from "@/shared/lib/http";
import { useQueryClient } from "@tanstack/react-query";

interface VerifyDocumentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  documentId: number;
  documentTitle: string;
}

export const VerifyDocumentDialog: React.FC<VerifyDocumentDialogProps> = ({
  open,
  onOpenChange,
  documentId,
  documentTitle,
}) => {
  const [loading, setLoading] = useState(false);
  const [notes, setNotes] = useState("");
  const queryClient = useQueryClient();

  const handleVerify = async () => {
    try {
      setLoading(true);
      await http.patch(`/api/documents/${documentId}/verify`, {
        status: "terverifikasi",
        verification_note: notes || undefined,
      });
      toast.success("Dokumen berhasil diverifikasi!");
      queryClient.invalidateQueries({ queryKey: ["documents"] });
      onOpenChange(false);
    } catch (err: unknown) {
      const errorMsg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || "Terjadi kesalahan.";
      toast.error("Gagal memverifikasi dokumen", {
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
            <div className="p-2.5 bg-green-100 text-green-600 rounded-full dark:bg-green-950 dark:text-green-400">
              <CheckCircle2 className="size-6" />
            </div>
            <div className="text-left">
              <DialogTitle className="text-base">
                Verifikasi Dokumen
              </DialogTitle>
              <DialogDescription className="text-xs">
                {documentTitle}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-3 py-2">
          <Label htmlFor="notes" className="text-xs font-semibold">
            Catatan Verifikasi (Opsional)
          </Label>
          <Textarea
            id="notes"
            placeholder="Tambahkan catatan hasil verifikasi..."
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            className="text-xs resize-none"
            rows={3}
          />
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
            size="sm"
            onClick={handleVerify}
            disabled={loading}
            className="bg-green-600 hover:bg-green-700 text-white"
          >
            {loading ? (
              <>
                <Loader2 className="size-4 animate-spin mr-2" />
                Memproses...
              </>
            ) : (
              <>
                <CheckCircle2 className="size-4 mr-2" />
                Konfirmasi Verifikasi
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

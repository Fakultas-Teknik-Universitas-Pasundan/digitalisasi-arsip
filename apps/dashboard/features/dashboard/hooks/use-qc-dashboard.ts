"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { dashboardQueries } from "../queries/dashboard.queries";
import { dashboardService } from "../services/dashboard.service";

export function useQCDashboard() {
  const queryClient = useQueryClient();
  const pendingQuery = useQuery(dashboardQueries.pendingDocuments(15, 1));
  const docStatsQuery = useQuery(dashboardQueries.documentStats());

  const isLoading = pendingQuery.isLoading || docStatsQuery.isLoading;
  const stats = docStatsQuery.data?.data;
  const pendingDocs = pendingQuery.data?.data || [];

  const verifyMutation = useMutation({
    mutationFn: ({
      id,
      status,
      note,
    }: {
      id: number;
      status: "terverifikasi" | "tidak terverifikasi";
      note?: string;
    }) =>
      dashboardService.verifyDocument(id, { status, verification_note: note }),
    onSuccess: (res, variables) => {
      toast.success(
        variables.status === "terverifikasi"
          ? "Dokumen berhasil diverifikasi!"
          : "Dokumen ditolak.",
      );
      queryClient.invalidateQueries({ queryKey: dashboardQueries.all });
    },
    onError: () => {
      toast.error("Gagal memproses verifikasi dokumen.");
    },
  });

  return {
    isLoading,
    stats,
    pendingDocs,
    isPendingRefetching: pendingQuery.isFetching,
    refetchPending: pendingQuery.refetch,
    verifyDocument: verifyMutation.mutate,
    isVerifying: verifyMutation.isPending,
  };
}

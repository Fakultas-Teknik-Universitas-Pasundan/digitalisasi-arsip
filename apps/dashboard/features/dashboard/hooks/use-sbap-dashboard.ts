"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { dashboardQueries } from "../queries/dashboard.queries";

export function useSBAPDashboard() {
  const [searchTerm, setSearchTerm] = useState("");

  const verifiedDocsQuery = useQuery(
    dashboardQueries.documents({
      status: "terverifikasi",
      per_page: 15,
      search: searchTerm,
    }),
  );
  const docStatsQuery = useQuery(dashboardQueries.documentStats());

  const isLoading = verifiedDocsQuery.isLoading || docStatsQuery.isLoading;
  const stats = docStatsQuery.data?.data;
  const verifiedDocs = verifiedDocsQuery.data?.data || [];

  const handleDownload = (id: number) => {
    window.open(`/api/documents/${id}/download`, "_blank");
  };

  return {
    searchTerm,
    setSearchTerm,
    isLoading,
    stats,
    verifiedDocs,
    refetchVerified: verifiedDocsQuery.refetch,
    handleDownload,
  };
}

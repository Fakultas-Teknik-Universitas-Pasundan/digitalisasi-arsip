export type PeriodType = "hari" | "minggu" | "bulan" | "custom";

export interface DocumentStats {
  total_documents: number;
  verified_documents: number;
  pending_documents: number;
  rejected_documents: number;
}

export interface DocumentStatisticsResponse {
  message: string;
  data: DocumentStats;
}

export interface DashboardStatsResponse {
  message: string;
  data: DocumentStats;
  period?: {
    start_date: string;
    end_date: string;
  };
}

export interface DocumentUploader {
  id: number;
  name: string;
  email: string;
  role: string;
}

export type DocumentStatusType =
  | "pending"
  | "verified"
  | "rejected"
  | "menunggu_verifikasi"
  | "terverifikasi"
  | "tidak_terverifikasi";

export interface DocumentItem {
  id: number;
  file_name?: string;
  title?: string;
  document_type?: string;
  category?: string;
  status: DocumentStatusType | string;
  created_at: string | Date;
  updated_at?: string | Date;
  verification_note?: string | null;
  student_number?: string;
  npm?: string;
  prodi?: string;
  department?: string;
  tahun_ajaran?: string;
  academic_year?: string;
  semester?: string;
  mata_kuliah?: string;
  course_name?: string;
  kelas?: string;
  class_name?: string;
  tahun_lulus?: string;
  graduation_year?: string;
  student_name?: string;
  uploaded_by_name?: string;
  verified_by_name?: string;
  verified_at?: string | Date | null;
  uploader?: DocumentUploader;
  year?: string;
  file_url?: string;
  file_size?: number | string;
  file_size_formatted?: string;
}

export interface DocumentListResponse {
  message: string;
  data: DocumentItem[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface VerifyDocumentPayload {
  status: "terverifikasi" | "tidak terverifikasi";
  verification_note?: string;
}

export interface AuditLogActionObj {
  name: string;
  label: string;
  color?: string;
}

export interface AuditLogItem {
  id: number;
  user_id?: number;
  action: string | AuditLogActionObj;
  description: string;
  created_at?: string;
  date?: {
    formatted?: string;
    time?: string;
    timestamp?: string;
  };
  user?: {
    id?: number;
    name: string;
    email?: string;
    role?: string;
  };
}

export interface AuditLogStats {
  total_activities: number;
  today_total: number;
  today_upload: number;
  today_verify: number;
  today_reject: number;
  by_action?: Record<string, number>;
  recent_activities?: AuditLogItem[];
}

export interface AuditLogStatisticsResponse {
  message: string;
  data: AuditLogStats;
}

export interface UserStats {
  total_users: number;
  total_by_role?: Record<string, number>;
  active_users: number;
  new_users: number;
}

export interface UserStatisticsResponse {
  message: string;
  data: UserStats;
}

export interface YearlyStat {
  year: string;
  nilai: number;
  transkrip: number;
  ijazah: number;
  bas: number;
  total: number;
}

export interface DocumentTypeStat {
  name: string;
  count: number;
  percentage: number;
  color: string;
}

export interface QCStaffStat {
  id?: number;
  staff: string;
  role?: string;
  email?: string;
  terverifikasi: number;
  ditolak: number;
  avgTime: string;
  successRate: string;
  isOnline?: boolean;
  lastSeen?: string;
}

export interface NotificationItem {
  id: number;
  title: string;
  message: string;
  time: string;
  type: "warning" | "success" | "info" | "destructive";
  iconName: "alert-circle" | "check-circle" | "info" | "alert-triangle";
  bgColor: string;
  textColor: string;
  iconColor: string;
  /** URL tujuan saat notifikasi diklik. Opsional. */
  href?: string;
}

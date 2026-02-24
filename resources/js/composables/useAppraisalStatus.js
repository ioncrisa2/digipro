import {
  FileText,
  Clock,
  CheckCircle2,
  XCircle,
  AlertCircle,
} from "lucide-vue-next";

/**
 * Appraisal Status Enum
 * Maps database status values to their configurations
 */
export const AppraisalStatus = {
  DRAFT: "draft",
  SUBMITTED: "submitted",
  DOCS_INCOMPLETE: "docs_incomplete",
  DOCUMENT_NOT_COMPLETE: "document_not_complete", // Legacy alias
  VERIFIED: "verified",
  WAITING_OFFER: "waiting_offer",
  OFFER_SENT: "offer_sent",
  WAITING_SIGNATURE: "waiting_signature",
  CONTRACT_SIGNED: "contract_signed",
  VALUATION_IN_PROGRESS: "valuation_in_progress",
  VALUATION_ON_PROGRESS: "valuation_on_progress", // Legacy alias
  VALUATION_COMPLETED: "valuation_completed",
  REPORT_READY: "report_ready",
  COMPLETED: "completed",
  CANCELLED: "cancelled",
  CANCELED: "canceled", // Legacy alias
};

/**
 * Status configuration mapping
 * Each status has: label, variant, icon, color classes
 */
const statusConfigMap = {
  [AppraisalStatus.DRAFT]: {
    label: "Draft",
    variant: "secondary",
    icon: FileText,
    color: "text-slate-600",
    bgColor: "bg-slate-50",
    borderColor: "border-slate-200",
  },
  [AppraisalStatus.SUBMITTED]: {
    label: "Diajukan",
    variant: "default",
    icon: Clock,
    color: "text-blue-600",
    bgColor: "bg-blue-50",
    borderColor: "border-blue-200",
  },
  [AppraisalStatus.DOCS_INCOMPLETE]: {
    label: "Dokumen Belum Lengkap",
    variant: "destructive",
    icon: AlertCircle,
    color: "text-rose-600",
    bgColor: "bg-rose-50",
    borderColor: "border-rose-200",
  },
  [AppraisalStatus.DOCUMENT_NOT_COMPLETE]: {
    label: "Dokumen Belum Lengkap",
    variant: "destructive",
    icon: AlertCircle,
    color: "text-rose-600",
    bgColor: "bg-rose-50",
    borderColor: "border-rose-200",
  },
  [AppraisalStatus.VERIFIED]: {
    label: "Terverifikasi",
    variant: "default",
    icon: CheckCircle2,
    color: "text-emerald-600",
    bgColor: "bg-emerald-50",
    borderColor: "border-emerald-200",
  },
  [AppraisalStatus.WAITING_OFFER]: {
    label: "Menunggu Penawaran",
    variant: "secondary",
    icon: Clock,
    color: "text-amber-600",
    bgColor: "bg-amber-50",
    borderColor: "border-amber-200",
  },
  [AppraisalStatus.OFFER_SENT]: {
    label: "Penawaran Dikirim",
    variant: "default",
    icon: Clock,
    color: "text-sky-600",
    bgColor: "bg-sky-50",
    borderColor: "border-sky-200",
  },
  [AppraisalStatus.WAITING_SIGNATURE]: {
    label: "Menunggu Tanda Tangan",
    variant: "default",
    icon: Clock,
    color: "text-violet-600",
    bgColor: "bg-violet-50",
    borderColor: "border-violet-200",
  },
  [AppraisalStatus.CONTRACT_SIGNED]: {
    label: "Kontrak Ditandatangani",
    variant: "default",
    icon: CheckCircle2,
    color: "text-indigo-600",
    bgColor: "bg-indigo-50",
    borderColor: "border-indigo-200",
  },
  [AppraisalStatus.VALUATION_IN_PROGRESS]: {
    label: "Proses Valuasi",
    variant: "default",
    icon: Clock,
    color: "text-orange-600",
    bgColor: "bg-orange-50",
    borderColor: "border-orange-200",
  },
  [AppraisalStatus.VALUATION_ON_PROGRESS]: {
    label: "Proses Valuasi",
    variant: "default",
    icon: Clock,
    color: "text-orange-600",
    bgColor: "bg-orange-50",
    borderColor: "border-orange-200",
  },
  [AppraisalStatus.VALUATION_COMPLETED]: {
    label: "Valuasi Selesai",
    variant: "default",
    icon: CheckCircle2,
    color: "text-teal-600",
    bgColor: "bg-teal-50",
    borderColor: "border-teal-200",
  },
  [AppraisalStatus.REPORT_READY]: {
    label: "Laporan Siap",
    variant: "default",
    icon: FileText,
    color: "text-cyan-600",
    bgColor: "bg-cyan-50",
    borderColor: "border-cyan-200",
  },
  [AppraisalStatus.COMPLETED]: {
    label: "Selesai",
    variant: "default",
    icon: CheckCircle2,
    color: "text-emerald-600",
    bgColor: "bg-emerald-50",
    borderColor: "border-emerald-200",
  },
  [AppraisalStatus.CANCELLED]: {
    label: "Dibatalkan",
    variant: "destructive",
    icon: XCircle,
    color: "text-rose-600",
    bgColor: "bg-rose-50",
    borderColor: "border-rose-200",
  },
  [AppraisalStatus.CANCELED]: {
    label: "Dibatalkan",
    variant: "destructive",
    icon: XCircle,
    color: "text-rose-600",
    bgColor: "bg-rose-50",
    borderColor: "border-rose-200",
  },
};

/**
 * Status filter options for Select component
 * Returns array of objects with value, label, and color indicator
 */
export const getStatusFilterOptions = () => [
  {
    value: "all",
    label: "Semua Status",
    dotColor: "bg-slate-400",
  },
  {
    value: AppraisalStatus.DRAFT,
    label: statusConfigMap[AppraisalStatus.DRAFT].label,
    dotColor: "bg-slate-400",
  },
  {
    value: AppraisalStatus.SUBMITTED,
    label: statusConfigMap[AppraisalStatus.SUBMITTED].label,
    dotColor: "bg-blue-400",
  },
  {
    value: AppraisalStatus.DOCS_INCOMPLETE,
    label: statusConfigMap[AppraisalStatus.DOCS_INCOMPLETE].label,
    dotColor: "bg-rose-400",
  },
  {
    value: AppraisalStatus.VERIFIED,
    label: statusConfigMap[AppraisalStatus.VERIFIED].label,
    dotColor: "bg-emerald-400",
  },
  {
    value: AppraisalStatus.VALUATION_IN_PROGRESS,
    label: statusConfigMap[AppraisalStatus.VALUATION_IN_PROGRESS].label,
    dotColor: "bg-orange-400",
  },
  {
    value: AppraisalStatus.COMPLETED,
    label: statusConfigMap[AppraisalStatus.COMPLETED].label,
    dotColor: "bg-emerald-400",
  },
  {
    value: AppraisalStatus.CANCELLED,
    label: statusConfigMap[AppraisalStatus.CANCELLED].label,
    dotColor: "bg-rose-400",
  },
];

/**
 * Composable for appraisal status management
 */
export const useAppraisalStatus = () => {
  /**
   * Get status configuration by status key
   * @param {string} status - The status key
   * @returns {object} Status configuration object
   */
  const getStatusConfig = (status) => {
    return statusConfigMap[status] || statusConfigMap[AppraisalStatus.DRAFT];
  };

  /**
   * Check if status is a draft
   * @param {string} status
   * @returns {boolean}
   */
  const isDraft = (status) => status === AppraisalStatus.DRAFT;

  /**
   * Check if status is submitted
   * @param {string} status
   * @returns {boolean}
   */
  const isSubmitted = (status) => status === AppraisalStatus.SUBMITTED;

  /**
   * Check if status is completed
   * @param {string} status
   * @returns {boolean}
   */
  const isCompleted = (status) => status === AppraisalStatus.COMPLETED;

  /**
   * Check if status is cancelled
   * @param {string} status
   * @returns {boolean}
   */
  const isCancelled = (status) =>
    status === AppraisalStatus.CANCELLED || status === AppraisalStatus.CANCELED;

  /**
   * Check if status indicates documents are incomplete
   * @param {string} status
   * @returns {boolean}
   */
  const isDocsIncomplete = (status) =>
    status === AppraisalStatus.DOCS_INCOMPLETE ||
    status === AppraisalStatus.DOCUMENT_NOT_COMPLETE;

  /**
   * Check if status is in progress (active workflow)
   * @param {string} status
   * @returns {boolean}
   */
  const isInProgress = (status) => {
    return [
      AppraisalStatus.SUBMITTED,
      AppraisalStatus.VERIFIED,
      AppraisalStatus.WAITING_OFFER,
      AppraisalStatus.OFFER_SENT,
      AppraisalStatus.WAITING_SIGNATURE,
      AppraisalStatus.CONTRACT_SIGNED,
      AppraisalStatus.VALUATION_IN_PROGRESS,
      AppraisalStatus.VALUATION_ON_PROGRESS,
      AppraisalStatus.VALUATION_COMPLETED,
      AppraisalStatus.REPORT_READY,
    ].includes(status);
  };

  /**
   * Get all available status filter options
   * @returns {Array}
   */
  const statusFilterOptions = getStatusFilterOptions();

  return {
    // Main function
    getStatusConfig,

    // Status checks
    isDraft,
    isSubmitted,
    isCompleted,
    isCancelled,
    isDocsIncomplete,
    isInProgress,

    // Filter options
    statusFilterOptions,

    // Enum for direct access
    AppraisalStatus,
  };
};

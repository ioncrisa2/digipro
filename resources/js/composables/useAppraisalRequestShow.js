import { computed, ref } from "vue";

/**
 * Composable untuk halaman detail appraisal request customer.
 */
export function useAppraisalRequestShow(props) {
  const tab = ref("overview");

  const req = computed(() => {
    const r = props?.request ?? {};
    return {
      request_number: r.request_number ?? (r.id != null ? `REQ-${r.id}` : "REQ-..."),
      status: r.status ?? "draft",
      status_label: r.status_label,
      report_type_label: r.report_type_label,
      assets_count: r.assets_count ?? (Array.isArray(r.assets) ? r.assets.length : 0),
      ...r,
    };
  });

  function formatIDR(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return "-";

    try {
      return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(num);
    } catch (_) {
      return `Rp ${num}`;
    }
  }

  function formatBytes(bytes) {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return "0 B";

    const units = ["B", "KB", "MB", "GB", "TB"];
    const idx = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
    const val = n / Math.pow(1024, idx);
    return `${val.toFixed(idx === 0 ? 0 : 2)} ${units[idx]}`;
  }

  function docTypeLabel(type) {
    const t = String(type || "").trim();
    const map = {
      agreement_pdf: "Agreement DigiPro",
      npwp: "NPWP",
      representative: "Surat Kuasa / Perwakilan",
      representative_letter_pdf: "Surat Representatif DigiPro",
      disclaimer_pdf: "Disclaimer DigiPro",
      permission: "Surat Izin Aset",
      contract_signed_pdf: "PDF Kontrak Ditandatangani",
      doc_pbb: "PBB",
      doc_imb: "IMB",
      doc_old_report: "Laporan Lama",
      doc_certs: "Sertifikat",
      photo_access_road: "Foto Akses Jalan",
      photo_front: "Foto Depan",
      photo_interior: "Foto Dalam",
      photos: "Foto",
    };

    return map[t] ?? (t ? t : "Dokumen");
  }

  function isImageDoc(doc) {
    if (!doc) return false;
    const mime = String(doc.mime || "").toLowerCase();
    if (mime.startsWith("image/")) return true;
    const t = String(doc.type || "").toLowerCase();
    return t.startsWith("photo_") || t === "photos";
  }

  const statusLabel = computed(() => {
    const fromBackend = req.value.status_label;
    if (fromBackend) return fromBackend;

    const s = req.value.status;
    const map = {
      draft: "Draft",
      submitted: "Terkirim",
      docs_incomplete: "Dokumen Kurang",
      verified: "Terverifikasi",
      waiting_offer: "Menunggu Penawaran",
      offer_sent: "Penawaran Dikirim",
      waiting_signature: "Menunggu TTD",
      contract_signed: "Kontrak Ditandatangani",
      valuation_in_progress: "Penilaian Berjalan",
      valuation_completed: "Penilaian Selesai",
      preview_ready: "Preview Kajian Siap",
      report_preparation: "Laporan Sedang Disiapkan",
      report_ready: "Laporan Siap",
      completed: "Selesai",
      cancelled: "Dibatalkan",
      pending: "Menunggu Review",
      in_progress: "Sedang Diproses",
      cancellation_review_pending: "Menunggu Review Pembatalan",
      rejected: "Ditolak",
    };

    return map[s] ?? String(s || "-");
  });

  const statusVariant = computed(() => {
    const s = req.value.status;
    if (["completed"].includes(s)) return "default";
    if (["rejected", "cancelled"].includes(s)) return "destructive";
    if (["paid", "in_progress", "valuation_in_progress", "valuation_completed", "preview_ready", "report_preparation", "report_ready"].includes(s)) {
      return "secondary";
    }
    return "outline";
  });

  const canDownloadReport = computed(() => Boolean(req.value.report_pdf_url || req.value.report_pdf_path));

  function downloadReport() {
    if (req.value.report_pdf_url) {
      window.open(req.value.report_pdf_url, "_blank", "noreferrer");
      return;
    }

    // eslint-disable-next-line no-alert
    alert(`File laporan belum punya URL public. Path: ${req.value.report_pdf_path || "-"}`);
  }

  function formatArea(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) return "-";
    return `${num} m2`;
  }

  function formatCoordinates(coords) {
    const lat = coords?.lat;
    const lng = coords?.lng;

    if (!Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) {
      return "-";
    }

    return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
  }

  const documentsSummary = computed(() => {
    const docs = [
      ...(Array.isArray(req.value.request_files) ? req.value.request_files : []),
      ...(Array.isArray(req.value.documents) ? req.value.documents : []),
    ];
    const totalBytes = docs.reduce((sum, d) => sum + (Number(d.size) || 0), 0);

    const typeCounts = {};
    for (const d of docs) {
      const label = docTypeLabel(d.type);
      typeCounts[label] = (typeCounts[label] || 0) + 1;
    }

    const byType = Object.entries(typeCounts)
      .map(([label, count]) => ({ label, count }))
      .sort((a, b) => b.count - a.count);

    return {
      totalCount: docs.length,
      totalBytes,
      byType,
    };
  });

  const documentsShortList = computed(() => {
    const docs = [
      ...(Array.isArray(req.value.request_files) ? req.value.request_files : []),
      ...(Array.isArray(req.value.documents) ? req.value.documents : []),
    ];
    return docs.filter((d) => !isImageDoc(d));
  });

  const documentsImages = computed(() => {
    const docs = [
      ...(Array.isArray(req.value.request_files) ? req.value.request_files : []),
      ...(Array.isArray(req.value.documents) ? req.value.documents : []),
    ];
    return docs.filter((d) => isImageDoc(d));
  });

  const requestDocuments = computed(() => {
    const docs = Array.isArray(req.value.request_files) ? req.value.request_files : [];
    return docs.filter((d) => !isImageDoc(d));
  });

  const documentsByAssetSections = computed(() => {
    const assets = Array.isArray(req.value.assets) ? req.value.assets : [];
    const docs = [
      ...(Array.isArray(req.value.request_files) ? req.value.request_files : []),
      ...(Array.isArray(req.value.documents) ? req.value.documents : []),
    ];

    const groupedDocs = new Map();
    for (const doc of docs) {
      const key = doc?.asset_id == null ? "__unlinked__" : String(doc.asset_id);
      if (!groupedDocs.has(key)) {
        groupedDocs.set(key, []);
      }
      groupedDocs.get(key).push(doc);
    }

    const sections = assets.map((asset, index) => {
      const key = String(asset?.id ?? `asset-${index}`);
      const docsForAsset = groupedDocs.get(key) ?? [];
      return {
        key: `asset-${key}`,
        title: `Aset #${index + 1} - ${asset?.type_label ?? asset?.type ?? "-"}`,
        asset,
        documents: docsForAsset.filter((doc) => !isImageDoc(doc)),
        images: docsForAsset.filter((doc) => isImageDoc(doc)),
      };
    });

    const unlinkedDocs = groupedDocs.get("__unlinked__") ?? [];
    if (unlinkedDocs.length) {
      sections.push({
        key: "asset-unlinked",
        title: "Dokumen Permohonan",
        asset: null,
        documents: unlinkedDocs.filter((doc) => !isImageDoc(doc)),
        images: unlinkedDocs.filter((doc) => isImageDoc(doc)),
      });
    }

    return sections;
  });

  const statusTimeline = computed(() => {
    const timeline = req.value?.status_timeline;
    return Array.isArray(timeline) ? timeline : [];
  });

  const documentWorkspace = computed(() => ({
    summary: req.value?.document_summary ?? {},
    requestUploadDocuments: Array.isArray(req.value?.request_upload_documents) ? req.value.request_upload_documents : [],
    assetSections: Array.isArray(req.value?.asset_sections) ? req.value.asset_sections : [],
    systemDocuments: Array.isArray(req.value?.system_documents) ? req.value.system_documents : [],
    legalDocuments: Array.isArray(req.value?.legal_documents) ? req.value.legal_documents : [],
    billingDocuments: Array.isArray(req.value?.billing_documents) ? req.value.billing_documents : [],
  }));

  const progressSummary = computed(() => req.value?.progress_summary ?? null);
  const physicalReport = computed(() => req.value?.physical_report ?? null);
  const billingSummary = computed(() => req.value?.billing_summary ?? null);
  const recentStatusEvents = computed(() => {
    const events = req.value?.recent_status_events;
    return Array.isArray(events) ? events : [];
  });
  const trackingPageUrl = computed(() => req.value?.tracking_page_url ?? null);
  const cancellationRequest = computed(() => req.value?.cancellation_request ?? null);
  const canRequestCancellation = computed(() => Boolean(req.value?.can_request_cancellation));
  const cancellationBlockers = computed(() => Array.isArray(req.value?.cancellation_blockers) ? req.value.cancellation_blockers : []);
  const cancellationRequestUrl = computed(() => req.value?.cancellation_request_url ?? null);
  const supportContact = computed(() => req.value?.support_contact ?? null);

  return {
    tab,
    req,
    statusLabel,
    statusVariant,
    formatIDR,
    formatBytes,
    formatArea,
    formatCoordinates,
    docTypeLabel,
    documentsSummary,
    documentsShortList,
    documentsImages,
    requestDocuments,
    documentsByAssetSections,
    statusTimeline,
    documentWorkspace,
    progressSummary,
    physicalReport,
    billingSummary,
    recentStatusEvents,
    trackingPageUrl,
    cancellationRequest,
    canRequestCancellation,
    cancellationBlockers,
    cancellationRequestUrl,
    supportContact,
    canDownloadReport,
    downloadReport,
  };
}

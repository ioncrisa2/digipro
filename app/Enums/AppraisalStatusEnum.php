<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum AppraisalStatusEnum: string
{
    use EnumTraits;

    case Draft                  = 'draft';
    case Submitted              = 'submitted';
    case DocsIncomplete         = 'docs_incomplete';
    case Verified               = 'verified';
    case WaitingOffer           = 'waiting_offer';
    case OfferSent              = 'offer_sent';
    case WaitingSignature       = 'waiting_signature';
    case ContractSigned         = 'contract_signed';
    case ValuationOnProgress    = 'valuation_in_progress';
    case ValuationCompleted     = 'valuation_completed';
    case PreviewReady           = 'preview_ready';
    case ReportPreparation      = 'report_preparation';
    case ReportReady            = 'report_ready';
    case CancellationReviewPending = 'cancellation_review_pending';
    case Completed              = 'completed';
    case Cancelled               = 'cancelled';

    public function label(): string
    {
        return match($this){
            self::Draft                 => 'Draft',
            self::Submitted             => 'Submitted',
            self::DocsIncomplete        => 'Dokumen Belum Lengkap',
            self::Verified              => 'Terverifikasi',
            self::WaitingOffer          => 'Menunggu Penawaran',
            self::OfferSent             => 'Penawaran Dikirim',
            self::WaitingSignature      => 'Menunggu Tanda Tangan',
            self::ContractSigned        => 'Kontrak Ditandatangani',
            self::ValuationOnProgress   => 'Proses Valuasi Berjalan',
            self::ValuationCompleted    => 'Proses Valuasi Selesai',
            self::PreviewReady          => 'Preview Kajian Siap',
            self::ReportPreparation     => 'Laporan Sedang Disiapkan',
            self::ReportReady           => 'Laporan Siap',
            self::CancellationReviewPending => 'Menunggu Review Pembatalan',
            self::Completed             => 'Selesai',
            self::Cancelled             => 'Dibatalkan',
        };
    }

}

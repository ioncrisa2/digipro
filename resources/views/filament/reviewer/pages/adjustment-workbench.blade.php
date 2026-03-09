<x-filament::page>
<style>
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,400;0,600;1,400&family=Syne:wght@500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap');

    /* ── Root ── */
    .aw-root {
        font-family: 'DM Sans', sans-serif;
        color: #0f172a;
    }

    /* ── Context Bar ── */
    .aw-context-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .aw-context-item {
        flex: 1 1 140px;
        padding: 12px 18px;
        border-right: 1px solid #e2e8f0;
        min-width: 130px;
    }
    .aw-context-item:last-child { border-right: none; }
    .aw-context-label {
        font-size: 9.5px;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 3px;
        font-family: 'Syne', sans-serif;
    }
    .aw-context-value {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        font-family: 'IBM Plex Mono', monospace;
    }
    .aw-context-value.large { font-size: 20px; }

    /* ── Toolbar ── */
    .aw-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #fafafa;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }
    .aw-toolbar-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        white-space: nowrap;
        font-family: 'Syne', sans-serif;
    }
    .aw-toolbar input[type="text"] {
        flex: 1;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        padding: 6px 12px;
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        background: #fff;
        color: #0f172a;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .aw-toolbar input[type="text"]:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }

    /* ── Range Summary ── */
    .aw-range-strip {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .aw-range-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
    }
    .aw-range-title {
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        font-family: 'Syne', sans-serif;
    }
    .aw-range-values {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    .aw-range-item { display: flex; flex-direction: column; gap: 2px; }
    .aw-range-item-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        font-weight: 600;
    }
    .aw-range-item-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }
    .aw-range-item-val.low { color: #dc2626; }
    .aw-range-item-val.mid { color: #0369a1; }
    .aw-range-item-val.high { color: #16a34a; }

    /* ── Section header above cards ── */
    .aw-section-label {
        font-family: 'Syne', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 2px 0 6px 2px;
    }

    /* ── Comparable Card ── */
    .aw-comp-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        transition: box-shadow 0.2s;
    }
    .aw-comp-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }

    /* Card header */
    .aw-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        user-select: none;
        gap: 12px;
    }
    .aw-card-header:hover {
        background: #f1f5f9;
    }
    .aw-card-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }
    /* Score badge — replaces rank */
    .aw-card-score {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 52px;
        padding: 4px 10px;
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        gap: 1px;
        flex-shrink: 0;
    }
    .aw-card-score-label {
        font-family: 'Syne', sans-serif;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .aw-card-score-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
    }
    .aw-card-score.score-high { border-color: #bbf7d0; background: #f0fdf4; }
    .aw-card-score.score-high .aw-card-score-val { color: #15803d; }
    .aw-card-score.score-mid  { border-color: #fde68a; background: #fffbeb; }
    .aw-card-score.score-mid  .aw-card-score-val { color: #b45309; }
    .aw-card-score.score-low  { border-color: #fecaca; background: #fef2f2; }
    .aw-card-score.score-low  .aw-card-score-val { color: #dc2626; }

    .aw-card-title {
        font-family: 'Syne', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
    }
    .aw-card-meta {
        font-size: 11px;
        color: #94a3b8;
        font-family: 'IBM Plex Mono', monospace;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .aw-card-header-right {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-shrink: 0;
    }
    .aw-card-est {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 1px;
    }
    .aw-card-est-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #94a3b8;
        font-weight: 600;
        font-family: 'Syne', sans-serif;
    }
    .aw-card-est-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 15px;
        font-weight: 700;
        color: #0369a1;
    }
    .aw-card-total-adj {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 1px;
    }
    .aw-card-total-adj-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #94a3b8;
        font-weight: 600;
        font-family: 'Syne', sans-serif;
    }
    .aw-card-total-adj-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
    }
    .aw-card-divider {
        width: 1px;
        height: 32px;
        background: #e2e8f0;
        flex-shrink: 0;
    }
    .aw-card-toggle {
        color: #94a3b8;
        transition: transform 0.25s;
        flex-shrink: 0;
    }
    .aw-comp-card.collapsed .aw-card-toggle { transform: rotate(-90deg); }

    /* Card body */
    .aw-card-body {
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .aw-comp-card.collapsed .aw-card-body {
        max-height: 0 !important;
    }

    /* Info strip inside card */
    .aw-card-info-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .aw-info-chip {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 9px 16px;
        border-right: 1px solid #f1f5f9;
        min-width: 100px;
        flex: 1 1 auto;
    }
    .aw-info-chip:last-child { border-right: none; }
    .aw-info-chip-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        font-weight: 700;
        color: #94a3b8;
        font-family: 'Syne', sans-serif;
    }
    .aw-info-chip-val {
        font-size: 12px;
        color: #334155;
        font-weight: 600;
        font-family: 'IBM Plex Mono', monospace;
    }

    /* Section divider within card */
    .aw-factor-section-header {
        padding: 7px 18px;
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        font-family: 'Syne', sans-serif;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #64748b;
    }
    .aw-factor-section-header.secondary {
        background: #f8fafc;
        color: #94a3b8;
    }

    /* Factor row */
    .aw-factor-row {
        display: grid;
        grid-template-columns: 220px 1fr 1fr 160px 130px;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        min-height: 42px;
        transition: background 0.12s;
    }
    .aw-factor-row:last-child { border-bottom: none; }
    .aw-factor-row:hover { background: #f8fafc; }
    .aw-factor-row.is-group {
        background: #f1f5f9;
        grid-template-columns: 1fr;
        min-height: 30px;
    }
    .aw-factor-row.is-total {
        background: #eff6ff;
        border-top: 2px solid #bfdbfe;
        grid-template-columns: 220px 1fr 1fr 160px 130px;
        min-height: 46px;
    }

    .aw-factor-name {
        padding: 8px 14px 8px 18px;
        font-size: 11.5px;
        font-weight: 600;
        color: #334155;
        line-height: 1.35;
    }
    .aw-factor-name.indented { padding-left: 30px; }
    .aw-factor-name.group-label {
        font-family: 'Syne', sans-serif;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        padding: 0 18px;
        display: flex;
        align-items: center;
    }
    .aw-factor-name.total-label {
        font-weight: 700;
        color: #1e40af;
        font-size: 12px;
    }

    /* Subject / Comparable value cells */
    .aw-val-cell {
        padding: 8px 12px;
        border-left: 1px solid #f1f5f9;
    }
    .aw-val-cell-inner {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .aw-val-badge {
        font-size: 12px;
        font-family: 'IBM Plex Mono', monospace;
        color: #0f172a;
        font-weight: 500;
    }
    .aw-val-cell.subject .aw-val-badge {
        color: #0369a1;
        font-weight: 600;
    }
    .aw-val-cell.comparable .aw-val-badge {
        color: #334155;
    }
    .aw-val-cell-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        font-weight: 600;
        font-family: 'Syne', sans-serif;
    }
    .aw-val-diff {
        display: inline-block;
        font-size: 10px;
        font-family: 'IBM Plex Mono', monospace;
        margin-top: 2px;
        padding: 1px 5px;
        border-radius: 4px;
        font-weight: 600;
    }
    .aw-val-diff.same {
        color: #64748b;
        background: #f1f5f9;
    }
    .aw-val-diff.diff {
        color: #92400e;
        background: #fef3c7;
    }

    /* Adjustment input cell */
    .aw-adj-cell {
        padding: 6px 12px;
        border-left: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 3px;
        align-items: flex-start;
    }
    .aw-adj-input-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .aw-adj-input {
        width: 76px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12.5px;
        font-family: 'IBM Plex Mono', monospace;
        background: #fff;
        color: #0f172a;
        text-align: right;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .aw-adj-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }
    .aw-adj-unit {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 700;
        font-family: 'Syne', sans-serif;
    }
    .aw-adj-amount {
        font-size: 10.5px;
        font-family: 'IBM Plex Mono', monospace;
        color: #64748b;
    }
    .aw-adj-amount.pos { color: #16a34a; }
    .aw-adj-amount.neg { color: #dc2626; }

    /* Total / estimated cells */
    .aw-total-cell {
        padding: 8px 14px;
        border-left: 1px solid #bfdbfe;
    }
    .aw-total-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 13px;
        font-weight: 700;
        color: #1e40af;
    }
    .aw-total-pct {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }
    .aw-est-cell {
        padding: 8px 14px;
        border-left: 1px solid #bfdbfe;
    }
    .aw-est-val {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 13px;
        font-weight: 700;
        color: #065f46;
    }

    /* Column headers inside card */
    .aw-factor-col-header {
        display: grid;
        grid-template-columns: 220px 1fr 1fr 160px 130px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        border-top: 1px solid #e2e8f0;
        padding: 0;
    }
    .aw-col-head {
        padding: 6px 12px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        font-family: 'Syne', sans-serif;
        border-left: 1px solid #e2e8f0;
    }
    .aw-col-head:first-child { border-left: none; padding-left: 18px; }
    .aw-col-head.highlight-sub { color: #0369a1; background: #f0f9ff; }
    .aw-col-head.highlight-comp { color: #92400e; background: #fffbeb; }
    .aw-col-head.highlight-adj { color: #6366f1; background: #f5f3ff; }

    /* Empty state */
    .aw-empty {
        border: 2px dashed #e2e8f0;
        border-radius: 14px;
        padding: 48px 24px;
        text-align: center;
        background: #fafafa;
    }
    .aw-empty-icon { font-size: 36px; margin-bottom: 12px; opacity: 0.35; }
    .aw-empty-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 16px; color: #374151; margin-bottom: 6px; }
    .aw-empty-desc { font-size: 13px; color: #6b7280; max-width: 380px; margin: 0 auto; line-height: 1.6; }
    .aw-empty-actions { margin-top: 18px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }

    /* Notes */
    .aw-notes {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        border-radius: 10px;
        padding: 12px 16px;
    }
    .aw-notes-title {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #92400e;
        margin-bottom: 7px;
        font-family: 'Syne', sans-serif;
    }
    .aw-notes ul { margin: 0; padding-left: 18px; list-style: disc; }
    .aw-notes li { font-size: 12.5px; color: #78350f; line-height: 1.6; }

    @media (max-width: 900px) {
        .aw-factor-row,
        .aw-factor-col-header,
        .aw-factor-row.is-total {
            grid-template-columns: 180px 1fr 1fr 140px 120px;
        }
        .aw-range-strip { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .aw-factor-row,
        .aw-factor-col-header,
        .aw-factor-row.is-total {
            grid-template-columns: 140px 1fr 1fr 130px 110px;
            font-size: 11px;
        }
    }
</style>

<div class="aw-root space-y-4">

    {{-- ── Context Bar ── --}}
    <div class="aw-context-bar">
        <div class="aw-context-item">
            <div class="aw-context-label">No. Permohonan</div>
            <div class="aw-context-value">{{ $this->contextMeta['request_number'] ?? '–' }}</div>
        </div>
        <div class="aw-context-item">
            <div class="aw-context-label">Aset Subjek</div>
            <div class="aw-context-value">#{{ $this->subjectAsset['id'] ?? '–' }}</div>
        </div>
        <div class="aw-context-item">
            <div class="aw-context-label">Jenis Aset</div>
            <div class="aw-context-value">{{ $this->subjectAsset['type'] ?? '–' }}</div>
        </div>
        <div class="aw-context-item">
            <div class="aw-context-label">Luas Tanah Subjek</div>
            <div class="aw-context-value">{{ $this->subjectAsset['land_area'] ?? '–' }}</div>
        </div>
        <div class="aw-context-item">
            <div class="aw-context-label">Pembanding</div>
            <div class="aw-context-value large">{{ count($this->matrixColumns) }}</div>
        </div>
        <div class="aw-context-item">
            <div class="aw-context-label">Guideline</div>
            <div class="aw-context-value">
                {{ $this->contextMeta['guideline'] ?? '–' }}
                <span style="font-weight:400;color:#94a3b8;">({{ $this->contextMeta['guideline_year'] ?? '–' }})</span>
            </div>
        </div>
    </div>

    {{-- ── Range Summary ── --}}
    <div class="aw-range-strip">
        <div class="aw-range-card">
            <div class="aw-range-title">Range Hasil (Rp/sqm)</div>
            <div class="aw-range-values">
                <div class="aw-range-item">
                    <span class="aw-range-item-label">Low</span>
                    <span class="aw-range-item-val low">{{ $this->rangeSummary['unit_low_text'] ?? '-' }}</span>
                </div>
                <div class="aw-range-item">
                    <span class="aw-range-item-label">Mid</span>
                    <span class="aw-range-item-val mid">{{ $this->rangeSummary['unit_mid_text'] ?? '-' }}</span>
                </div>
                <div class="aw-range-item">
                    <span class="aw-range-item-label">High</span>
                    <span class="aw-range-item-val high">{{ $this->rangeSummary['unit_high_text'] ?? '-' }}</span>
                </div>
            </div>
        </div>
        <div class="aw-range-card">
            <div class="aw-range-title">Range Hasil Aset (Total)</div>
            <div class="aw-range-values">
                <div class="aw-range-item">
                    <span class="aw-range-item-label">Low</span>
                    <span class="aw-range-item-val low">{{ $this->rangeSummary['value_low_text'] ?? '-' }}</span>
                </div>
                <div class="aw-range-item">
                    <span class="aw-range-item-label">Mid</span>
                    <span class="aw-range-item-val mid">{{ $this->rangeSummary['value_mid_text'] ?? '-' }}</span>
                </div>
                <div class="aw-range-item">
                    <span class="aw-range-item-label">High</span>
                    <span class="aw-range-item-val high">{{ $this->rangeSummary['value_high_text'] ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Add Custom Factor Toolbar ── --}}
    <div class="aw-toolbar">
        <span class="aw-toolbar-label">Faktor Tambahan</span>
        <input
            type="text"
            wire:model.defer="newCustomFactorLabel"
            placeholder="Contoh: Akses Utilitas"
        />
        <x-filament::button size="sm" color="warning" wire:click="addCustomAdjustmentFactor">
            + Tambah Faktor
        </x-filament::button>
    </div>

    {{-- ── Comparable Cards ── --}}
    @if (count($this->matrixColumns) > 0)

        @php
            /**
             * Build a lookup: rowKey → row data (for all sections)
             * so we can quickly pull subject + comparable[index] values per factor.
             */
            $rowLookup = [];
            foreach ($this->matrixSections as $section) {
                foreach ($section['rows'] as $row) {
                    if (isset($row['key']) && $row['key'] !== null) {
                        $rowLookup[$row['key']] = $row;
                    }
                }
            }

            // Keys that are adjustment inputs (not group/total display-only rows)
            $adjKeys = array_keys($this->defaultAdjustmentFactors);
            foreach ($this->customAdjustmentFactors as $cf) {
                $adjKeys[] = $cf['key'];
            }

            // Quick helper: subject vs comparable values for info strip fields
            $infoFields = [
                'land_area'          => 'Luas Tanah',
                'building_area'      => 'Luas Bangunan',
                'title_doc'          => 'Dokumen',
                'data_date'          => 'Tgl. Data',
                'distance_to_subject'=> 'Jarak',
                'likely_sale'        => 'Harga Transaksi',
                'assumed_discount'   => 'Diskon',
                'topography'         => 'Topografi',
                'land_condition'     => 'Kondisi Tanah',
                'fronting_road'      => 'Lebar Jalan',
            ];
        @endphp

        <div class="space-y-3">
        @foreach ($this->matrixColumns as $colIndex => $column)
            @php
                $comparableId  = (string) ($column['id'] ?? '');
                $computed      = $this->adjustmentComputed[$comparableId] ?? [];
                $estimatedText = $computed['estimated_unit_text'] ?? '–';
                $totalPctText  = $computed['total_percent_text'] ?? '–';
                $totalAmtText  = $computed['total_amount_text'] ?? '–';
                $cardId        = 'aw-card-' . $colIndex;

                // Score: use rank value as the score display (raw numeric rank from column)
                $scoreRaw      = $column['score'] ?? '–';
                $scoreNum      = is_numeric($scoreRaw) ? (float) $scoreRaw : null;
                // Score color tier: lower rank number = better = green
                $scoreTier     = $scoreNum === null ? '' : ($scoreNum <= 3 ? 'score-high' : ($scoreNum <= 6 ? 'score-mid' : 'score-low'));
            @endphp

            <div class="aw-comp-card" id="{{ $cardId }}">

                {{-- Card Header (clickable to collapse) --}}
                <div class="aw-card-header" onclick="awToggleCard('{{ $cardId }}')">
                    <div class="aw-card-header-left">
                        {{-- Score badge --}}
                        <div class="aw-card-score {{ $scoreTier }}">
                            <span class="aw-card-score-label">Score</span>
                            <span class="aw-card-score-val">{{ $scoreRaw }}</span>
                        </div>
                        <div>
                            <div class="aw-card-title">Data Pembanding {{ $colIndex + 1 }}</div>
                            <div class="aw-card-meta">
                                ID: {{ $column['external_id'] ?? '–' }}
                                &ensp;·&ensp;
                                {{ $column['distance_to_subject'] ?? '–' }}
                            </div>
                        </div>
                    </div>
                    <div class="aw-card-header-right">
                        <div class="aw-card-total-adj">
                            <span class="aw-card-total-adj-label">Total Adj.</span>
                            <span class="aw-card-total-adj-val">{{ $totalPctText }}</span>
                        </div>
                        <div class="aw-card-divider"></div>
                        <div class="aw-card-est">
                            <span class="aw-card-est-label">Est. Rp/sqm</span>
                            <span class="aw-card-est-val">{{ $estimatedText }}</span>
                        </div>
                        <svg class="aw-card-toggle" width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="aw-card-body">

                    {{-- Quick Info Strip --}}
                    <div class="aw-card-info-strip">
                        @foreach ($infoFields as $fieldKey => $fieldLabel)
                            @php
                                $subjectVal = $rowLookup[$fieldKey]['subject'] ?? '–';
                                $compVal    = $rowLookup[$fieldKey]['comparables'][$colIndex] ?? '–';
                            @endphp
                            <div class="aw-info-chip">
                                <span class="aw-info-chip-label">{{ $fieldLabel }}</span>
                                <span class="aw-info-chip-val" title="Subjek: {{ $subjectVal }} / Pembanding: {{ $compVal }}">
                                    {{ $compVal }}
                                    @if ($subjectVal !== $compVal && $subjectVal !== '–' && $compVal !== '–')
                                        <span style="color:#94a3b8;font-weight:400"> ← {{ $subjectVal }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>

                    {{-- ── Data Umum Section (collapsed by default, read-only reference) ── --}}
                    {{-- We show it as a collapsible sub-section --}}
                    @php $dataUmumId = 'aw-dataumum-' . $colIndex; @endphp
                    <div
                        style="border-bottom:1px solid #f1f5f9; cursor:pointer; display:flex; align-items:center; justify-content:space-between;"
                        onclick="awToggleSection('{{ $dataUmumId }}')"
                    >
                        <div class="aw-factor-section-header secondary" style="flex:1; cursor:pointer;">
                            ▸ Data Umum &amp; Referensi (Klik untuk tampilkan)
                        </div>
                    </div>
                    <div id="{{ $dataUmumId }}" style="display:none;">
                        {{-- Column headers --}}
                        <div class="aw-factor-col-header">
                            <div class="aw-col-head">Parameter</div>
                            <div class="aw-col-head highlight-sub">Objek Penilaian</div>
                            <div class="aw-col-head highlight-comp">Pembanding {{ $colIndex + 1 }}</div>
                            <div class="aw-col-head" colspan="2" style="grid-column:span 2;">—</div>
                        </div>
                        @foreach ($this->matrixSections as $section)
                            @if (in_array($section['title'], ['Data Umum', 'Land Residual Method - Reference Data (Read-Only)']))
                                @foreach ($section['rows'] as $row)
                                    @php
                                        $rowType  = $row['type'] ?? 'data';
                                        $rowKey   = $row['key'] ?? null;
                                        $subVal   = $row['subject'] ?? '–';
                                        $compVal  = $row['comparables'][$colIndex] ?? '–';
                                        $isDiff   = ($subVal !== $compVal && $subVal !== '–' && $compVal !== '–' && $subVal !== '-' && $compVal !== '-');
                                        $indent   = (int)($row['indent'] ?? 0);
                                    @endphp
                                    @if ($rowType === 'group')
                                        <div class="aw-factor-row is-group">
                                            <div class="aw-factor-name group-label">{{ $row['label'] }}</div>
                                        </div>
                                    @else
                                        <div class="aw-factor-row">
                                            <div class="aw-factor-name {{ $indent > 0 ? 'indented' : '' }}">{{ $row['label'] }}</div>
                                            <div class="aw-val-cell subject">
                                                <div class="aw-val-cell-inner">
                                                    <span class="aw-val-cell-label">Subjek</span>
                                                    <span class="aw-val-badge">{{ $subVal }}</span>
                                                </div>
                                            </div>
                                            <div class="aw-val-cell comparable">
                                                <div class="aw-val-cell-inner">
                                                    <span class="aw-val-cell-label">Pembanding</span>
                                                    <span class="aw-val-badge">{{ $compVal }}</span>
                                                    @if ($isDiff)
                                                        <span class="aw-val-diff diff">≠ beda</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="aw-val-cell" style="grid-column: span 2; border-left:1px solid #f1f5f9;"></div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    </div>

                    {{-- ── Adjustment Section ── --}}
                    <div class="aw-factor-section-header">
                        Adjustment — Element of Comparison (SPI 300)
                    </div>

                    {{-- Column headers for adjustment section --}}
                    <div class="aw-factor-col-header">
                        <div class="aw-col-head">Faktor Penyesuaian</div>
                        <div class="aw-col-head highlight-sub">Objek Penilaian</div>
                        <div class="aw-col-head highlight-comp">Pembanding {{ $colIndex + 1 }}</div>
                        <div class="aw-col-head highlight-adj">Adj. % &amp; Nilai</div>
                        <div class="aw-col-head">Rp/sqm Terkoreksi</div>
                    </div>

                    @foreach ($this->matrixSections as $section)
                        @if (str_contains($section['title'], 'Adjustment'))
                            @foreach ($section['rows'] as $row)
                                @php
                                    $rowType     = $row['type'] ?? 'data';
                                    $rowKey      = $row['key'] ?? null;
                                    $subVal      = $row['subject'] ?? '–';
                                    $compVal     = $row['comparables'][$colIndex] ?? '–';
                                    $isDiff      = ($subVal !== $compVal && $subVal !== '–' && $compVal !== '–' && $subVal !== '-' && $compVal !== '-');
                                    $indent      = (int)($row['indent'] ?? 0);

                                    $isAdjRow    = is_string($rowKey)
                                        && str_starts_with($rowKey, 'adj_')
                                        && !in_array($rowKey, ['adj_total', 'adj_estimated_unit'], true);

                                    $amountText  = data_get($this->adjustmentComputed, "{$comparableId}.factors.{$rowKey}.amount_text", '–');
                                    $pctVal      = data_get($this->adjustmentInputs, "{$comparableId}.{$rowKey}", 0);
                                    $amountPos   = is_numeric($pctVal) && $pctVal > 0;
                                    $amountNeg   = is_numeric($pctVal) && $pctVal < 0;
                                @endphp

                                @if ($rowType === 'group')
                                    <div class="aw-factor-row is-group">
                                        <div class="aw-factor-name group-label">{{ $row['label'] }}</div>
                                    </div>

                                @elseif ($rowKey === 'adj_total')
                                    <div class="aw-factor-row is-total">
                                        <div class="aw-factor-name total-label">Total Adjustment</div>
                                        <div class="aw-val-cell subject" style="grid-column: span 2;">
                                            <span style="font-size:11px;color:#64748b;">—</span>
                                        </div>
                                        <div class="aw-total-cell" style="grid-column:span 2;">
                                            <div class="aw-total-val">{{ data_get($this->adjustmentComputed, "{$comparableId}.total_amount_text", '–') }}</div>
                                            <div class="aw-total-pct">{{ data_get($this->adjustmentComputed, "{$comparableId}.total_percent_text", '–') }}</div>
                                        </div>
                                    </div>

                                @elseif ($rowKey === 'adj_estimated_unit')
                                    <div class="aw-factor-row is-total">
                                        <div class="aw-factor-name total-label">Estimated Land / Unit Value</div>
                                        <div class="aw-val-cell subject" style="grid-column: span 2;">
                                            <span style="font-size:11px;color:#64748b;">—</span>
                                        </div>
                                        <div class="aw-est-cell" style="grid-column:span 2;">
                                            <div class="aw-est-val">{{ data_get($this->adjustmentComputed, "{$comparableId}.estimated_unit_text", '–') }}</div>
                                        </div>
                                    </div>

                                @elseif ($isAdjRow && $comparableId !== '')
                                    <div class="aw-factor-row">
                                        <div class="aw-factor-name {{ $indent > 0 ? 'indented' : '' }}">{{ $row['label'] }}</div>

                                        {{-- Subject value --}}
                                        <div class="aw-val-cell subject">
                                            <div class="aw-val-cell-inner">
                                                <span class="aw-val-cell-label">Subjek</span>
                                                <span class="aw-val-badge">{{ $subVal }}</span>
                                            </div>
                                        </div>

                                        {{-- Comparable value --}}
                                        <div class="aw-val-cell comparable">
                                            <div class="aw-val-cell-inner">
                                                <span class="aw-val-cell-label">Pembanding</span>
                                                <span class="aw-val-badge">{{ $compVal }}</span>
                                                @if ($isDiff)
                                                    <span class="aw-val-diff diff">≠ perlu adj.</span>
                                                @else
                                                    <span class="aw-val-diff same">= sama</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Adjustment input --}}
                                        <div class="aw-adj-cell">
                                            <div class="aw-adj-input-wrap">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    class="aw-adj-input"
                                                    wire:model.live.debounce.300ms="adjustmentInputs.{{ $comparableId }}.{{ $rowKey }}"
                                                />
                                                <span class="aw-adj-unit">%</span>
                                            </div>
                                            <div class="aw-adj-amount {{ $amountPos ? 'pos' : ($amountNeg ? 'neg' : '') }}">
                                                {{ $amountText }}
                                            </div>
                                        </div>

                                        {{-- Corrected unit (base + this factor) --}}
                                        <div class="aw-val-cell" style="border-left:1px solid #f1f5f9;">
                                            <span style="font-size:10.5px;font-family:'IBM Plex Mono',monospace;color:#94a3b8;">
                                                {{ $amountText }}
                                            </span>
                                        </div>
                                    </div>

                                @else
                                    {{-- Plain data row fallback --}}
                                    <div class="aw-factor-row">
                                        <div class="aw-factor-name {{ $indent > 0 ? 'indented' : '' }}">{{ $row['label'] }}</div>
                                        <div class="aw-val-cell subject">
                                            <span class="aw-val-badge">{{ $subVal }}</span>
                                        </div>
                                        <div class="aw-val-cell comparable">
                                            <span class="aw-val-badge">{{ $compVal }}</span>
                                        </div>
                                        <div class="aw-val-cell" style="grid-column:span 2; border-left:1px solid #f1f5f9;"></div>
                                    </div>
                                @endif

                            @endforeach
                        @endif
                    @endforeach

                </div>{{-- /aw-card-body --}}
            </div>{{-- /aw-comp-card --}}
        @endforeach
        </div>

    @else
        {{-- ── Empty State ── --}}
        <div class="aw-empty">
            <div class="aw-empty-icon">📋</div>
            <div class="aw-empty-title">Belum ada pembanding dipilih</div>
            <div class="aw-empty-desc">
                Kartu pembanding akan muncul setelah reviewer memilih data pembanding.
                Gunakan menu <strong>Kelola Data Pembanding</strong> untuk memulai.
            </div>
            <div class="aw-empty-actions">
                <x-filament::button color="primary" size="sm">Kelola Data Pembanding</x-filament::button>
                <x-filament::button color="gray" size="sm">Cari &amp; Pilih Pembanding</x-filament::button>
            </div>
        </div>
    @endif

    {{-- ── Phase Notes ── --}}
    <div class="aw-notes">
        <div class="aw-notes-title">Catatan Fase Lanjut</div>
        <ul>
            <li>Setiap kartu pembanding menampilkan nilai Subjek vs Pembanding secara berdampingan untuk kemudahan pengisian adjustment.</li>
            <li>Baris dengan tanda <strong>≠ perlu adj.</strong> menunjukkan perbedaan nilai antara subjek dan pembanding.</li>
            <li>Range hasil low-high-mid dihitung otomatis dari nilai akhir setelah adjustment.</li>
            <li>Gunakan tombol <strong>Simpan Adjustment</strong> di header untuk menyimpan hasil ke database.</li>
            <li>Klik header kartu untuk memperkecil/memperbesar tampilan tiap pembanding.</li>
        </ul>
    </div>

</div>

<script>
    function awToggleCard(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        card.classList.toggle('collapsed');
        // Set explicit max-height for animation
        const body = card.querySelector('.aw-card-body');
        if (card.classList.contains('collapsed')) {
            body.style.maxHeight = body.scrollHeight + 'px';
            requestAnimationFrame(() => { body.style.maxHeight = '0'; });
        } else {
            body.style.maxHeight = body.scrollHeight + 'px';
            body.addEventListener('transitionend', () => { body.style.maxHeight = 'none'; }, { once: true });
        }
    }

    function awToggleSection(sectionId) {
        const el = document.getElementById(sectionId);
        if (!el) return;
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
</script>

</x-filament::page>

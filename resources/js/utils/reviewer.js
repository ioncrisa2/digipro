export const formatCurrency = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(number);
};

export const formatNumber = (value, digits = 0) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(number);
};

export const formatPercent = (value, digits = 2) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  const sign = number > 0 ? '+' : '';
  return `${sign}${formatNumber(number, digits)}%`;
};

export const formatDateTime = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
};

export const formatArea = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${formatNumber(number, 2)} m2`;
};

export const cloneDeep = (value) => JSON.parse(JSON.stringify(value ?? null));

export const statusToneClass = (statusValue) => {
  switch (statusValue) {
    case 'contract_signed':
      return 'bg-amber-100 text-amber-900 border-amber-300';
    case 'valuation_in_progress':
      return 'bg-sky-100 text-sky-900 border-sky-300';
    case 'valuation_completed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-300';
    case 'preview_ready':
      return 'bg-fuchsia-100 text-fuchsia-900 border-fuchsia-300';
    case 'report_preparation':
    case 'report_ready':
      return 'bg-cyan-100 text-cyan-900 border-cyan-300';
    case 'completed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-300';
    case 'cancelled':
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-300';
    default:
      return 'bg-stone-100 text-stone-800 border-stone-300';
  }
};


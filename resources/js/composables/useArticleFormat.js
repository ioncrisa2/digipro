export function useArticleFormat(locale = 'id-ID') {
  const formatDate = (value, emptyText = '-') => {
    if (!value) return emptyText
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)

    return new Intl.DateTimeFormat(locale, {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(date)
  }

  const stripHtml = (value) => {
    if (!value) return ''
    return String(value)
      .replace(/<[^>]*>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
  }

  const getReadTime = (content, options = {}) => {
    const text = stripHtml(content)
    const wordsPerMinute = options.wordsPerMinute ?? 200
    const suffix = options.suffix ?? ''
    const words = text ? text.split(' ').filter(Boolean).length : 0
    const minutes = Math.max(1, Math.ceil(words / wordsPerMinute))

    return suffix ? `${minutes} min ${suffix}` : `${minutes} min`
  }

  return { formatDate, getReadTime }
}

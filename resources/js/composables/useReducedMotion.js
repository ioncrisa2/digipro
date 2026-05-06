import { ref, onMounted, onBeforeUnmount } from 'vue'

export const useReducedMotion = () => {
  const prefersReducedMotion = ref(false)

  let mediaQuery = null
  const update = () => {
    prefersReducedMotion.value = Boolean(mediaQuery?.matches)
  }

  onMounted(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return

    mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    update()

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', update)
      return
    }

    mediaQuery.addListener?.(update)
  })

  onBeforeUnmount(() => {
    if (!mediaQuery) return

    if (typeof mediaQuery.removeEventListener === 'function') {
      mediaQuery.removeEventListener('change', update)
      return
    }

    mediaQuery.removeListener?.(update)
  })

  return { prefersReducedMotion }
}


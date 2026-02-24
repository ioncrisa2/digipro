import { ref } from 'vue'

// singleton: dipakai bersama oleh semua komponen
const notifications = ref([])

export function useNotification() {
  const removeNotification = (id) => {
    notifications.value = notifications.value.filter((n) => n.id !== id)
  }

  /**
   * type: 'success' | 'error' | 'warning' | 'info'
   */
  const notify = (type, message, duration = 4000) => {
    const id = Date.now() + Math.random()

    notifications.value.push({
      id,
      type,
      message,
    })

    if (duration > 0) {
      setTimeout(() => {
        removeNotification(id)
      }, duration)
    }
  }

  return {
    notifications,
    notify,
    removeNotification,
  }
}

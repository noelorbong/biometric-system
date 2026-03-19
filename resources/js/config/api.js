const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || window.location.origin).replace(/\/$/, '')

const buildApiUrl = (path = '') => {
  if (!path) {
    return API_BASE_URL
  }

  if (/^https?:\/\//i.test(path)) {
    return path
  }

  return `${API_BASE_URL}/${String(path).replace(/^\//, '')}`
}

export {
  API_BASE_URL,
  buildApiUrl,
}

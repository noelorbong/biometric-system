export function createUpdateState(updater, currentVersion, packaged, schedule = setTimeout) {
  updater.autoDownload = false
  updater.autoInstallOnAppQuit = false
  updater.allowPrerelease = false
  updater.allowDowngrade = false
  let state = { status: 'idle', current_version: currentVersion, latest_version: null, percent: 0, error: null }
  const fail = error => { state = { ...state, status: 'error', error: String(error?.message || error) } }
  const install = () => {
    if (state.status !== 'downloaded') throw new Error('The update must finish downloading before installation.')
    state = { ...state, status: 'installing' }
    schedule(() => {
      try { updater.quitAndInstall(false, true) } catch (error) { fail(error) }
    }, 1500)
  }
  updater.on('error', fail)
  updater.on('download-progress', progress => {
    state = { ...state, status: 'downloading', percent: Math.max(0, Math.min(100, progress.percent || 0)) }
  })
  return {
    status: () => ({ ...state }),
    check() {
      if (['checking', 'downloading', 'downloaded', 'installing'].includes(state.status)) return
      if (!packaged) { fail('Updates can only be checked in the installed desktop application.'); return }
      state = { ...state, status: 'checking', error: null, percent: 0 }
      updater.checkForUpdates().then(result => {
        // electron-updater determines availability using semver and channel rules.
        if (!result) throw new Error('No update information was returned.')
        state = { ...state, status: result.isUpdateAvailable ? 'available' : 'current', latest_version: result.updateInfo.version }
      }).catch(fail)
    },
    download() {
      if (state.status !== 'available') throw new Error('Check for an available update before downloading.')
      state = { ...state, status: 'downloading', percent: 0, error: null }
      updater.downloadUpdate().then(() => {
        state = { ...state, status: 'downloaded', percent: 100 }
        install()
      }).catch(fail)
    },
    install,
  }
}

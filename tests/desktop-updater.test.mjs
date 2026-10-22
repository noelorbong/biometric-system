import { test } from 'node:test'
import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import { createUpdateState } from '../resources/desktop/updateState.js'

const tick = () => new Promise(resolve => setImmediate(resolve))
function fixture() {
  const updater = new EventEmitter()
  let downloads = 0, installs = 0, finishDownload
  updater.checkForUpdates = async () => ({ isUpdateAvailable: true, updateInfo: { version: '1.0.14' } })
  updater.downloadUpdate = () => { downloads++; return new Promise(resolve => { finishDownload = resolve }) }
  updater.quitAndInstall = () => { installs++ }
  const state = createUpdateState(updater, '1.0.13', true, fn => fn())
  return { updater, state, counts: () => [downloads, installs], finish: () => finishDownload() }
}
test('checking does not download; user-started download installs automatically on success', async () => {
  const f = fixture()
  assert.equal(f.updater.autoDownload, false)
  assert.equal(f.updater.autoInstallOnAppQuit, false)
  assert.throws(() => f.state.install())
  f.state.check()
  await tick()
  assert.equal(f.state.status().status, 'available')
  assert.deepEqual(f.counts(), [0, 0])
  f.state.download()
  assert.throws(() => f.state.download())
  assert.throws(() => f.state.install())
  f.updater.emit('download-progress', { percent: 42 })
  assert.equal(f.state.status().percent, 42)
  f.finish()
  await tick()
  assert.equal(f.state.status().status, 'installing')
  assert.deepEqual(f.counts(), [1, 1])
  f.state.check()
  assert.equal(f.state.status().status, 'installing')
  assert.deepEqual(f.counts(), [1, 1])
  assert.throws(() => f.state.install())
})
test('failed checks can be retried and current versions do not offer download', async () => {
  const f = fixture()
  f.updater.checkForUpdates = async () => { throw new Error('Network unavailable') }
  f.state.check()
  await tick()
  assert.equal(f.state.status().status, 'error')
  f.updater.checkForUpdates = async () => ({ isUpdateAvailable: false, updateInfo: { version: '1.0.13' } })
  f.state.check()
  await tick()
  assert.equal(f.state.status().status, 'current')
  assert.throws(() => f.state.download())
})
test('failed downloads never enable installation', async () => {
  const f = fixture()
  f.state.check()
  await tick()
  f.updater.downloadUpdate = async () => { throw new Error('Checksum mismatch') }
  f.state.download()
  await tick()
  assert.equal(f.state.status().status, 'error')
  assert.throws(() => f.state.install())
  assert.deepEqual(f.counts(), [0, 0])
})

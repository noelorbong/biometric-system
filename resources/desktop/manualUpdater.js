import express from 'express'
import { app } from 'electron'
import { autoUpdater } from 'electron-updater'
import { createUpdateState } from './updateState'

const updater = createUpdateState(autoUpdater, app.getVersion(), app.isPackaged)
const router = express.Router()
router.get('/status', (req, res) => res.json(updater.status()))
for (const action of ['check', 'download', 'install']) {
  router.post('/' + action, (req, res) => {
    try {
      updater[action]()
      res.json(updater.status())
    } catch (error) {
      res.status(409).json({ message: error.message })
    }
  })
}
export default router

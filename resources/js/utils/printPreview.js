export const isDesktop = () => document.querySelector('meta[name="desktop-app"]')?.content === 'true'

export async function finishPrintWindow(win, { closeAfterPrint = false } = {}) {
  if (!win || win.closed) return
  await Promise.race([
    Promise.all([
      win.document.fonts?.ready,
      ...Array.from(win.document.images).map(img => img.complete ? Promise.resolve() : new Promise(resolve => {
        img.addEventListener('load', resolve, { once: true })
        img.addEventListener('error', resolve, { once: true })
      })),
    ]),
    new Promise(resolve => setTimeout(resolve, 10000)),
  ])
  if (win.closed) return
  win.focus()
  if (!isDesktop()) {
    win.print()
    if (closeAfterPrint) win.close()
    return
  }

  const doc = win.document
  const style = doc.createElement('style')
  style.textContent = `
    @media screen {
      html { background: #e2e8f0; }
      body { padding-top: 64px !important; }
      #desktop-print-toolbar { position: fixed; inset: 0 0 auto 0; z-index: 2147483647; display: flex; align-items: center; gap: 12px; padding: 12px 20px; background: #0f172a; color: white; font: 14px system-ui; }
      #desktop-print-toolbar button, #desktop-print-toolbar select { padding: 6px 12px; background: white; color: #0f172a; border: 0; border-radius: 4px; font: inherit; cursor: pointer; }
      #desktop-print-content { background: white; }
    }
    @media print {
      #desktop-print-toolbar { display: none !important; }
      #desktop-print-content { zoom: 1 !important; }
    }
  `
  doc.head.append(style)
  const content = doc.createElement('div')
  content.id = 'desktop-print-content'
  while (doc.body.firstChild) content.append(doc.body.firstChild)
  const toolbar = doc.createElement('div')
  toolbar.id = 'desktop-print-toolbar'
  toolbar.innerHTML = '<strong>Print preview</strong><button type="button">Print…</button><label>Zoom <select aria-label="Preview zoom"><option value="0.5">50%</option><option value="0.75">75%</option><option value="1" selected>100%</option><option value="1.25">125%</option></select></label><button type="button">Close</button>'
  const [print, close] = toolbar.querySelectorAll('button')
  const paperLabel = doc.createElement('label')
  paperLabel.innerHTML = 'Paper <select aria-label="Paper size"><option value="">Report default</option><option value="a4">A4</option><option value="a3">A3</option><option value="letter">Letter</option><option value="legal">Legal</option><option value="long">Long bond (8.5 × 13 in)</option></select>'
  const orientationLabel = doc.createElement('label')
  orientationLabel.innerHTML = 'Orientation <select aria-label="Paper orientation"><option value="landscape">Landscape</option><option value="portrait">Portrait</option></select>'
  toolbar.insertBefore(paperLabel, close)
  toolbar.insertBefore(orientationLabel, close)
  toolbar.style.flexWrap = 'wrap'
  const paper = paperLabel.querySelector('select')
  const orientation = orientationLabel.querySelector('select')
  orientation.disabled = true
  const paperStyle = doc.createElement('style')
  doc.head.append(paperStyle)
  const sizes = { a4: [210, 297], a3: [297, 420], letter: [215.9, 279.4], legal: [215.9, 355.6], long: [215.9, 330.2] }
  // Keep each report's margins; only replace its page size when selected.
  let margin = 0
  for (const sheet of Array.from(doc.styleSheets)) {
    for (const rule of Array.from(sheet.cssRules || [])) {
      if (rule.type === 6) {
        const value = rule.style.getPropertyValue('margin')
        if (/^\d+(\.\d+)?mm$/.test(value.trim())) margin = parseFloat(value)
      }
    }
  }
  const applyPaper = () => {
    orientation.disabled = !paper.value
    if (!paper.value) { paperStyle.textContent = ''; return }
    const [short, long] = sizes[paper.value]
    const [width, height] = orientation.value === 'portrait' ? [short, long] : [long, short]
    paperStyle.textContent = `
      @page { size: ${width}mm ${height}mm; }
      @media screen { #desktop-print-content { width: ${width - margin * 2}mm; min-height: ${height - margin * 2}mm; margin: 16px auto; } }
      #desktop-print-content .page-wrapper, #desktop-print-content .cut-line-h { width: 100%; }
      #desktop-print-content .form-copy { width: 25%; height: auto; }
    `
  }
  paper.onchange = applyPaper
  orientation.onchange = applyPaper
  print.onclick = () => win.print()
  close.onclick = () => win.close()
  toolbar.querySelector('select').onchange = event => { content.style.zoom = event.target.value }
  doc.body.append(toolbar, content)
  const toolbarSize = new win.ResizeObserver(() => {
    style.textContent = style.textContent.replace(/padding-top: [\d.]+px !important/, `padding-top: ${toolbar.getBoundingClientRect().height + 12}px !important`)
  })
  toolbarSize.observe(toolbar)
  win.addEventListener('beforeunload', () => toolbarSize.disconnect(), { once: true })
}

export function printTable(table, title) {
  if (!table) return
  const win = window.open('', '_blank', 'width=1400,height=900')
  if (!win) { window.alert('Allow pop-ups to open the print preview.'); return }
  const clone = table.cloneNode(true)
  clone.querySelectorAll('button').forEach(button => button.replaceWith(document.createTextNode(button.textContent)))
  win.document.write('<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 landscape;margin:10mm}body{font:11px Arial;color:#111;margin:0}h1{font-size:18px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #aaa;padding:5px;text-align:left}thead{display:table-header-group}tr{break-inside:avoid}</style></head><body></body></html>')
  win.document.close()
  win.document.title = title
  const heading = win.document.createElement('h1')
  heading.textContent = title
  win.document.body.append(heading, clone)
  void finishPrintWindow(win)
}

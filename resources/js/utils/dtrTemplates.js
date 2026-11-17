// The component styles are shared with the isolated print-preview document.
import componentStyles from './dtrTemplateTwo.css?raw'

export const templateTwoPrintStyles = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: white; color: black; }
  @page { size: A4 landscape; margin: 5mm; }
  .page-wrapper {
    display: flex;
    align-items: flex-start;
    width: 287mm;
    gap: 3mm;
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .page-wrapper + .page-wrapper { break-before: page; page-break-before: always; }
  .form-copy { width: 142mm; flex: 0 0 142mm; break-inside: avoid; }
  ${componentStyles}
`

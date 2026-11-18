// Fika invoices: every INV-*.html in a folder becomes an A4 PDF next to it, rendered by Chromium (Playwright).
// Usage: node render_pdf.js <folder> [INV-1616.html ...]
// The page footer carries the company, the invoice number, the contact line and "Page x of y".
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

(async () => {
  const dir = path.resolve(process.argv[2] || '.');
  const files = process.argv.length > 3 ? process.argv.slice(3)
    : fs.readdirSync(dir).filter(f => /^INV-.*\.html$/.test(f)).sort();
  const browser = await chromium.launch();
  const page = await browser.newPage();
  let n = 0;
  for (const f of files) {
    await page.goto('file://' + path.join(dir, f), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    const [inv, company, contact] = await page.evaluate(() => ['fika-invoice', 'fika-company', 'fika-contact']
      .map(k => (document.querySelector(`meta[name="${k}"]`) || {}).content || ''));
    await page.pdf({
      path: path.join(dir, f.replace(/\.html$/, '.pdf')),
      format: 'A4', printBackground: true, preferCSSPageSize: true,
      displayHeaderFooter: true,
      headerTemplate: '<span></span>',   // page 2 of a long invoice opens with its own "continued" line (make_invoice.py)
      footerTemplate: `<div style="box-sizing:border-box;width:100%;padding:0 14mm;display:flex;justify-content:space-between;`
        + `gap:6mm;font-family:Inter,'Helvetica Neue',Arial,sans-serif;font-size:6.8pt;line-height:1.3;color:#7a86a1">`
        + `<span>${esc(company)} · ${esc(inv)} · ${esc(contact)}</span>`
        + `<span style="white-space:nowrap">Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>`,
    });
    n++;
  }
  await browser.close();
  console.log(`rendered ${n} PDF(s) in ${dir}`);
})();

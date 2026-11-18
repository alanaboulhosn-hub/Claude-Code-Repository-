// Layout check for Fika invoices (the HTML that make_invoice.py writes), laid out by Chromium for print on A4:
//  - no text overlaps other text or the logo,
//  - nothing sticks out of its box, the table or the page width (fields kept on one line must fit their box),
//  - product names or customer lines that take more than one line are listed (allowed, shown for review),
//  - with --pdf: pages of every INV-*.pdf in the folder (pdfinfo); a last page without invoice lines fails.
// Usage: node check_invoices.js <folder> [--pdf] [--json report.json]
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

(async () => {
  const dir = path.resolve(process.argv[2] || '.');
  const withPdf = process.argv.includes('--pdf');
  const jsonOut = process.argv.includes('--json') ? process.argv[process.argv.indexOf('--json') + 1] : null;
  const files = fs.readdirSync(dir).filter(f => /^INV-.*\.html$/.test(f)).sort();
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 794, height: 1123 } });   // A4 at 96 dpi
  await page.emulateMedia({ media: 'print' });
  const report = [];
  for (const f of files) {
    await page.goto('file://' + path.join(dir, f), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    const r = await page.evaluate(() => {
      const issues = [], info = [];
      const label = el => `${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).trim().replace(/\s+/g, '.') : ''}`
        + ` "${el.textContent.trim().replace(/\s+/g, ' ').slice(0, 48)}"`;
      // 1. overlaps: every line fragment of every text node, and the logo
      const boxes = [];
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
      let node, id = 0;
      while ((node = walker.nextNode())) {
        if (!node.textContent.trim()) continue;
        const range = document.createRange();
        range.selectNodeContents(node);
        id++;
        for (const b of range.getClientRects()) if (b.width > 0.5 && b.height > 0.5) boxes.push({ id, b, el: node.parentElement });
      }
      for (const img of document.querySelectorAll('img')) boxes.push({ id: ++id, b: img.getBoundingClientRect(), el: img });
      for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) {
        const a = boxes[i], c = boxes[j];
        if (a.id === c.id) continue;
        const w = Math.min(a.b.right, c.b.right) - Math.max(a.b.left, c.b.left);
        const h = Math.min(a.b.bottom, c.b.bottom) - Math.max(a.b.top, c.b.top);
        if (w > 0.6 && h > 0.6) issues.push(`text overlap: ${label(a.el)} / ${label(c.el)}`);
      }
      // 2. overflow: any box whose content is wider than the box (only the innermost one is reported)
      const over = [];
      for (const el of document.querySelectorAll('body *')) {
        const cs = getComputedStyle(el);
        if (cs.display === 'inline' || cs.display === 'none' || el.tagName === 'IMG' || !el.clientWidth) continue;
        if (el.scrollWidth > el.clientWidth + 1 && cs.overflowX === 'visible') over.push(el);
      }
      for (const el of over) if (!over.some(o => o !== el && el.contains(o)))
        issues.push(`overflow ${el.scrollWidth - el.clientWidth}px: ${label(el)}`);
      if (document.documentElement.scrollWidth > 794) issues.push(`page wider than A4: ${document.documentElement.scrollWidth}px`);
      // 3. text past the page's side margins (14 mm = 53 px), other than the full-width pink band
      for (const bx of boxes) if (!bx.el.closest('.band') && (bx.b.left < 52 || bx.b.right > 794 - 52))
        issues.push(`outside the margins: ${label(bx.el)}`);
      // 4. allowed to wrap, listed for review
      const lines = el => { const r = document.createRange(); r.selectNodeContents(el);
        return new Set([...r.getClientRects()].filter(b => b.width > 0.5).map(b => Math.round(b.bottom))).size; };
      for (const el of document.querySelectorAll('.items .nm, .cust .ln, .notes dd, .pay p')) {
        const n = lines(el);
        if (n > 1) info.push(`${n} lines: ${label(el)}`);
      }
      return { issues, info, rows: document.querySelectorAll('.items tbody tr').length };
    });
    report.push({ file: f, ...r });
  }
  await browser.close();
  if (withPdf) {
    for (const x of report) {
      const pdf = path.join(dir, x.file.replace(/\.html$/, '.pdf'));
      if (!fs.existsSync(pdf)) { x.issues.push('no PDF'); continue; }
      x.pages = +execFileSync('pdfinfo', [pdf]).toString().match(/Pages:\s+(\d+)/)[1];
      if (x.pages > 1) {
        const last = execFileSync('pdftotext', ['-f', String(x.pages), '-l', String(x.pages), '-layout', pdf, '-']).toString();
        x.last_page = last.replace(/\s+/g, ' ').trim().slice(0, 160);
        if (!/DESCRIPTION/.test(last)) x.issues.push(`page ${x.pages} has no invoice lines, only the end of the invoice`);
      }
    }
  }
  const bad = report.filter(x => x.issues.length);
  console.log(`${report.length} invoice(s) checked: ${bad.length} with layout problems`);
  for (const x of bad) for (const i of x.issues.slice(0, 8)) console.log(`FAIL ${x.file}: ${i}`);
  const infos = report.filter(x => x.info.length);
  console.log(`${infos.length} invoice(s) with a field on more than one line (allowed):`);
  for (const x of infos.slice(0, 15)) console.log(`  ${x.file}: ${x.info.join(' | ')}`);
  if (withPdf) {
    const by = {};
    for (const x of report) (by[x.pages] = by[x.pages] || []).push(x);
    for (const p of Object.keys(by).sort()) {
      const rows = by[p].map(x => x.rows);
      console.log(`${p} page(s): ${by[p].length} invoice(s), ${Math.min(...rows)} to ${Math.max(...rows)} lines`);
    }
    for (const x of report.filter(x => x.pages > 1)) console.log(`  ${x.file} (${x.rows} lines), last page: ${x.last_page}`);
  }
  if (jsonOut) fs.writeFileSync(jsonOut, JSON.stringify(report, null, 1));
  process.exitCode = bad.length ? 1 : 0;
})();

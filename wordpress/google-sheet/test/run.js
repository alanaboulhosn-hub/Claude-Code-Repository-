// Runs the real Apps Script file against a stand-in spreadsheet.
// node run.js <state.json> <changes.json> <function> <now ISO> <orders.json>
const fs = require('fs'), vm = require('vm'), cp = require('child_process');
const _now = Date.parse(process.argv[5]); Date.now = () => _now;
const [stateFile, changesFile, fn, nowIso, ordersFile] = process.argv.slice(2);
const state = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
const changes = {};
const TZ = 'Asia/Beirut';

// dates: a JS Date is an instant; the sheet shows Beirut wall time
function wall(d) {
  const p = {};
  new Intl.DateTimeFormat('en-GB', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
    .formatToParts(d).forEach(x => (p[x.type] = x.value));
  return p;
}
function fromWall(s) { // 'YYYY-MM-DDTHH:MM' Beirut -> Date
  const guess = new Date(s + ':00Z');
  const w = wall(guess);
  const asUtc = Date.UTC(+w.year, w.month - 1, +w.day, +w.hour, +w.minute);
  return new Date(guess.getTime() - (asUtc - guess.getTime()));
}
const Utilities = {
  formatDate(d, tz, f) {
    const w = wall(d);
    return f.replace('yyyy', w.year).replace('MM', w.month).replace('dd', w.day).replace('HH', w.hour).replace('mm', w.minute);
  },
  sleep(ms) { console.log('sleep ' + ms); },
  parseDate(s, tz, f) { return fromWall(f === 'yyyy-MM-dd' ? s + 'T00:00' : s.replace(' ', 'T')); }
};
const dec = v => (v && typeof v === 'object' && v.$date ? fromWall(v.$date) : v == null ? '' : v);
const enc = v => (v instanceof Date ? { $date: Utilities.formatDate(v, TZ, 'yyyy-MM-ddTHH:mm') } : v);

function col(s) { let n = 0; for (const ch of s) n = n * 26 + ch.charCodeAt(0) - 64; return n; }
function name(c) { let s = ''; while (c) { const m = (c - 1) % 26; s = String.fromCharCode(65 + m) + s; c = (c - m - 1) / 26; } return s; }

function Sheet(title) {
  const cells = state[title] || (state[title] = {});
  const get = (r, c) => cells[name(c) + r] || ['', ''];
  const put = (r, c, v, f) => {
    const k = name(c) + r;
    cells[k] = [v, f];
    (changes[title] = changes[title] || {})[k] = [enc(v), f];
  };
  const lastRow = () => Object.keys(cells).reduce((m, k) => {
    const [v, f] = cells[k];
    return v === '' && !f ? m : Math.max(m, +k.match(/\d+/)[0]);
  }, 0);
  function Range(r, c, nr, nc) {
    return {
      getValues() { const o = []; for (let i = 0; i < nr; i++) { const row = []; for (let j = 0; j < nc; j++) row.push(dec(get(r + i, c + j)[0])); o.push(row); } return o; },
      getValue() { return dec(get(r, c)[0]); },
      getFormula() { return get(r, c)[1] || ''; },
      setValues(v) {
        if (v.length !== nr || v.some(x => x.length !== nc)) throw new Error('setValues size mismatch');
        v.forEach((row, i) => row.forEach((x, j) => put(r + i, c + j, x, ''))); return this;
      },
      setFormula(f) { put(r, c, '', f); return this; },
      clearContent() { for (let i = 0; i < nr; i++) for (let j = 0; j < nc; j++) put(r + i, c + j, '', ''); return this; },
      setFontWeight() { return this; }, setNumberFormat() { return this; }
    };
  }
  return {
    getName: () => title,
    getLastRow: lastRow,
    setFrozenRows() {},
    getRange(a, b, nr, nc) {
      if (typeof a === 'number') return Range(a, b, nr || 1, nc || 1);
      const m = a.match(/^([A-Z]+)(\d*)(?::([A-Z]+)(\d*))?$/);
      const r1 = m[2] ? +m[2] : 1, r2 = m[4] ? +m[4] : (m[2] && !m[3] ? r1 : Math.max(lastRow(), 1));
      return Range(r1, col(m[1]), r2 - r1 + 1, col(m[3] || m[1]) - col(m[1]) + 1);
    }
  };
}
const ss = {
  getSpreadsheetTimeZone: () => TZ,
  getSheetByName: t => (state[t] ? Sheet(t) : null),
  insertSheet: t => { state[t] = {}; changes[t] = changes[t] || {}; return Sheet(t); }
};
const log = [];
const triggers = [];
const sandbox = {
  SpreadsheetApp: { getActive: () => ss, flush() {} },
  Utilities,
  LockService: { getScriptLock: () => ({ tryLock: () => true, releaseLock() {} }) },
  ScriptApp: {
    getProjectTriggers: () => triggers,
    deleteTrigger() {},
    newTrigger(h) {
      const t = { h }; const b = {
        timeBased: () => b, atHour: x => ((t.hour = x), b), nearMinute: x => ((t.min = x), b),
        everyDays: x => ((t.every = x), b), inTimezone: x => ((t.tz = x), b), create: () => (log.push('trigger ' + JSON.stringify(t)), t)
      };
      return b;
    }
  },
  UrlFetchApp: {
    fetch(url, opt) {
      const q = new URL(url).searchParams;
      log.push('fetch ' + q.get('from') + '..' + q.get('to'));
      let body, code = 200;
      try {
        body = cp.execFileSync('php', ['harness.php', ordersFile, q.get('from'), q.get('to')], { env: { ...process.env, HDR: opt.headers['X-Fika-Key'] }, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
      } catch (e) { code = 401; body = e.stdout.toString(); }
      return { getResponseCode: () => code, getContentText: () => body };
    }
  },
  Date,
  Math, JSON, String, Error
};
let src = fs.readFileSync('code.gs', 'utf8');
vm.createContext(sandbox);
vm.runInContext(src + '\n;this.__run = function(x){ return eval(x); }', sandbox);
fn.split(';').forEach(x => sandbox.__run(x));
fs.writeFileSync(changesFile, JSON.stringify(changes));
console.log(log.join('\n'));
Object.keys(changes).forEach(t => console.log('changed', t, Object.keys(changes[t]).length, 'cells'));

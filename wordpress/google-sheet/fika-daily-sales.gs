/**
 * Fika: nightly candy sales into the FIKA sheet (Extensions > Apps Script, file Code.gs).
 * - Every night at about 00:30 Beirut: asks the shop for the kilograms of each candy ordered in the last 7 days
 *   (snippet "Fika: daily candy sales for the FIKA sheet", wordpress/snippets/fika-daily-sales.php) and rewrites those
 *   days on the Daily Sales tab: Date | Candy | Sold (kg) | Orders. Rewriting means a day is never counted twice and an
 *   order cancelled later drops out by itself.
 * - Then adds one Stock Log row per candy (Source "nightly orders") with the Inventory tab's Ordered and Est. Quant.,
 *   so the Inventory System's recent demand rate comes from real sales.
 * - setup(), run once by hand: makes the Daily Sales tab, fills it from the day the shop went live, adds "Sold since
 *   count" (column N) to the Inventory tab, makes Est. Quant. = In Boxes + On table - Sold since count, has the
 *   Inventory System's projected stock use the same, and starts the nightly run.
 * - If a run fails, Google emails the sheet's owner; the next night fills the gap.
 * Paste the key from the shop in place of PASTE_KEY_HERE (never commit the real key).
 */

var SHOP_URL = 'https://swedishfikalb.com/wp-json/fika/v1/daily-sales';
var SHOP_KEY = 'PASTE_KEY_HERE';
var FIRST_DAY = '2026-10-09'; // the shop went live
var DAYS_REWRITTEN = 7;
var SALES = 'Daily Sales';
var TZ = 'Asia/Beirut';

// Beirut day, n days before today, as YYYY-MM-DD
function fikaDay_(n) {
  return Utilities.formatDate(new Date(Date.now() - n * 864e5), TZ, 'yyyy-MM-dd');
}

function fikaFetch_(from, to) {
  var res = UrlFetchApp.fetch(SHOP_URL + '?from=' + from + '&to=' + to, {
    headers: { 'X-Fika-Key': SHOP_KEY },
    muteHttpExceptions: true
  });
  if (res.getResponseCode() !== 200) {
    throw new Error('Shop answered ' + res.getResponseCode() + ': ' + res.getContentText().slice(0, 300));
  }
  return JSON.parse(res.getContentText()).rows;
}

// rewrite the days from..to (YYYY-MM-DD) on the Daily Sales tab
function fikaWriteDays_(from, to) {
  var ss = SpreadsheetApp.getActive();
  var tz = ss.getSpreadsheetTimeZone();
  var sh = ss.getSheetByName(SALES);
  var rows = fikaFetch_(from, to);
  var keep = [];
  if (sh.getLastRow() > 1) {
    sh.getRange(2, 1, sh.getLastRow() - 1, 4).getValues().forEach(function (r) {
      if (r[0] === '') return;
      var d = r[0] instanceof Date ? Utilities.formatDate(r[0], tz, 'yyyy-MM-dd') : String(r[0]);
      if (d < from || d > to) keep.push([d, r[1], r[2], r[3]]);
    });
  }
  rows.forEach(function (r) { keep.push([r.date, r.candy, r.kg, r.orders]); });
  keep.sort(function (a, b) { return a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : String(a[1]).localeCompare(String(b[1])); });
  var out = keep.map(function (r) { return [Utilities.parseDate(r[0], tz, 'yyyy-MM-dd'), r[1], r[2], r[3]]; });
  if (sh.getLastRow() > 1) sh.getRange(2, 1, sh.getLastRow() - 1, 4).clearContent();
  if (out.length) sh.getRange(2, 1, out.length, 4).setValues(out);
  return rows.length;
}

// one Stock Log row per candy for the end of yesterday, once
function fikaLogStock_() {
  var ss = SpreadsheetApp.getActive();
  var tz = ss.getSpreadsheetTimeZone();
  var log = ss.getSheetByName('Stock Log');
  var inv = ss.getSheetByName('Inventory');
  var day = fikaDay_(1);
  var stamp = Utilities.parseDate(day + ' 23:59', tz, 'yyyy-MM-dd HH:mm');
  if (log.getLastRow() > 1) {
    var done = log.getRange(2, 1, log.getLastRow() - 1, 5).getValues().some(function (r) {
      return r[4] === 'nightly orders' && r[0] instanceof Date && Utilities.formatDate(r[0], tz, 'yyyy-MM-dd') === day;
    });
    if (done) return;
  }
  SpreadsheetApp.flush();
  var add = [];
  inv.getRange(3, 1, inv.getLastRow() - 2, 5).getValues().forEach(function (r) {
    if (r[0] !== '' && typeof r[4] === 'number') add.push([stamp, r[0], r[1], Math.round(r[4] * 1000) / 1000, 'nightly orders']);
  });
  if (add.length) log.getRange(log.getLastRow() + 1, 1, add.length, 5).setValues(add);
}

// the nightly run
function nightly() {
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(60000)) return;
  try {
    fikaWriteDays_(fikaDay_(DAYS_REWRITTEN), fikaDay_(1));
    fikaLogStock_();
  } finally {
    lock.releaseLock();
  }
}

// run once by hand
function setup() {
  var ss = SpreadsheetApp.getActive();
  var sh = ss.getSheetByName(SALES) || ss.insertSheet(SALES);
  sh.getRange(1, 1, 1, 4).setValues([['Date', 'Candy', 'Sold (kg)', 'Orders']]).setFontWeight('bold');
  sh.setFrozenRows(1);
  sh.getRange('A:A').setNumberFormat('dd mmm yyyy');
  sh.getRange('C:C').setNumberFormat('0.00');

  // fill from the day the shop went live, in pieces of up to 100 days
  var day = FIRST_DAY, last = fikaDay_(1);
  while (day <= last) {
    var end = Utilities.formatDate(new Date(Utilities.parseDate(day, TZ, 'yyyy-MM-dd').getTime() + 99 * 864e5), TZ, 'yyyy-MM-dd');
    fikaWriteDays_(day, end < last ? end : last);
    day = Utilities.formatDate(new Date(Utilities.parseDate(end, TZ, 'yyyy-MM-dd').getTime() + 864e5), TZ, 'yyyy-MM-dd');
  }

  // Inventory tab: N = Sold since count, E = In Boxes + On table - Sold since count
  var inv = ss.getSheetByName('Inventory');
  inv.getRange('N1:N2').setValues([['Sold since count (kg)'], ['(from orders)']]);
  var n = inv.getLastRow();
  for (var r = 3; r <= n; r++) {
    if (inv.getRange(r, 1).getValue() === '') continue;
    // the candy's count date on the Inventory System tab (column Q there, else Last full count date)
    inv.getRange(r, 14).setFormula('=IFERROR(SUMIFS(\'' + SALES + '\'!$C:$C,\'' + SALES + '\'!$B:$B,$A' + r +
      ',\'' + SALES + '\'!$A:$A,">"&IFERROR(INDEX(\'Inventory System\'!$AD$53:$AD$97,MATCH($A' + r +
      ',\'Inventory System\'!$A$53:$A$97,0)),\'Inventory System\'!$B$8)),0)');
    var e = inv.getRange(r, 5);
    if (e.getFormula().replace(/\s/g, '') === '=D' + r + '+C' + r) e.setFormula('=D' + r + '+C' + r + '-N' + r);
  }
  inv.getRange('N3:N' + n).setNumberFormat('0.00');

  // Inventory System: projected stock today = stock at count - sold since the count (was: minus a forecast)
  var sys = ss.getSheetByName('Inventory System');
  for (var s = 53; s <= 97; s++) {
    var d = sys.getRange(s, 4);
    if (d.getFormula().indexOf('$AI' + s + '*$B$45*MAX(0,TODAY()-$AD' + s + ')') === -1) continue;
    d.setFormula('=IF($A' + s + '="","",IF($R' + s + '<>"",0,MAX(0,$AC' + s + '-SUMIFS(\'' + SALES + '\'!$C:$C,\'' + SALES +
      '\'!$B:$B,$A' + s + ',\'' + SALES + '\'!$A:$A,">"&$AD' + s + '))))');
  }

  // the nightly run, about 00:30 Beirut
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'nightly') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('nightly').timeBased().atHour(0).nearMinute(30).everyDays(1).inTimezone(TZ).create();
}

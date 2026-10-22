# FIKA sheet: nightly candy sales

Each night at about 00:30 (Beirut), the FIKA Google Sheet gets the kilograms of each candy ordered on the shop, and the
Inventory tab's Est. Quant. goes down by what was sold since your last count.

## Putting the script in the sheet (once, about 5 minutes)

Claude sends you `fika-sheet-script.gs`, the script with the shop's key already filled in.

1. Open the **FIKA** sheet on a computer. Menu **Extensions → Apps Script**. A new tab opens.
2. Click into the code box, select everything in it (Ctrl+A / Cmd+A) and delete it. Paste the whole script.
3. Click the **Save** icon (the floppy disk). Name the project **Fika daily sales** if Google asks.
4. In the bar above the code, pick **setup** in the function menu, then click **Run**.
5. Google asks for permission ("Authorization required"): **Review permissions** → pick your Google account →
   "Google hasn't verified this app" → **Advanced** → **Go to Fika daily sales (unsafe)** → **Allow**.
   It is your own script; Google shows this for every script that is not published.
6. Wait for **Execution completed** at the bottom (under a minute). Go back to the sheet.

## What setup did

- A new tab, **Daily Sales**: Date, Candy, Sold (kg), Orders, from 9 October 2026 (the day the shop went live).
- **Inventory** tab, new column **N, "Sold since count (kg)"**: what each candy sold after its last count date.
  **Est. Quant.** (E) is now In Boxes + On table − Sold since count.
- **Inventory System** tab: **Projected stock today** (D) is the stock at your count minus what was actually sold
  (it used to subtract a forecast).
- The nightly run is switched on. Every night it also adds one row per candy to **Stock Log** ("nightly orders").

## Day to day

- **Counting stock:** type the new In Boxes and On table numbers as before, and set **Last full count date** on the
  Inventory System tab (or that candy's column Q there) to the day of the count. Sold since count starts again from
  zero after that date. Count in the evening, after the day's packing.
- **A delivery from ERT:** add it to In Boxes and Ordered Quantity, as before.
- **A new candy in the shop:** tell Claude; it adds the candy to the shop's list and you add its row on the sheet.
- **If a night fails** (the shop is down, say), Google emails you. Nothing to do: each night rewrites the last 7 days,
  so the next night fills the gap.
- Don't rename the Daily Sales, Inventory, Inventory System or Stock Log tabs, or the candy names in column A.

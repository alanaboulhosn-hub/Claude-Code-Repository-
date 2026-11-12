---
name: fika-batch-order
description: Plan Fika's next candy batch from ERT (Sweden): how many kilograms of each candy to order so that every candy runs out on about the same day, and when to reorder. Use when the owner asks for an order sheet, a reorder, "how much of each candy", a stock check before ordering, or when to order the next batch.
---

# Fika batch order (inventory specialist SOP)

**Goal:** one order sheet where every candy is planned to reach 0 stock on about the same day, so one reorder covers
the whole shop. Each candy's kilograms follow its real use (loose sales + Ready Mix use + losses), projected with the
growth and season the owner expects, minus the stock it will still have when the batch lands.

**Before you start, read** `references/owner-and-lessons.md` (how the owner works, and the corrections that shaped
this method), `references/data-quirks.md` (how to reach each source and its traps), and
`references/worked-example.md` (batch 3 numbers: your regression test).

## Ground rules

- Speak to the owner in **kilograms only**. Packs/bags appear only in the Excel for ERT.
- Every candy on sale gets at least one ERT pack, unless the owner leaves it out.
- Owner-set amounts are fixed; the rest is re-split. A new candy inside a fixed batch takes kilograms from the
  candies with the most time left.
- Separate data from estimates in everything you say. Show ranges for estimates; let the owner decide.
- Read-only on the shop and on the owner's sheets. Order JSON and sheet exports contain customer data: scratchpad
  only, never git. Do not contact the supplier.

## Phase 1: Questions for the owner (ask once, in one message)

Ask only what the data cannot tell you:
1. Batch size (kg) and freight (air about 10 days door to shelf, sea about 70). When will they order?
2. What is coming: promotions, ads, Christmas or other campaigns, price changes, new candies, candies dropped.
3. Wholesale / B2B kilograms (only if confirmed; the owner caps it).
4. Supplier risks: candies that may be unavailable.
5. Is there a stock count newer than the one on the Inventory tab? (Ask for one if older than a week.)
6. The Ready Mix recipes (grams of each candy per bag), if not known yet.
7. Which lines of the last supplier order were actually delivered.

Defaults if they do not answer: growth 10% a month, December x1.5 if promotions are planned (else x1.3),
losses x1.10, wholesale 0.

## Phase 2: Collect and verify the data

1. **Orders.** `scripts/fetch_orders.py <date the current batch arrived> orders.json`.
2. **Products.** Categories (sweet / sour / ready-mix), hidden or out-of-stock candies, new product ids. Add any new
   candy to `candies` in the config (product id -> FIKA sheet name, shop name, group, ERT code, pack size, price).
3. **FIKA sheet.** Download as xlsx (see data-quirks), `scripts/read_inventory.py FIKA.xlsx inventory.json`.
   Check: count date, total kg, no negative stock, every candy in the config has a row.
4. **FIKA Orders.** What arrived in each batch, pack sizes, prices, landed cost per kg (add VAT if blank).
5. **Verify the order kilograms three ways** for the window (`scripts/verify_orders.py orders.json <from> <to>`):
   weight label, quantity, price paid. They must agree line by line; investigate any line that does not.
6. **Reconcile the stock:** kg received (Inventory "Ordered Quantity") minus the latest count, vs website kilograms
   (completed + processing) + PR + atypical + sampling + gifts. A difference over about 5% must be explained before
   you plan (count error, missing receipt, candy leaving unrecorded). Cross-check atypical names against website
   orders so nothing counts twice.
7. **Sell-out dates:** from Inventory (B - E) / KG/Day, Inventory System "Out of stock since", and the last order
   date per candy. Note candies that were out of stock during the window.

## Phase 3: Demand per candy (kg a day, today's level)

1. **Window = since the current batch arrived.** Not 30 days: launches and restocks need the whole batch.
2. **Loose demand** = loose kg sold / days the candy was on sale in the window.
3. **No sales this batch** (hidden, sold out, returning): share of loose sales when last on sale (on its days on
   sale), applied to today's loose sales. Flag as an estimate. If that period was a launch week, say the share is
   likely inflated and show the range (as sold then / as your current best seller).
4. **Ready Mix use** (always include): each mix's kg in the window spread over its candies (equal shares unless the
   recipe is known). Fan Favourites at its rate after the launch week (exclude the first 7 days of any new product),
   300 g over every candy + 100 g of each of its two special candies. Replaced or ended mixes are not counted.
5. **Sanity check against the last 7 days.** If the last week differs from the window average by more than 25%,
   report it with the reason you can see (launch peaks, sell-outs, holidays). It changes how long the batch lasts.

## Phase 4: Plan the order (`scripts/plan_order.py`)

1. Fill the config (copy `scripts/config.batch3.json`): today, count date, arrival, window, batch_kg, projection,
   fixed_kg, shares, mix use, candies.
2. The script: use x (1 + losses) x growth (compounded monthly from today) x season; stock on arrival = count minus
   use until arrival; finds the common run-out day T that fills the batch in nearest whole packs (minimum one);
   fixed amounts stay; fine-tunes to the batch weight without pulling any candy more than a week off T.
3. **Reorder date** = T minus lead time minus 7 days (send it to the owner as a date).
4. `scripts/render_view.py` -> HTML for the owner (kg only, run-out date per candy, estimates in yellow).

## Phase 5: Review before you present (every item, every time)

- [ ] Every line: ERT code, pack size from the latest real order, a plausible price (SEK per kg 40-90).
- [ ] Mix kilograms = bags x 500 g; loose + mix totals match the order data.
- [ ] Owner-fixed amounts are exactly as set (nearest pack).
- [ ] Candies more than a week off T listed with the reason (pack size, stock already high, fixed amount).
- [ ] Landed cost includes freight, customs and VAT; give the total.
- [ ] Last 7 days vs window average reported if more than 25% apart.
- [ ] Supplier risks and unconfirmed wholesale stated with their effect in kg and days.
- [ ] Worked example still reproduces (after any script change).
- [ ] The view and the Excel come from the same run.

## Phase 6: Present to the owner

Send the HTML (display "render") and the Excel (attach). Then, in kilograms:
1. One line: total kg, landed cost, the common run-out date, the reorder date.
2. The biggest lines (5-6 candies) and anything they asked to change, before vs after.
3. Candies outside the common date and why.
4. What it depends on (estimates and risks), each with what happens if it is wrong.
5. What you need from them to firm it up (fresh count, supplier confirmation, recipes).
If they ask how sure you are: confident about the split (it follows data), less about timing (growth, season).

## Commands

```
cd .claude/skills/fika-batch-order/scripts          # $SCRATCH = the session scratchpad
python3 fetch_orders.py 2026-09-28 $SCRATCH/orders.json
python3 verify_orders.py $SCRATCH/orders.json 2026-09-28 2026-11-11
python3 read_inventory.py $SCRATCH/FIKA.xlsx $SCRATCH/inventory.json
cp config.batch3.json $SCRATCH/config.json           # then edit it for the new batch
python3 plan_order.py $SCRATCH/config.json $SCRATCH/orders.json $SCRATCH/inventory.json $SCRATCH/order.xlsx
python3 render_view.py $SCRATCH/order.xlsx.json $SCRATCH/order.html
```

## After the order

- When the batch arrives: switch on returning candies on the shop (with the owner's OK), record what arrived (kg per
  candy) in FIKA Orders and the Inventory tab, and set the window start for the next plan to the arrival date.
- Weekly: compare actual use per candy with the plan; flag any candy running more than a week ahead or behind.

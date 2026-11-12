---
name: fika-batch-order
description: Plan Fika's next candy batch from ERT (Sweden): how many kilograms of each candy to order so that every candy runs out on about the same day. Use when the owner asks for an order sheet, a reorder, "how much of each candy", or when to order the next batch.
---

# Fika batch order (inventory specialist SOP)

Goal: one order sheet where **every candy is planned to reach 0 stock on about the same day**, so a single
reorder covers the whole shop. Quantities follow each candy's real demand, plus the growth and season the owner
expects. Built for the third batch (Nov 2026); the scripts reproduce that order from `scripts/config.batch3.json`.

## Owner rules (always)

- Talk to the owner in **kilograms only**, never bags or packs. Packs exist only in the Excel copy for ERT.
- Every candy on sale is in the batch (at least one ERT pack), unless the owner says to leave one out.
- Amounts the owner sets by hand (for example "Pomegranate Ovals at 80 kg") are fixed; the rest is re-split around them.
- A new candy added inside a fixed batch size takes its kilograms from the other candies (the re-split does this).
- Say plainly what is data and what is an estimate. Do not round an assumption into a fact.
- No customer data in git: order JSON files and the FIKA sheet export stay in the scratchpad.

## Where the data lives

| What | Where |
|---|---|
| Every order, line by line (candies, Ready Mix bags, weights, status) | WooCommerce REST, `scripts/fetch_orders.py` (WP_USER / WP_APP_PASSWORD) |
| Candy categories (sweet / sour), visibility, stock status | WooCommerce products REST |
| Stock at the last count, ERT codes, pack sizes, prices | Google Drive: **FIKA** sheet, tabs Inventory, Inventory System (table from row 53), ERT ORDER, Stock Log |
| What each batch actually contained, landed cost per batch | Google Drive: **FIKA Orders** (tabs Order 1, Order 2, Sheet2 = cost per kg). Check with the owner: a line can be planned but never bought (Order 2's Strawberry/Vanilla Diamonds were not) |
| Candy that leaves without a website order | FIKA sheet tabs **PR** and **Atypical Orders** (cross-check names against website orders: some appear in both) |
| Product id -> FIKA sheet name | `candies` in the config (same mapping as `wordpress/snippets/fika-daily-sales.php`) |

## Steps

1. **Ask what changed** (only what you cannot read yourself): batch size in kg, arrival / lead time (air about 10
   days, sea about 70), promotions and ads coming, price changes, wholesale (B2B) kilograms, candies to add or drop,
   candies the supplier may not have, and whether a fresh stock count exists. Ask for the Ready Mix recipes if still
   unknown (until then: equal amounts of each candy in a mix).
2. **Fetch the data.** `fetch_orders.py <batch arrival date> orders.json`. Download the FIKA sheet as .xlsx and run
   `read_inventory.py FIKA.xlsx inventory.json`. Also read FIKA Orders (what came in) and the PR / Atypical tabs.
3. **Window = since the current batch arrived** (28 Sep for batch 2). Count orders that are completed, processing or
   on hold; leave out cancelled, failed, refunded. Weight per line: the "Weight" label; Ready Mix = **500 g per bag x
   quantity**; free mystery taste 50 g; old-site lines without labels: 500 g if the unit price is 10 or more, else
   100 g. (A bug that counted old Ready Mix lines as 100 g was caught in review: always check mix kilograms by bag count.)
4. **Loose demand per candy** = its loose kilograms / the days it was **on sale**. A candy that sold out early is
   measured only over its days in stock (Inventory tab KG/Day and Inventory System "Out of stock since" give the dates).
5. **Candies with no sales this batch** (hidden, sold out, returning): take their **share of loose sales** in the
   batch where they last sold, on their days on sale, and give them the same share of today's loose sales. Mark them
   as estimates. Launch weeks inflate shares: tell the owner, and let them set the amount.
6. **Ready Mix use** is real stock use, so add it: Sour Mix kg over the sour candies, Sweet Mix kg over the sweet ones
   (equal shares unless the recipe is known); Fan Favourites = 300 g over every candy + 100 g Wild Berry Pomegranate
   Ovals + 100 g Sour Strawberry Vanilla Diamonds per bag, at its **steady rate after the launch week** (2.3 bags a day
   in Nov 2026, not the launch week's 6). Mixes that were replaced or ended (Sweet & Sour, Skull Mix) are not counted.
7. **Projection** on top of today's use: x 1.10 for losses and non-website use (PR + atypical + sampling were about
   11% of website sales), growth per month compounded from today (10% unless the owner says otherwise), a season
   factor per month (December 1.5 with heavy Christmas promotions), and a wholesale amount shared by loose sales.
8. **Stock on arrival** = last count minus projected use from the count date to arrival.
9. **One common run-out day**: find the day T where (use from arrival to T minus stock on arrival plus wholesale),
   rounded to the **nearest whole ERT pack** with at least one pack each, adds up to the batch size. Fixed amounts stay
   fixed. Then fine-tune to the batch weight: take a pack from the candy that would still run out latest, or add one to
   the candy that runs out earliest **only if that keeps it within a week of T**. No per-candy safety stock: the margin
   is in when you reorder.
10. **Render and check**: `plan_order.py` writes the Excel (ERT codes, packs, kg, run-out dates, "How it was worked
    out") and `render_view.py` the page for the owner (kg only). Send both; the page with display "render".

## Review checklist (do it before calling it final)

- Every line has an ERT code, a pack size from the **latest real order** (FIKA Orders) and a price. Small packs exist
  (175 g x 12, 190 g x 14, 155 g x 12): use the unit the supplier sells. First-batch-only candies: Order 1's code and size.
- Mix kilograms match bag counts x 500 g.
- Compare the model's daily rates with the **last 7 days** of orders. If sales slowed or jumped, say so: it changes
  how long the batch lasts, not the split.
- Landed cost: the FIKA Orders plan may leave VAT blank; add it (about $840 on batch 2). Batch 2 landed at $15.93/kg.
- List candies more than a week off the common day and why (pack size, existing stock, fixed by the owner).
- Reconcile stock: Inventory "Ordered Quantity" minus the latest count vs website kilograms + PR + atypical + sampling.
- Flag supplier risks (e.g. Bubblegum Skulls availability) and give the **reorder date** = common run-out day minus
  lead time minus a week.

## Running it

```
cd .claude/skills/fika-batch-order/scripts
python3 fetch_orders.py 2026-09-28 $SCRATCH/orders.json
python3 read_inventory.py $SCRATCH/FIKA.xlsx $SCRATCH/inventory.json
cp config.batch3.json $SCRATCH/config.json   # edit: dates, window, batch_kg, projection, fixed_kg, shares, candies
python3 plan_order.py $SCRATCH/config.json $SCRATCH/orders.json $SCRATCH/inventory.json $SCRATCH/order.xlsx
python3 render_view.py $SCRATCH/order.xlsx.json $SCRATCH/order.html
```

Config fields: `today`, `count_date`, `arrival`, `window` (from / to), `batch_kg`, `projection` (growth_per_month,
season {month: factor}, loss, wholesale_kg), `ready_mixes` (product id -> sour / sweet / ff / ss / skull),
`mix_use` (Fan Favourites bags a day, base kg, extras kg by product id), `share_from_earlier_batch` (product id ->
share), `fixed_kg` (product id -> kg), `candies` (product id -> sheet name, shop name, group sweet / sour / extra,
code, pack_kg, sek_per_pack, optional days_on_sale, in_mixes, stock_zero, note), `explanation` (lines for the sheet).

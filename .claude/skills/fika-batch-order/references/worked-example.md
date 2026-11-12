# Worked example: batch 3 (planned 12 Nov 2026) and regression test

Run with the batch-3 inputs and you must get these numbers. If not, something in the scripts changed.

```
python3 scripts/read_inventory.py FIKA.xlsx inventory.json      # 31 candies, counted 2026-11-06, 247.8 kg
python3 scripts/fetch_orders.py 2026-09-28 orders.json            # orders up to 11 Nov 2026 used (window ends 11 Nov)
python3 scripts/plan_order.py scripts/config.batch3.json orders.json inventory.json order.xlsx
# -> common run-out day 59 (2027-01-22), 548.8 kg; within a week: 21 of 32
```
(Orders fetched later contain more days, but the window in the config stops at 11 Nov, so the result is the same as
long as old orders are not edited.)

## Inputs used

- Window 28 Sep - 11 Nov (45 days): 191.7 kg loose; Ready Mix bags: Fan Favourites 68, Sweet & Sour 52, Sour 44,
  Skull 18, Sweet 13.
- Loose demand today: 4.34 kg/day for candies with batch data. Shares from August for candies without:
  Pomegranate Ovals 14.8%, Tutti Frutti Diamonds 7.1%, Sour Pineapple 2.8%.
- Mix use: Sour Mix 22 kg and Sweet Mix 6.5 kg over 45 days; Fan Favourites 2.3 bags/day x (300 g spread + 100 g
  Pomegranate + 100 g Strawberry Vanilla).
- Projection: x1.10 losses, +10% a month, December x1.5, 50 kg wholesale. Arrival 24 Nov (air, 10 days).
- Fixed by the owner: Pomegranate Ovals 80 kg (81.2 in packs), Tutti Frutti Diamonds 15.6 kg (trial), Strawberry
  Vanilla Diamonds 24.7 kg.

## Result (kg)

Pomegranate Ovals 81.2, Forest Berry Ovals 52.5, Bubblegum Skulls 46.8, Banana Toffee Ovals 36.4, Lemon Raspberry
Skulls 28.8, Strawberry Vanilla Diamonds 24.7, Cola Skulls 24.0, Fruity Pear Ovals 23.4, Sour Pineapple 21.0,
Raspberry Salty Licorice Skulls 20.9, Sour Swedish Fish 19.2, Fizzy Blue 18.0, Fruity Lemon Mix Skulls 16.2,
Tutti Frutti Diamonds 15.6, Sour Cherries 15.0, Swedish Fish 15.0, Sour Watermelon Pacifier 12.0, Fizzy Pop 10.0,
Fizzy Cola 9.75, Sugared Strawberries 9.0, Raspberry Bites 8.0, Tutti Frutti Rings 6.8, Peaches 6.0, Tutti Frutti
Sour 6.0, Sour Strawberries 4.0, Loose Teeth 3.0, Red Ammo 3.0, Rhubarb Bites 3.0, Sugared Apples 3.0, Forest
Berries 2.24, Tutti Frutti Passion 2.2, Tutti Frutti Sour Melon 2.1. Total 548.8 kg.
Landed about $7,900 + VAT about $840. Reorder by about 5 Jan 2027.

## What the owner was told it depends on

- Christmas promotions lifting sales back up (last week ran 40% below the window average). If they do not, the batch
  lasts into March: timing changes, the split does not.
- Bubblegum Skulls availability; the 50 kg wholesale deal (not confirmed); Pomegranate and Tutti Frutti Diamonds
  resting on August data; Ready Mix recipes assumed equal.

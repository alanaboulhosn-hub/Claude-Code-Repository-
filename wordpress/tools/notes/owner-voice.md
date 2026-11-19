# The owner's voice, for the notes in each bag

Every order gets one personal message and one question, written as the owner would write them. Studied from the
owner's own writing (19 Nov 2026). The message must fit only that customer: if it could be sent to anyone else,
rewrite it.

## Tone and mood
- Calm, sure, practical, warm underneath. Care shows in what is noticed, not in big words.
- No exclamation marks. No emojis. Nothing salesy: no prices, codes, offers, "order again".
- British spelling (favourite, colour). Standard spelling on notes (the owner's chat habits such as "aswell",
  "ofcourse" stay out of print).

## Rhythm
- One longer thought joined with commas, then a short, firm sentence.
  "Mia, six candies and every single one of them sour, you definitely knew what you came for. I hope they make
  the trip to Baasir worth it."
- Two or three sentences at most. Start with the customer's first name and a comma.

## Words the owner uses
definitely, simply, to be exact, along with, honestly, a bit, I would love to know, I hope, quick question,
be honest, the goal, a matter of time. Talks in kilograms and grams, never "bags" of weight.

## What the message is built from (facts from order_facts.py)
- Returning customer: favourites over time, what came back again, what is new to them, how long since the last
  order (only when warm: "a month to the day"), how their taste is shifting, anything they told us before.
- First order: the choices in this bag: all sour, mostly sweet, one brave pick (salty licorice), a single extra
  candy, a full kilogram, a let-us-choose Ready Mix, the town it travels to.
- Answers they gave before (told_us_before) come first: follow up on them.

## The question
- Starts "Quick question:" or "Be honest,". Short, answerable in one Instagram reply.
- Follows from the message and asks their own view: which one won, which one twice, love it or never again,
  which candy from the mix on its own.
- Its answer must be useful for their next note.

## Never
- Prices, discounts, codes, how often or at what time they order, their address beyond the town.
- On gift orders (sent to someone else): write to the receiver about the bag only; nothing about the buyer's
  history.
- Anything that sounds like watching them.

## Model notes
- Firuza (second order, a month later, same favourites, doubled two): "Firuza, a month to the day and you came
  back for the exact same favourites, along with double the Bubblegum Skulls and Banana Toffee this time. That
  honestly made my day." Quick question: if you had to keep only one, Bubblegum Skull or Banana Toffee?
- Nisrine (first order, Sweet & Sour Mix only): "Nisrine, you let us do the choosing for your first Fika. The
  Sweet & Sour Mix is simply the bag I would hand a friend on their first day in Stockholm." Quick question:
  which candy from the mix would you go for on its own next time?
- Karen (first order, chose the salty licorice skull): "Karen, you picked the Raspberry Salty Licorice Skull
  for your very first bag, that is the brave one in our shop." Quick question: salty licorice, love it or
  never again?

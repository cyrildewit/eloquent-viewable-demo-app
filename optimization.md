# Improvements to consider for eloquent-viewable 9.x

Measured on this demo with 21 million views over three years: Docker on a laptop, one MariaDB 11.8 with a 4 GB
buffer pool, the `rollup` source with day, month and hour tiers, and seven dimensions folded. Reads are timed without
`remember()`. Each item says what was measured, where it lives in the package, and a direction to explore.

## 1. Pruning can delete views that a rollup never folded

**Severity:** data loss in the rollup history.

**Where:** `RollupWatermarks::clamp()` in `src/Querying/Rollups/RollupWatermarks.php`, used by `PruneViews` and
`AnonymiseViews`.

**What happens:** `clamp()` caps the prune cutoff at how far each tier is folded (`rollup:{name}:{tier}`), but never
looks at where a tier's history starts (`rollup:{name}:{tier}:since`). A tier that starts late, for example because
its first fold began at a later date, or because a dimension was added to `retention.rollups.dimensions` after views
were recorded, still reports itself folded up to yesterday. Pruning then deletes the views before its start, which
no tier of that rollup ever counted, and every read over that range comes back short.

Seen in practice: the built-in rollup and four dimension rollups had their day and month tiers start at a date three
months back, with the views table holding views from twenty months before that. Every later run resumes from the
watermark and never fills the gap, so the next prune would have removed 20 months of history from those rollups.

**Direction:** in `clamp()`, also cap the cutoff at the start of the coarsest tier of each rollup, unless that start
is at or before the oldest view. A tier that starts late should hold pruning back, the same way a tier that is
behind does. `views:doctor` could report a tier whose start is later than the oldest view in the table.

## 2. `recommended()` takes seconds on a large table

**Where:** `CoVisitation::recommendationPairs()` in `src/Querying/Sources/CoVisitation.php`, and the pairs path in
`DatabaseSource::recommendationPairs()`.

| Call, for one reader                          | Time   | Slowest statement                                         |
|-----------------------------------------------|-------:|-----------------------------------------------------------|
| `recommended()` with `period(pastDays(30))`   | 20 s   | the co-visitation query, 17 s, and the audiences, 1.5 s   |
| `recommended()` over all time, pairs table    | 1.5 s  | the pairs query with its `seen` subquery, 0.9 s           |

The 30-day call reads every view of every visitor of the reader's recent pieces. The pairs-table call is far
cheaper, but its correlated `select 1 from views as seen` subquery runs once per candidate pair.

**Direction:**

- Anti-join the reader's own history once, for example by fetching the keys the reader viewed (the seeds query
  already knows them, through the `(viewer_type, viewer_id)` index) and excluding them with `whereNotIn`, instead of
  a correlated subquery per pair.
- Let the pairs table serve calls with a period, by keeping pairs per period window, such as the 30 and 90 days
  `views:pairs` already reads, so a period call does not fall back to co-visitation.
- Until then, say in the README that a period call on a large table belongs in a scheduled job, as the "On large
  tables" section suggests for all time.

## 3. The hour tier multiplies the rollup rows of every dimension

**Where:** `RollupDefinition` for dimension rollups, which take `retention.rollups.tiers` as is.

After folding, the rollups table held 3.9 million rows, about 900 MB. The hour tier of the seven dimension rollups
was 2.5 million of them: country 651,000, source 538,000 and referrer host 492,000 alone. Hourly splits by dimension
are rarely read; hourly totals, for trending, rising and spike baselines, come from the built-in rollup's hour tier.

**Direction:** let a dimension rollup choose its tiers, or default dimension rollups to the tiers coarser than an
hour, for example `'dimensions' => ['source' => ['tiers' => ['day', 'month']]]`. That would remove most of the table
in this setup.

## 4. The view scopes stay slow after folding

**Where:** `scopeOrderByViews()`, `scopeOrderByTrending()` and `scopeWhereViewsCount()` in
`src/Concerns/InteractsWithViews.php`, through `countSubquery()` and `trendingSubquery()`.

| Call, on 24 posts                       | Raw table | With rollups |
|-----------------------------------------|----------:|-------------:|
| `orderByViews('desc', pastDays(7))`     | 1.8 s     | 0.96 s       |
| `orderByTrending()`                     | 2.2 s     | 0.80 s       |
| `whereViewsCount('>=', 1000)`           | 1.8 s     | 0.76 s       |
| the same order on a counter column      | 0.7 ms    | 0.7 ms       |

The equivalent reads through `views()`, `top()` and `trending()`, take 0.12 to 0.15 s with rollups. The scopes run
their subquery per row of the outer query and cannot use `remember()`.

**Direction:** check whether the rollup source's subqueries aggregate the rollup rows once and join, rather than once
per row. If they cannot become fast, point to counter columns from the scope docblocks and the README sections on
ordering, since a list page is where these scopes end up.

## 5. The first fold scans the views table once per tier and rollup

**Where:** `FoldViews::foldBucket()`, `insert()` and `insertCapped()`.

The first `views:maintain` over 21 million views took 2 hours: 66 minutes folding, about 50 minutes anonymising 17.9
million views and 4 minutes pruning 12.2 million. Folding took about 8.5 minutes per dimension rollup: 4.6 minutes for
the month tier, 3.2 for the day tier and 0.7 for the hour tier. The month tier reads every view of the month again,
although the day tier has just counted them. Hourly runs afterwards take 23 seconds.

**Direction:** fold a coarser tier from the finer tier's rows where the finer tier covers the bucket. Views and the
dimension values sum exactly; unique visitors do not, so this needs either a raw pass for `unique_visitors` alone, or
accepting summed uniques for the month tier, which reads already do for older history. Short of that, document what a
first fold costs on a large table and recommend `--max-seconds`.

## 6. Pruning does not return disk space

**Where:** the README's retention and partitioning sections.

After pruning 12.2 million of 21 million views, the views table held 8.8 million rows but still took 6.7 GB, because
InnoDB keeps the freed pages. The partitioning section covers this for very large tables; a table that is pruned row
by row needs `OPTIMIZE TABLE` now and then, which rebuilds it and locks it on older MySQL versions.

**Direction:** one paragraph in the retention section about disk space after pruning, and when partitioning is the
better choice.

## 7. Anonymising clears the reading history a product may rely on

**Where:** `retention.anonymise.columns`, which includes `viewer` by default.

After anonymising, views older than 30 days lose their viewer. `whereNotViewedBy()` then lists posts a reader opened
more than 30 days ago as unread, `viewed()` and `lastViewedAt()` forget them, and `recommended()` has less history to
start from. That is the intent of anonymising, but it reads as a bug in a "continue reading" feature.

**Direction:** say this where `HasViewHistory` and `whereNotViewedBy()` are documented, with the alternatives: leave
`viewer` out of `anonymise.columns`, or set `anonymise.after` to the history the product needs.

## 8. `remember()` examples give the wrong lifetimes

**Where:** the README, "Caching view counts" and "Rising, spiking and dropping".

`Views::remember(int)` takes minutes (`resolveLifetime()` calls `addMinutes()`), but two examples read as seconds:

- `views($post)->remember(3600)->count(); // for an hour` caches for 60 hours.
- "`remember(600)` serves the same answer for ten minutes" caches for 10 hours.

**Direction:** `remember(60)` for an hour and `remember(10)` for ten minutes, or name the unit in the method's
docblock and the README once.

## Reproducing the measurements

```bash
docker exec evd-php php artisan demo:reset --views=20000000 --days=1095 --focus=post --viral=breaking-the-php-9-rfc-passes:1000000
docker exec evd-php php artisan demo:benchmark raw
docker exec evd-php php artisan views:maintain
docker exec evd-php php artisan demo:benchmark folded --compare=raw
```

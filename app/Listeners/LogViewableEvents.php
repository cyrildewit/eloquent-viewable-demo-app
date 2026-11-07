<?php

namespace App\Listeners;

use App\Models\DemoEvent;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryAnonymised;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryExported;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryForgotten;
use CyrildeWit\EloquentViewable\Milestones\Events\ViewMilestoneReached;
use CyrildeWit\EloquentViewable\Querying\Counters\Events\CountersRecounted;
use CyrildeWit\EloquentViewable\Querying\Pairs\Events\ViewsPaired;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Recording\Events\BurstDetected;
use CyrildeWit\EloquentViewable\Recording\Events\ViewsDestroyed;
use CyrildeWit\EloquentViewable\Retention\Events\BotViewsPurged;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsAnonymised;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsPruned;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsDropped;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSettled;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSpiked;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Writes every event the package dispatches to the demo_events table, so the
 * Console shows what a command set off. Listeners in a real application
 * should be queued; these write one row, so they run right away and the feed
 * updates while you watch.
 */
class LogViewableEvents
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            ViewMilestoneReached::class => 'milestone',
            ViewsSpiked::class => 'spiked',
            ViewsDropped::class => 'dropped',
            ViewsSettled::class => 'settled',
            ViewsRolledUp::class => 'rolledUp',
            ViewsAnonymised::class => 'anonymised',
            ViewsPruned::class => 'pruned',
            BotViewsPurged::class => 'botsPurged',
            CountersRecounted::class => 'recounted',
            ViewsPaired::class => 'paired',
            BurstDetected::class => 'burst',
            ViewsDestroyed::class => 'destroyed',
            ViewHistoryForgotten::class => 'historyForgotten',
            ViewHistoryAnonymised::class => 'historyAnonymised',
            ViewHistoryExported::class => 'historyExported',
        ];
    }

    public function milestone(ViewMilestoneReached $event): void
    {
        $this->log($event, 'good', sprintf(
            '%s passed %s %s',
            $this->name($event->type, $event->key),
            number_format($event->milestone),
            str_replace('_', ' ', $event->column),
        ), [
            'column' => $event->column,
            'milestone' => $event->milestone,
            'count' => $event->count,
            'passed' => $event->passed,
        ]);
    }

    public function spiked(ViewsSpiked $event): void
    {
        $this->log($event, 'hot', "{$this->name($event->type, $event->key)} is spiking", $event->baseline->toArray());
    }

    public function dropped(ViewsDropped $event): void
    {
        $this->log($event, 'bad', "{$this->name($event->type, $event->key)} dropped far below its usual traffic", $event->baseline->toArray());
    }

    public function settled(ViewsSettled $event): void
    {
        $this->log($event, 'info', "{$this->name($event->type, $event->key)} settled after a {$event->direction->value}", [
            'peak_count' => $event->peakCount,
            'peak_score' => round($event->peakScore, 2),
            'since' => $event->since->toDateTimeString(),
        ]);
    }

    public function rolledUp(ViewsRolledUp $event): void
    {
        $this->log($event, 'info', "Folded {$event->buckets} {$event->tier->value} buckets of the `{$event->rollup}` rollup", [
            'from' => $event->from?->toDateTimeString(),
            'until' => $event->until->toDateTimeString(),
            'stopped' => $event->stopped,
        ]);
    }

    public function anonymised(ViewsAnonymised $event): void
    {
        $this->log($event, 'info', 'Anonymised '.number_format($event->views).' views', [
            'from' => $event->from?->toDateTimeString(),
            'until' => $event->until->toDateTimeString(),
        ]);
    }

    public function pruned(ViewsPruned $event): void
    {
        $this->log($event, 'warn', 'Pruned '.number_format($event->views).' views', [
            'from' => $event->from?->toDateTimeString(),
            'until' => $event->until->toDateTimeString(),
        ]);
    }

    public function botsPurged(BotViewsPurged $event): void
    {
        $this->log($event, 'warn', 'Purged '.number_format($event->views).' bot views of '.$event->visitors.' visitors', [
            'from' => $event->from->toDateTimeString(),
            'until' => $event->until->toDateTimeString(),
        ]);
    }

    public function recounted(CountersRecounted $event): void
    {
        $this->log($event, 'muted', 'Recounted the counter columns of '.count($event->keys).' '.class_basename($event->class).' models', [
            'keys' => array_slice($event->keys, 0, 25),
        ]);
    }

    public function paired(ViewsPaired $event): void
    {
        $this->log($event, 'info', "Paired {$event->viewables} models into {$event->pairs} pairs", [
            'since' => $event->since->toDateTimeString(),
        ]);
    }

    public function burst(BurstDetected $event): void
    {
        $this->log($event, 'bad', "Burst detected, counted per {$event->by}", [
            'visitor' => substr($event->attempt->visitor->id(), 0, 16),
            'viewable' => $this->name($event->attempt->viewable->getMorphClass(), $event->attempt->viewable->getKey()),
        ]);
    }

    public function destroyed(ViewsDestroyed $event): void
    {
        $this->log($event, 'warn', 'Destroyed the views of '.$this->name($event->viewable->getMorphClass(), $event->viewable->getKey()));
    }

    public function historyForgotten(ViewHistoryForgotten $event): void
    {
        $this->log($event, 'warn', "Forgot {$event->views} views of {$this->subject($event->subject)}");
    }

    public function historyAnonymised(ViewHistoryAnonymised $event): void
    {
        $this->log($event, 'info', "Anonymised {$event->views} views of {$this->subject($event->subject)}");
    }

    public function historyExported(ViewHistoryExported $event): void
    {
        $this->log($event, 'muted', "Exported the view history of {$this->subject($event->subject)}");
    }

    private function log(object $event, string $tone, string $title, array $payload = []): void
    {
        try {
            DemoEvent::query()->create([
                'event' => class_basename($event),
                'tone' => $tone,
                'title' => $title,
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function name(string $type, int|string $key): string
    {
        $class = Relation::getMorphedModel($type) ?? $type;
        $model = class_exists($class) ? $class::query()->find($key) : null;

        $label = $model?->title ?? "#{$key}";

        return class_basename($class).' “'.$label.'”';
    }

    private function subject(object $subject): string
    {
        if ($subject->isViewer()) {
            return class_basename(Relation::getMorphedModel($subject->viewerType) ?? $subject->viewerType)." #{$subject->viewerKey}";
        }

        return 'visitor '.substr((string) $subject->visitor, 0, 12).'…';
    }
}

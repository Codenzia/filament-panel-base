<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\NotificationMatrix;

/**
 * Fleet-wide notification trigger registry. Host apps and plugins register
 * every notification "trigger" they can fire, at boot time, in their own
 * service providers:
 *
 *     NotificationTriggers::register('task-off.task.assigned', [
 *         'label' => 'Task assigned to you',
 *         'group' => 'Tasks',
 *         'channels' => ['database', 'mail'],
 *     ]);
 *
 * A plain container singleton (see FilamentPanelBaseServiceProvider) — one
 * registry per application boot, so Orchestra Testbench's fresh application
 * per test gives every test a clean slate without an explicit reset.
 *
 * Keys are namespaced strings ('app.entity.event') so multiple apps/plugins
 * never collide. Meta:
 *   - label            human-readable description shown in the preferences UI.
 *   - group            UI section the trigger is grouped under (e.g. 'Tasks').
 *   - channels         channels this trigger can be delivered through.
 *                      Defaults to ['database', 'mail'].
 *   - default_enabled  whether the trigger is opted in when a user has never
 *                      touched its preference row. Defaults to true.
 */
class NotificationTriggers
{
    /** @var array<string, array<string, mixed>> */
    protected array $triggers = [];

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function register(string $key, array $meta): void
    {
        static::instance()->put($key, $meta);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return static::instance()->find($key);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return static::instance()->everything();
    }

    /**
     * All registered triggers keyed by their `group` meta, preserving
     * registration order within each group. Used by the preferences page to
     * render one section per group.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (static::all() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'General');
            $grouped[$group][$key] = $meta;
        }

        return $grouped;
    }

    public static function forget(string $key): void
    {
        static::instance()->remove($key);
    }

    protected static function instance(): static
    {
        return app(static::class);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function put(string $key, array $meta): void
    {
        $this->triggers[$key] = array_merge([
            'label' => $key,
            'group' => 'General',
            'channels' => ['database', 'mail'],
            'default_enabled' => true,
        ], $meta);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->triggers[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function everything(): array
    {
        return $this->triggers;
    }

    public function remove(string $key): void
    {
        unset($this->triggers[$key]);
    }
}

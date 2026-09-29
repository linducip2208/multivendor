<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest {--days=1 : Window of unread notifications to digest}';

    protected $description = 'Send each user a digest of their unread notifications from the last N days';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $users = User::query()
            ->where('status', 'active')
            ->whereIn('id', $this->unreadUserIds($since))
            ->limit(2000)
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $unread = $this->unreadFor((int) $user->id, $since);

            if ($unread->isEmpty() || $this->alreadyDigested($unread, $since)) {
                continue;
            }

            Notification::create([
                'id' => (string) Str::uuid(),
                'type' => 'notification_digest',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => self::digestTemplate($user, $unread),
            ]);

            $sent++;
        }

        $this->info("Created {$sent} notification digest(s).");

        return self::SUCCESS;
    }

    private function unreadUserIds($since): array
    {
        return Notification::query()
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('notifiable_id')
            ->all();
    }

    private function unreadFor(int $userId, $since): Collection
    {
        return Notification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->get();
    }

    private function alreadyDigested(Collection $unread, $since): bool
    {
        return $unread->contains(
            static fn (Notification $notification): bool => $notification->type === 'notification_digest'
                && $notification->created_at?->greaterThanOrEqualTo($since),
        );
    }

    /** Template digest granular Bahasa Indonesia (dipakai command + preview UI). */
    public static function digestTemplate(User $user, Collection $unread): array
    {
        $byType = $unread->groupBy(fn ($n) => $n->type ?? 'info')->map->count()->sortDesc();
        $lines = $byType->map(fn ($count, $type) => '• '.Str::headline((string) $type).': '.$count)->values()->all();

        return [
            'title' => 'Ringkasan Notifikasi',
            'greeting' => 'Halo '.$user->name.',',
            'message' => 'Anda memiliki '.$unread->count().' notifikasi belum dibaca.',
            'unread_count' => $unread->count(),
            'types' => $unread->pluck('type')->unique()->values()->all(),
            'breakdown' => $byType->all(),
            'lines' => $lines,
            'cta_label' => 'Lihat semua notifikasi',
            'cta_url' => route('account.notifications'),
        ];
    }
}

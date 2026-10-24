<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Voucher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('hotspot:hosts {--url= : Validate one link-login-only URL} {--limit=200 : Number of recent payments to inspect}')]
#[Description('Show hotspot login hosts seen in portal opens/payment records and validate them against the allowlist')]
class InspectHotspotHosts extends Command
{
    public function handle(): int
    {
        $configured = array_values(array_filter(array_map(
            'trim',
            (array) config('router.allowed_login_hosts', []),
        )));

        $this->line('Router API host:      '.config('router.host'));
        $this->line('APP_URL host:         '.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: '—'));
        $this->line('Allowed login hosts:  '.($configured === [] ? '(none)' : implode(', ', $configured)));
        $this->newLine();

        $url = $this->option('url');

        if (is_string($url) && $url !== '') {
            $this->reportUrl($url);
            $this->newLine();
        }

        $observed = Cache::get('hotspot:observed-hosts', []);

        if (is_array($observed) && $observed !== []) {
            $this->line('Hosts seen when the portal was opened (last 30 days):');

            $this->table(
                ['Host', 'Opens', 'Last seen', 'Accepted'],
                array_map(function (string $host, array $entry) {
                    $url = (string) ($entry['url'] ?? 'http://'.$host.'/login');

                    return [
                        $host,
                        (int) ($entry['count'] ?? 0),
                        isset($entry['last_seen']) ? Carbon::parse($entry['last_seen'])->toDateTimeString() : '—',
                        Voucher::isSafeLoginUrl($url) ? 'yes' : 'NO',
                    ];
                }, array_keys($observed), array_values($observed)),
            );

            $rejectedObserved = array_keys(array_filter(
                $observed,
                fn (array $entry, $host) => ! Voucher::isSafeLoginUrl((string) ($entry['url'] ?? 'http://'.$host.'/login')),
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($rejectedObserved !== []) {
                $this->warn('Add these public hosts to ROUTER_ALLOWED_LOGIN_HOSTS: '.implode(',', $rejectedObserved));
            }

            $this->newLine();
        }

        $limit = max(1, (int) $this->option('limit'));
        $payments = Payment::query()
            ->whereNotNull('login_url')
            ->latest()
            ->limit($limit)
            ->get(['login_url', 'created_at']);

        if ($payments->isEmpty()) {
            if (is_array($observed) && $observed !== []) {
                $this->info('No payment records contain a link-login-only URL yet.');
            } else {
                $this->info('No hotspot login host has been observed yet.');
                $this->line('Open the portal from the hotspot once, then run this command again.');
                $this->line('You can also validate a URL directly: php artisan hotspot:hosts --url="http://192.168.88.1/login"');
            }

            return self::SUCCESS;
        }

        $hosts = [];

        foreach ($payments as $payment) {
            $host = $this->host($payment->login_url);

            if ($host === null) {
                continue;
            }

            $hosts[$host] ??= [
                'host' => $host,
                'seen' => 0,
                'last_seen' => $payment->created_at,
                'safe' => Voucher::isSafeLoginUrl($payment->login_url),
            ];

            $hosts[$host]['seen']++;

            if ($payment->created_at && ($hosts[$host]['last_seen'] === null || $payment->created_at->gt($hosts[$host]['last_seen']))) {
                $hosts[$host]['last_seen'] = $payment->created_at;
            }
        }

        if ($hosts === []) {
            $this->warn('Payment records exist, but none contained a parsable link-login-only URL.');

            return self::SUCCESS;
        }

        ksort($hosts);

        $this->table(
            ['Host', 'Payments', 'Last seen', 'Accepted'],
            array_map(fn (array $row) => [
                $row['host'],
                $row['seen'],
                $row['last_seen']?->toDateTimeString() ?? '—',
                $row['safe'] ? 'yes' : 'NO',
            ], array_values($hosts)),
        );

        $rejected = array_values(array_filter($hosts, fn (array $row) => ! $row['safe']));

        if ($rejected !== []) {
            $this->warn('These hosts are public and not allowlisted. Add them to ROUTER_ALLOWED_LOGIN_HOSTS:');
            $this->line('ROUTER_ALLOWED_LOGIN_HOSTS='.implode(',', array_column($rejected, 'host')));
        } else {
            $this->info('All observed login hosts are accepted.');
        }

        return self::SUCCESS;
    }

    private function reportUrl(string $url): void
    {
        $host = $this->host($url);

        if ($host === null) {
            $this->error('Could not parse a host from that URL.');

            return;
        }

        $safe = Voucher::isSafeLoginUrl($url);

        $this->line("URL host: {$host}");
        $this->line('Accepted: '.($safe ? 'yes' : 'NO'));

        if (! $safe) {
            $this->line('Add it with: ROUTER_ALLOWED_LOGIN_HOSTS='.$host);
        }
    }

    private function host(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return rtrim(strtolower(trim($host, '[]')), '.');
    }
}

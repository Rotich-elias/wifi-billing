<?php

namespace App\Services;

use App\Models\MikrotikSyncLog;
use App\Models\Voucher;
use Exception;

class MikrotikService
{
    private $socket;
    private bool $connected = false;
    private string $host;
    private int $port;
    private string $user;
    private string $password;

    public function __construct()
    {
        $this->host     = config('mikrotik.host');
        $this->port     = (int) config('mikrotik.port');
        $this->user     = config('mikrotik.user');
        $this->password = config('mikrotik.password');
    }

    public function connect(): bool
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new Exception("Cannot connect to MikroTik: {$errstr} ({$errno})");
        }
        stream_set_timeout($this->socket, 10);
        $this->connected = true;
        $this->login();
        return true;
    }

    private function login(): void
    {
        $this->write(['/login', '=name='.$this->user, '=password='.$this->password]);
        $response = $this->read();
        if (!isset($response[0]) || $response[0] !== '!done') {
            throw new Exception('MikroTik login failed');
        }
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }

    public function addHotspotUser(Voucher $voucher): array
    {
        $plan   = $voucher->plan;
        $server = config('mikrotik.hotspot_server');

        $command = [
            '/ip/hotspot/user/add',
            '=name='.$voucher->code,
            '=password='.$voucher->code,
            '=server='.$server,
            '=profile='.$this->getOrCreateProfile($plan),
            '=comment=voucher_id:'.$voucher->id,
        ];

        if ($voucher->customer_name) {
            $command[] = '=name-for-mgmt='.$voucher->customer_name;
        }

        $this->write($command);
        $response = $this->read();

        if ($this->isError($response)) {
            throw new Exception('Failed to add hotspot user: '.implode(', ', $response));
        }

        return $response;
    }

    public function removeHotspotUser(string $username): void
    {
        $this->write(['/ip/hotspot/user/remove', '=numbers='.$username]);
        $this->read();
    }

    public function getHotspotUser(string $username): ?array
    {
        $this->write(['/ip/hotspot/user/print', '?name='.$username]);
        $response = $this->read();

        foreach ($response as $item) {
            if (is_array($item) && isset($item['name'])) {
                return $item;
            }
        }
        return null;
    }

    public function getActiveUsers(): array
    {
        $this->write(['/ip/hotspot/active/print']);
        return $this->readArray();
    }

    private function getOrCreateProfile(mixed $plan): string
    {
        $profileName = 'voucher_'.$plan->type;
        $server      = config('mikrotik.hotspot_server');

        $this->write(['/ip/hotspot/user/profile/print', '?name='.$profileName, '?server='.$server]);
        $existing = $this->read();

        foreach ($existing as $item) {
            if (is_array($item) && isset($item['name'])) {
                return $profileName;
            }
        }

        $sessionTimeout = $plan->session_timeout
            ? $this->secondsToMikrotik($plan->session_timeout)
            : '00:00:00';
        $idleTimeout = $plan->idle_timeout
            ? $this->secondsToMikrotik($plan->idle_timeout)
            : '00:05:00';

        $this->write([
            '/ip/hotspot/user/profile/add',
            '=name='.$profileName,
            '=server='.$server,
            '=rate-limit='.$plan->bandwidth_up.'/'.$plan->bandwidth_down,
            '=session-timeout='.$sessionTimeout,
            '=idle-timeout='.$idleTimeout,
        ]);
        $this->read();

        return $profileName;
    }

    private function write(array $words): void
    {
        foreach ($words as $word) {
            $this->sendLength(strlen($word));
            fwrite($this->socket, $word);
        }
        $this->sendLength(0);
    }

    private function sendLength(int $length): void
    {
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->socket, chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite($this->socket, chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite($this->socket, chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        }
    }

    private function readLength(): int
    {
        $b = ord(fread($this->socket, 1));
        if ($b < 0x80) return $b;
        if ($b < 0xC0) return (($b & 0x3F) << 8) + ord(fread($this->socket, 1));
        if ($b < 0xE0) {
            $b2 = fread($this->socket, 2);
            return (($b & 0x1F) << 16) + (ord($b2[0]) << 8) + ord($b2[1]);
        }
        if ($b < 0xF0) {
            $b2 = fread($this->socket, 3);
            return (($b & 0x0F) << 24) + (ord($b2[0]) << 16) + (ord($b2[1]) << 8) + ord($b2[2]);
        }
        $b2 = fread($this->socket, 4);
        return (ord($b2[0]) << 24) + (ord($b2[1]) << 16) + (ord($b2[2]) << 8) + ord($b2[3]);
    }

    private function read(): array
    {
        $result = [];
        while (true) {
            $len  = $this->readLength();
            $word = $len > 0 ? fread($this->socket, $len) : '';

            if ($word === '!done') {
                $result[] = '!done';
                break;
            }
            if ($word === '!re') {
                $result[] = $this->readSentence();
                continue;
            }
            if ($word === '!trap' || $word === '!fatal') {
                $result[] = $word;
                $result[] = $this->readSentence();
                break;
            }
            if ($len === 0) {
                break;
            }
        }
        return $result;
    }

    private function readSentence(): array
    {
        $data = [];
        while (true) {
            $len = $this->readLength();
            if ($len === 0) break;
            $word = fread($this->socket, $len);
            if (str_starts_with($word, '=')) {
                $parts = explode('=', ltrim($word, '='), 2);
                $data[$parts[0]] = $parts[1] ?? '';
            }
        }
        return $data;
    }

    private function readArray(): array
    {
        $result = [];
        $raw    = $this->read();
        foreach ($raw as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function isError(array $response): bool
    {
        return in_array('!trap', $response) || in_array('!fatal', $response);
    }

    private function secondsToMikrotik(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    public function syncVoucher(Voucher $voucher, string $action = 'add'): bool
    {
        try {
            $this->connect();

            if ($action === 'add') {
                $this->addHotspotUser($voucher);
            } elseif ($action === 'remove') {
                $this->removeHotspotUser($voucher->code);
            }

            $voucher->update([
                'mikrotik_synced'    => true,
                'mikrotik_synced_at' => now(),
                'mikrotik_user'      => $voucher->code,
            ]);

            MikrotikSyncLog::create([
                'voucher_id' => $voucher->id,
                'action'     => $action,
                'status'     => 'success',
                'message'    => "Voucher {$action} successful",
            ]);

            return true;
        } catch (Exception $e) {
            MikrotikSyncLog::create([
                'voucher_id' => $voucher->id,
                'action'     => $action,
                'status'     => 'failed',
                'message'    => $e->getMessage(),
            ]);
            return false;
        } finally {
            $this->disconnect();
        }
    }
}

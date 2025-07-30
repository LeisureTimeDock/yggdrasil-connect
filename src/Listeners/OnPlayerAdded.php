<?php

namespace LittleSkin\YggdrasilConnect\Listeners;

use App\Models\Player;
use Illuminate\Support\Facades\DB;
use LittleSkin\YggdrasilConnect\Models\UUID;
use LittleSkin\YggdrasilConnect\Services\MojangUUIDFetcher;
use Ramsey\Uuid\Uuid as RamseyUuid;

class OnPlayerAdded
{
    public function handle(Player $player): void
    {
        $algorithm = option('ygg_uuid_algorithm');
        $name = $player->name;
        $uuid = null;

        if ($algorithm === 'v3') {
            $uuid = UUID::generateUuidV3($name);
        } elseif ($algorithm === 'v5') {
            try {
                $fetcher = new MojangUUIDFetcher();
                $uuids = $fetcher->fetchUUIDs([$name]);
                $uuid = $uuids[strtolower($name)] ?? null;
                if (!$uuid) {
                    // Fallback to offline UUID
                    $uuid = UUID::generateUuidV3($name);
                    Log::info("使用离线UUID添加玩家 {$name}");
                }
            } catch (\Throwable $e) {
                // Fallback to offline UUID on error
                $uuid = UUID::generateUuidV3($name);
                Log::warning("Mojang UUID 获取失败：{$e->getMessage()}，已使用离线UUID添加玩家 {$name}");
            }
        } else {
            // 默认使用 v4 随机 UUID（理论上不会冲突，但不推荐用作识别标识）
            $uuid = RamseyUuid::uuid4()->getHex()->toString();
        }

        DB::table('uuid')->insert([
            'pid' => $player->pid,
            'name' => $name,
            'uuid' => $uuid,
        ]);
    }
}

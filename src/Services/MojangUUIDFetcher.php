<?php
namespace LittleSkin\YggdrasilConnect\Services;
class MojangUUIDFetcher {
    private string $cacheFile;

    public function __construct(string $cacheFile = __DIR__ . '/uuid_cache.json') {
        $this->cacheFile = $cacheFile;
        if (!file_exists($this->cacheFile)) {
            file_put_contents($this->cacheFile, json_encode([]));
        }
    }

    /**
     * 查询多个用户名的 UUID
     * @param array $usernames
     * @return array [用户名 => UUID]
     */
    public function fetchUUIDs(array $usernames): array {
        $usernames = array_unique(array_map('strtolower', $usernames));
        $cache = $this->loadCache();
        $result = [];

        $toFetch = [];

        foreach ($usernames as $name) {
            if (isset($cache[$name])) {
                $result[$name] = $cache[$name];
            } else {
                $toFetch[] = $name;
            }
        }

        // 分批请求 Mojang API（最多10个一批）
        foreach (array_chunk($toFetch, 10) as $batch) {
            $fetched = $this->fetchFromAPI($batch);
            foreach ($fetched as $entry) {
                $lowerName = strtolower($entry['name']);
                $cache[$lowerName] = $entry['id'];
                $result[$lowerName] = $entry['id'];
            }
            // 避免 API 限制（如需要可 sleep(1);）
        }

        // 保存缓存
        $this->saveCache($cache);

        return $result;
    }

    private function fetchFromAPI(array $usernames): array {
        $url = "https://api.mojang.com/profiles/minecraft";
        $headers = [
            'Content-Type: application/json'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($usernames),
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            throw new Exception("API request failed with status code $statusCode");
        }

        return json_decode($response, true) ?? [];
    }

    private function loadCache(): array {
        return json_decode(file_get_contents($this->cacheFile), true) ?? [];
    }

    private function saveCache(array $cache): void {
        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }
}

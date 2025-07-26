<?php
namespace webhubx\core;

/**
 * MtCache is a wrapper class for caching mechanisms with configurable key prefix.
 * 
 *    Author: Web Hub
 *    Date: 2018-07-18
 */
class MtCache {
    private $config;
    private $redis;
    private $keyPrefix;

    /**
     * Constructor with dependency injection
     * 
     * @param array $config Configuration array with:
     *     - method: 'file' or 'redis'
     *     - file_dir: Directory for file cache (if method is 'file')
     *     - ttl: Cache time-to-live in seconds
     *     - key_prefix: Optional prefix for cache keys (default: 'th_')
     * @param \Redis|null $redis Redis connection (required if method is 'redis')
     */
    public function __construct(array $config, $redis=null) {
        $this->validateConfig($config);
        $this->config = $config;
        $this->redis = $redis;
        $this->keyPrefix = $config['key_prefix'] ?? 'th_';
    }

    /**
     * Validate the configuration
     * 
     * @param array $config
     * @throws \InvalidArgumentException
     */
    private function validateConfig(array $config) {
        if (!in_array($config['method'] ?? null, ['file', 'redis'])) {
            throw new \InvalidArgumentException("Invalid cache method. Must be 'file' or 'redis'");
        }

        if (($config['method'] === 'file') && empty($config['file_dir'])) {
            throw new \InvalidArgumentException("file_dir must be specified for file cache method");
        }

        if (($config['method'] === 'file') && !is_writable($config['file_dir'])) {
            throw new \InvalidArgumentException("Cache directory is not writable");
        }

        if (!isset($config['ttl']) || !is_numeric($config['ttl'])) {
            throw new \InvalidArgumentException("TTL must be a number");
        }
    }

    /**
     * Set the cache key prefix
     * 
     * @param string $prefix The prefix to use for cache keys
     * @return self
     */
    public function setKeyPrefix(string $prefix): self {
        $this->keyPrefix = $prefix;
        return $this;
    }

    /**
     * Get the current cache key prefix
     * 
     * @return string
     */
    public function getKeyPrefix(): string {
        return $this->keyPrefix;
    }

    /**
     * Form a cache key from tenant handle and URI
     * 
     * @param string $thandle Tenant handle
     * @param string $uri URI to cache
     * @return string Cache key
     */
    public function formKey(string $thandle, string $uri): string {
        return $this->keyPrefix . $thandle . '_' . md5($uri);
    }

    /**
     * Clear cache by specific key
     * 
     * @param string $key Cache key to clear
     */
    public function clearByKey(string $key) {
        if ($this->config['method'] === 'file') {
            $filename = $this->config['file_dir'] . '/' . $key . '.json';
            if (file_exists($filename)) {
                unlink($filename);
            }
        } else if ($this->config['method'] === 'redis') {
            $this->redis->del($key);
        }
    }

    /**
     * Clear all cache for a specific tenant
     * 
     * @param string $thandle Tenant handle
     */
    public function clearTenantCache(string $thandle) {
        if ($this->config['method'] === 'file') {
            $files = glob($this->config['file_dir'] . '/' . $this->keyPrefix . $thandle . '_*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        } else if ($this->config['method'] === 'redis') {
            $keys = $this->redis->keys($this->keyPrefix . $thandle . '_*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        }
    }

    /**
     * Save data to cache
     * 
     * @param string $thandle Tenant handle
     * @param string $uri URI being cached
     * @param mixed $data Data to cache
     * @return bool True on success, false on failure
     */
    public function save(string $thandle, string $uri, $data): bool {
        $cacheKey = $this->formKey($thandle, $uri);

        if ($this->config['method'] === 'file') {
            $filename = $this->config['file_dir'] . '/' . $cacheKey . '.json';
            return file_put_contents($filename, json_encode($data)) !== false;
        } else if ($this->config['method'] === 'redis') {
            if($this->config['ttl'] <= 0) {
                return $this->redis->set($cacheKey, json_encode($data, JSON_UNESCAPED_UNICODE));
            }else{
                return $this->redis->setex($cacheKey, $this->config['ttl'], json_encode($data));
            }
        }

        return false;
    }

    /**
     * Get data from cache
     * 
     * @param string $thandle Tenant handle
     * @param string $uri URI to retrieve
     * @return mixed|null Cached data or null if not found
     */
    public function get(string $thandle, string $uri) {
        $cacheKey = $this->formKey($thandle, $uri);

        if ($this->config['method'] === 'file') {
            $filename = $this->config['file_dir'] . '/' . $cacheKey . '.json';
            if (file_exists($filename) && (time() - filemtime($filename) < $this->config['ttl'])) {
                $data = file_get_contents($filename);
                return json_decode($data, true);
            }
        } else if ($this->config['method'] === 'redis') {
            $data = $this->redis->get($cacheKey);
            if ($data !== false) {
                return json_decode($data, true);
            }
        }

        return null;
    }

    /**
     * Clear all cached data
     */
    public function clearAll() {
        if ($this->config['method'] === 'file') {
            $files = glob($this->config['file_dir'] . '/*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        } else if ($this->config['method'] === 'redis') {
            $this->redis->flushAll();
        }
    }

    /**
     * Get the current cache configuration
     * 
     * @return array Current configuration
     */
    public function getConfig(): array {
        return $this->config;
    }
}
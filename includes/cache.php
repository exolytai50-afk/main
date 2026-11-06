<?php
/**
 * GigaChefAI — система кэширования
 * Простая файловая система кэширования для API ответов
 */

/**
 * Получение данных из кэша
 * 
 * @param string $key Ключ кэша
 * @return mixed|null Данные из кэша или null, если кэш не найден или истек
 */
function cache_get(string $key): mixed
{
    if (!CACHE_ENABLED) {
        return null;
    }
    
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.json';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $data = json_decode(file_get_contents($cacheFile), true);
    
    if (!$data || !isset($data['expires_at']) || !isset($data['value'])) {
        return null;
    }
    
    // Проверка срока действия
    if (time() > $data['expires_at']) {
        @unlink($cacheFile);
        return null;
    }
    
    return $data['value'];
}

/**
 * Сохранение данных в кэш
 * 
 * @param string $key Ключ кэша
 * @param mixed $value Данные для кэширования
 * @param int $ttl Время жизни кэша в секундах
 * @return bool Успешность операции
 */
function cache_set(string $key, mixed $value, int $ttl = 600): bool
{
    if (!CACHE_ENABLED) {
        return false;
    }
    
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.json';
    
    $data = [
        'value' => $value,
        'expires_at' => time() + $ttl,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
}

/**
 * Удаление данных из кэша
 * 
 * @param string|null $key Ключ кэша или null для очистки всего кэша
 * @return bool Успешность операции
 */
function cache_delete(?string $key = null): bool
{
    if (!is_dir(CACHE_DIR)) {
        return true;
    }
    
    if ($key) {
        $cacheFile = CACHE_DIR . '/' . md5($key) . '.json';
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        return true;
    }
    
    // Очистка всего кэша
    $files = glob(CACHE_DIR . '/*.json');
    foreach ($files as $file) {
        @unlink($file);
    }
    
    return true;
}

/**
 * Очистка устаревшего кэша
 * 
 * @return int Количество удаленных файлов
 */
function cache_cleanup(): int
{
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $deleted = 0;
    $files = glob(CACHE_DIR . '/*.json');
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || !isset($data['expires_at']) || time() > $data['expires_at']) {
            @unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

/**
 * Генерация ключа кэша для поиска ресторанов
 * 
 * @param array $params Параметры поиска
 * @return string Ключ кэша
 */
function restaurant_cache_key(array $params): string
{
    $cacheParams = [
        'city' => $params['city'] ?? '',
        'query' => $params['query'] ?? '',
        'cuisine' => $params['cuisine'] ?? '',
        'price' => $params['price'] ?? '',
        'min_rating' => $params['min_rating'] ?? '',
        'radius' => $params['radius'] ?? '',
        'page' => $params['page'] ?? 1,
        'limit' => $params['limit'] ?? 20
    ];
    
    return 'restaurant_search_' . md5(json_encode($cacheParams, JSON_UNESCAPED_UNICODE));
}
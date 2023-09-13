<?php

namespace CloudCastle\ConfigCache;

use CloudCastle\AbstractClasses\Singleton;
use CloudCastle\Storage\EDisks;
use CloudCastle\Storage\Storage;
use CloudCastle\Storage\StorageInterface;

final class Cache extends Singleton
{
    private string|null $path = null;
    private StorageInterface|null $disk = null;

    public function create(string $path): bool
    {
        $path = $this->disk->path($path);
        $name = $this->getBaseName($path);
        $data = [];
        $cacheStr = '';

        if ($this->disk->isFile($path)) {
            $data[] = $path;
        }

        if ($this->disk->isDir($path)) {
            $data = $this->disk->get($path);
        }

        if ($data) {
            $cacheStr = $this->getCacheFileContent($data);
        }

        return $this->disk->put($this->getCacheFilePath($name), $cacheStr);
    }

    private function getBaseName(string $path): string
    {
        return preg_replace('~^(\w+)\.(\w{3,})$~u', '$1', basename($path));
    }

    private function getCacheFileContent(array $data): string
    {
        $str = '<?php' . PHP_EOL . PHP_EOL . '%start% return [' . PHP_EOL;
        $start = '';

        foreach ($data as $item) {
            $replace = $this->getConfContent($item);

            if ($replace) {
                $str .= "'" . $this->getBaseName($item) . "' => " . $replace['end'] . ',' . PHP_EOL;
                $start .= PHP_EOL.$replace['start'];
            }
        }

        $str .= " ];";

        return preg_replace(['~%start%~', '~;~', '~( +)~'], [$start, ";".PHP_EOL, ' '], $str);
    }

    private function getConfContent(string $file): array|false
    {
        $str = str_replace("\n", '', $this->disk->get($file));

        if(preg_match('~<\?php(?<start>.+)?(return(?<end>.+));~', $str, $matches))        {
            return ['end' => $matches['end'], 'start' => $matches['start']];
        }

        return ['end' => null, 'start' => null];
    }

    private function getCacheFilePath(string $name): string
    {
        return $this->getPath() . DIRECTORY_SEPARATOR . md5($name) . '.php';
    }

    public function getPath(): string|null
    {
        return $this->path;
    }

    /**
     * @throws CacheException
     */
    public function setPath(string $dir): self
    {
        $this->disk = Storage::disk(EDisks::LOCAL);

        if ($this->disk->mkDir($dir)) {
            $this->path = $this->disk->path($dir);

            return $this;
        }

        throw new CacheException(sprintf("Не удалось назначить %s как дирректорию для кеширования файлов", $dir));
    }

    public function delete(string $name): bool
    {
        $file = $this->getCacheFilePath($name);

        if ($this->disk->isDir($file)) {
            $this->disk->rm($file);
        }

        return $this->check($name);
    }

    public function check(string $name): bool
    {
        $file = $this->getCacheFilePath($name);

        return file_exists($file);
    }
}
<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\filesystem;

use League\Flysystem\FilesystemAdapter; 
use League\Flysystem\Filesystem;
use RuntimeException;
use think\Cache;
use think\File;

/**
 * Class Driver
 * @package think\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Cache */
    protected $cache;

    /** @var Filesystem */
    protected $filesystem;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(Cache $cache, array $config)
    {
        $this->cache  = $cache;
        $this->config = array_merge($this->config, $config);

        $adapter          = $this->createAdapter();
        $this->filesystem = $this->createFilesystem($adapter);
    }
 

    abstract protected function createAdapter(): FilesystemAdapter;

    protected function createFilesystem(FilesystemAdapter $adapter): Filesystem
    { 
        $config = array_intersect_key($this->config, array_flip(['visibility', 'disable_asserts', 'url']));

        return new Filesystem($adapter, $config);
    }

    /**
     * 获取文件完整路径
     * @param string $path
     * @return string
     */
    public function path(string $path): string
    {
        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof FilesystemAdapter) {
            return $adapter->applyPathPrefix($path);
        }

        return $path;
    }

    protected function concatPathToUrl($url, $path)
    {
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    public function url(string $path): string
    {
        throw new RuntimeException('This driver does not support retrieving URLs.');
    }

    /**
     * 保存文件
     * @param string $path 路径
     * @param File $file 文件
     * @param null|string|\Closure $rule 文件名规则
     * @param array $options 参数
     * @return bool|string
     */
    public function putFile(string $path, File $file, $rule = null, array $options = [])
    {
        return $this->putFileAs($path, $file, $file->hashName($rule), $options);
    }

    /**
     * 指定文件名保存文件
     * @param string $path 路径
     * @param File $file 文件
     * @param string $name 文件名
     * @param array $options 参数
     * @return bool|string
     */
    public function putFileAs(string $path, File $file, string $name, array $options = [])
    {
        $stream = fopen($file->getRealPath(), 'r');
        $path   = trim($path . '/' . $name, '/'); 
        $result = $this->writeStream($path, $stream, $options); 
        if (is_resource($stream)) {
            fclose($stream);
        }
        return $path;
    }

    public function __call($method, $parameters)
    {
        return $this->filesystem->$method(...$parameters);
    }
}

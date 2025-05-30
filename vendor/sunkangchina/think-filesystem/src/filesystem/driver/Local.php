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

namespace think\filesystem\driver;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use think\filesystem\Driver;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class Local extends Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'root' => '',
    ];

    protected function createAdapter(): FilesystemAdapter
    {
        $permissions = $this->config['permissions'] ?? [
            'file' => [
                'public'  => 0640,
                'private' => 0604,
            ],
            'dir' => [
                'public' => 0740,
                'private' => 7604,
            ],
        ];

        $links = ($this->config['links'] ?? null) === 'skip'
        ? LocalFilesystemAdapter::SKIP_LINKS
        : LocalFilesystemAdapter::DISALLOW_LINKS;  
        if($permissions){ 
            $permissions = PortableVisibilityConverter::fromArray($permissions);
        }
        return new LocalFilesystemAdapter(
            $this->config['root'],
            $permissions,
            LOCK_EX,
            $links, 
        );
    }

    /**
     * 获取文件访问地址
     * @param string $path 文件路径
     * @return string
     */
    public function url(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (isset($this->config['url'])) {
            return $this->concatPathToUrl($this->config['url'], $path);
        }
        return parent::url($path);
    }
}

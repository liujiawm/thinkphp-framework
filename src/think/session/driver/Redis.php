<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\session\driver;

use think\Exception;
use think\session\SessionHandler;

class Redis implements SessionHandler
{
    /** @var \Redis */
    protected $handler = null;
    protected $config  = [
        'host'       => '127.0.0.1', // redis主机
        'port'       => 6379, // redis端口
        'password'   => '', // 密码
        'select'     => 0, // 操作库
        'expire'     => 3600, // 有效期(秒)
        'timeout'    => 0, // 超时时间(秒)
        'persistent' => true, // 是否长连接
        'name'       => '', // session key前缀
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);

        $this->init();
    }

    /**
     * 打开Session
     * @access public
     * @return bool
     * @throws Exception
     */
    public function init(): bool
    {
        if (extension_loaded('redis')) {
            $this->handler = new \Redis;

            // 建立连接
            $func = $this->config['persistent'] ? 'pconnect' : 'connect';
            $this->handler->$func($this->config['host'], $this->config['port'], $this->config['timeout']);

            if ('' != $this->config['password']) {
                $this->handler->auth($this->config['password']);
            }

            if (0 != $this->config['select']) {
                $this->handler->select($this->config['select']);
            }
        } elseif (class_exists('\Predis\Client')) {
            $params = [];
            foreach ($this->config as $key => $val) {
                if (in_array($key, ['aggregate', 'cluster', 'connections', 'exceptions', 'prefix', 'profile', 'replication'])) {
                    $params[$key] = $val;
                    unset($this->config[$key]);
                }
            }
            $this->handler = new \Predis\Client($this->config, $params);
        } else {
            throw new \BadFunctionCallException('not support: redis');
        }

        return true;
    }

    /**
     * 读取Session
     * @access public
     * @param  string $sessID
     * @return string
     */
    public function read(string $sessID): array
    {
        return $this->handler->get($this->config['prefix'] . $sessID);
    }

    /**
     * 写入Session
     * @access public
     * @param  string $sessID
     * @param  array  $data
     * @return bool
     */
    public function write(string $sessID, array $data): bool
    {
        if ($this->config['expire'] > 0) {
            $result = $this->handler->setex($this->config['prefix'] . $sessID, $this->config['expire'], $data);
        } else {
            $result = $this->handler->set($this->config['prefix'] . $sessID, $data);
        }

        return $result ? true : false;
    }

    /**
     * 删除Session
     * @access public
     * @param  string $sessID
     * @return bool
     */
    public function delete(string $sessID): bool
    {
        return $this->handler->delete($this->config['prefix'] . $sessID) > 0;
    }

}

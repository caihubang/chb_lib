<?php

/**
 * 通用redis工具
 */

namespace chb_lib\common;

use think\facade\Cache;

trait RedisTool
{

    protected $redisHook = [];
    protected $redisKey = 'RedisTool:';
    protected $redisTime = 60 * 30;
    protected $redisKeyName = null;

    public function __get($name)
    {
        if ($name == 'objRedis') {
            $this->objRedis = Cache::store("redis")->handler();
            return $this->objRedis;
        }
    }

    /**
     * 获取数据,经过redis
     * @param string $action 访问方法
     * @param array $params 参数 数组/字符串
     * @param false $default 是否穿透缓存 true / false
     * @return mixed
     */
    public function getDataByRedisHook($action, $params, $default = false)
    {
        $redisName = $this->redisKey . $action . ':' . $this->md5Key($params);
        $ret = $this->setRedisKey($redisName)->getRedisArray();
        if (!empty($ret) && !$default) {
            return $ret;
        }
        if (!method_exists($this, $action)) {
            exception("redis:访问方法不存在");
        }
        if ($default) {
            return $this->$action($params);
        }
        $this->redisHook[$action] = 1;//记录访问次数
        $data = $this->$action($params);
        if (is_bool($data)) {
            return $data;
        }
        $this->setRedisKey($redisName)->setRedisValue($data);
        return $data;
    }

    /**
     * 右边入队列
     */
    public function rPush($value)
    {
        return $this->objRedis->rPush($this->getRedisKayName(), $value);
    }

    /**
     * 左边出队列
     */
    public function blPop()
    {
        if (empty($this->llen())) {
            return null;
        }
        $i = 0;
        while (true) {
            //从右边（rPush）入队，左边阻塞出队
            $data = $this->objRedis->blPop($this->getRedisKayName(), 10);
            if ($data) {
                return $data;
            } else {
                sleep(1);
                ++$i;
            }
            if ($i >= 10) { //十秒超时
                exception("请求超时", 502);
            }
        }
    }

    /**
     * 检查队列长度
     */
    public function llen()
    {
        return $this->objRedis->llen($this->getRedisKayName());
    }

    /**
     * Md5 加密一下key
     */
    public function md5Key($param, $default = false)
    {
        if (empty($param)) {
            return '';
        }
        if (is_array($param)) {
            return md5(json_encode($param));
        } else {
            if ($default === true) {
                return md5($param);
            }
            return $param;
        }
    }

    protected function getRedisKayName()
    {
        if (empty($this->redisKeyName)) {
            $this->redisKeyName = $this->redisKey;
        }
        return $this->redisKeyName;
    }

    /**
     * 设置redis的key
     */
    public function setRedisKey($redisKey)
    {
        $this->redisKeyName = $redisKey;
        return $this;
    }

    /**
     * 设置缓存时间
     */
    public function setRedisTime($redisTime)
    {
        $this->redisTime = $redisTime;
        return $this;
    }

    /**
     * 读取字符串型的redis
     */
    public function getRedisArray()
    {
        $data = $this->objRedis->get($this->getRedisKayName());
        if (!empty($data)) {
            return json_decode($data, true);
        }
        return null;
    }

    /**
     * 读取字符串型的redis
     */
    public function getRedisString()
    {
        return $this->objRedis->get($this->getRedisKayName());
    }

    /**
     * 保存数组型的redis
     */
    public function setRedisValue($value = null)
    {
        if (empty($value)) {
            return true;
        }
        if (is_array($value)) {
            $saveValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $saveValue = $value;
        }
        return $this->objRedis->setex($this->getRedisKayName(), $this->redisTime, $saveValue);
    }

    public function delRedis()
    {
        $keyName = $this->getRedisKayName();
        if (strchr($keyName, ":")) {
            $redisKey = explode(":", $keyName)[0] . ":";
        } else {
            $redisKey = $keyName;
        }
        $keys = $this->objRedis->keys($redisKey . '*');
        if (!empty($keys)) {
            $this->objRedis->del($keys);
        }
        return true;
    }
}

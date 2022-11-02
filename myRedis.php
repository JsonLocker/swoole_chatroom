<?php

class MyRedis{
    private $redis = null;
    private static $_instance=null;

    private function __construct(){
        $redis = new Redis();
        try{
            $redis->connect("127.0.0.1", "6379");
        }catch(Exception $e){
            die("缓存连接失败 : " . $e->getMessage());
        }
        $this->redis = &$redis;
    }

    public static function get_instance(){
        if(null == self::$_instance){
           return  new self;
        }else{
            return self::$_instance;
        }
    }

    public function __clone(){}

    public function __destruct(){
        $this->redis->close();
    }

    public function set($name,$val,$expire=0){
        if($expire == 0){
            return $this->redis->set($name,$val);
        }else{
            return $this->redis->set($name,$val,$expire);
        }
    }

    public function get($name){
        return $this->redis->get($name);
    }

    // 设置hash值
    public function hset($key,$name,$val){
        return $this->redis->hset($key,$name,$val);
    }

    // 获取hash值
    public function hget($key,$name){
        return $this->redis->hget($key,$name);
    }

    //删除hash行
    public function hDel($key, $name){
        return $this->redis->hDel($key, $name);
    }

    // 获取hash表所有值
    public function hGetAll($name){
        return $this->redis->hGetAll($name);
    }

    // 设置有效时间(s)
    public function expire($key, $seconds){
        return $this->redis->expire($key, $seconds);
    }

    //获取剩余有效时间(s)
    public function ttl($key, $milliseconds=false){
        if($milliseconds== false){
            return $this->redis->ttl($key);
        }else{
            return $this->redis->pttl($key);
        }
    }

}

/*
$a = MyRedis::get_instance();
$a->expire('classid_13', 0);
$a->expire('history-classid_13', 0);
*/
//echo $a->ttl('user');
//$a->hset('user', 'sex', 'femail');
//$a->hset('user', 'se1x', 'femail');
//echo $a->hGet("classid_13", "1").PHP_EOL;
//var_dump($a->hGetAll("classid_13"));

<?php

class WebSocketServer
{
    private static $_serv;

    public function __construct()
    {
        $this->_serv = new swoole_websocket_server("0.0.0.0", 9502, SWOOLE_PROCESS,SWOOLE_SOCK_TCP|SWOOLE_SSL);
        $this->_serv->set([
            'worker_num' => 1,
            // 防止内存泄露
            // 'task_worker_num' => 1,
            'max_request' => 3,
            'task_max_request' => 4,
            //配置ssl
            "ssl_cert_file" => "/www/server/panel/vhost/ssl/zh.haomooc.com/fullchain.pem",
            "ssl_key_file" => "/www/server/panel/vhost/ssl/zh.haomooc.com/privkey.pem",
            //防止粘包
            'open_eof_check' => true, //打开EOF检测
            'package_eof' => "\r\n", //设置EOF
            'open_eof_split' => true,
            // 设置心跳
            'heartbeat_check_interval' => 30,
            'heartbeat_idle_time' => 62,
             // 后台显示
            'daemonize' => true,  //后台运行
            'log_file' => __DIR__ . '/server.log',
        ]);
        $this->_serv->on('start', [$this, 'onStart']);
        $this->_serv->on('open', [$this, 'onOpen']);
        $this->_serv->on('message', [$this, 'onMessage']);
        $this->_serv->on('close', [$this, 'onClose']);
    }

    /**
     * 客户端请求连接时,根据携带参数,发送缓存班级名单
     * @param $serv
     * @param $request
     */
    public function onOpen($serv, $request)
    {
        self::checkAccess($serv, $request);
        //echo "server: handshake success with fd{$request->fd}.\n";
    }

    /**
     * 根据信息类型处理对应事件
     * 消息类型 : 群聊 group_chat /撤回 sendback  /点名 take_roll /禁言 deny_chat  /入班 login  /出班级 logout /心跳检测 heartCheck 
     * @param $serv
     * @param $frame
     */
    public function onMessage($serv, $frame)
    {
        $result = json_decode($frame->data, true);
        if(!isset($result['type'])){
            $msgStr = json_encode(['code'=>403,'msg'=>"格式错误 时间: ".date("m-d H:i:s")]);
            $serv->push($frame->fd, $msgStr);
            return false;
        }

        switch($result['type']){
            case "login" : 
                Work::joinRoom($serv, $frame);
                break;
            case "group_chat":
                Work::groupChat($serv, $frame);
                break;
            case "sendback":
                Work::sendback($serv, $frame);
                break;
            case "heartCheck":
                $msgStr = json_encode(['code'=>200,'type'=>'heartCheck','msg'=>"heartCheck ".date(" m-d H:i:s")]);
                $serv->push($frame->fd, $msgStr);
                break;
            default:
                // 点名 take_roll 确认点名_check_roll 和 禁言deny_chat 共用
                Work::bordcast($serv, $frame);
        }
    }
    
    // 用户离开动作
    public function onClose($serv, $fd) {
        Work::leaveRoom($serv, $fd);
        //echo "client {$fd} closed.\n";
    }

    public function onStart(){
        //进程名称
        swoole_set_process_name("live");
    }
    
    public function start() {
        require('./work.php');
        //swoole_set_process_name("live");
        $this->_serv->start();
    }

    /**
     *  校验客户端连接的合法性,无效的连接不允许连接
     *  token : md5(sha1(class_id + user_id))
     *  @data array 客户端发过来的消息
     *  @return bool
     */
    private static function checkAccess($serv, $request){
        if (!isset($request->get) || !isset($request->get['uid']) || !isset($request->get['token'])) {
            $serv->close($request->fd);
            return false;
        }
        $checkToken = md5(sha1($request->get['uid']));
        if ( $request->get['token'] != $checkToken ) {
            $serv->close($request->fd);
            return false;
        }
        return true;
    }

}


$server = new WebSocketServer;
$server->start();


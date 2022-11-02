<?php
require('./myRedis.php');

class Work{

    // 把进入的学员写入班级成员缓存 hSet
    public static function joinRoom($serv, $frame){
        // 准备数据
        $data_arr = json_decode($frame->data, true);

        // 1. 轮询班级 hGetAll
        $redis  = MyRedis::get_instance();
        $members = $redis->hGetAll($data_arr['class_id']);

        // 2. 向已有学员发送进入班广播 (swoole 异步处理)
        if(!empty($members)){
            foreach( array_keys($members) as $fid ){
                if($serv->exist($fid)){
                    $msgStr = json_encode(['code'=>200, 'type' => 'login', 'user_id' => $data_arr['user_id']]);
                    $serv->push($fid , $msgStr);
                }else{
                    $redis->hDel($data_arr['class_id'], $fid);
                    $redis->hDel('online', $fid);
                }
            } 
        }

        // 3. 当前学员写入班级缓存 和 在线人员缓存
        $redis->hset($data_arr['class_id'], $frame->fd, $data_arr['user_id']);
        $redis->hset('online',$frame->fd,$data_arr['class_id']);

        // 4. 发送在线班级成员列表
        $newMembers = $redis->hGetAll($data_arr['class_id']);
        $msgStr = json_encode(['code'=>200,'type'=>'members','members'=> array_values($newMembers)]); 
        $serv->push($frame->fd, $msgStr);

        // 4. 向学员发送班级聊天记录
        $history = $redis->hGetAll('history-'.$data_arr['class_id']);
        $history_data = json_encode(['code'=>200,'type'=>'history','history'=> array_values($history) ]); 
        $serv->push($frame->fd, $history_data);
    }

    // 群聊事件
    public static function groupChat($serv, $frame){
        $data_arr = json_decode($frame->data, true);
        $data_arr['code'] = 200;
        $data_arr['created_at'] = time();
        $msgStr = json_encode($data_arr);
        //$msgStr = json_encode(['code'=>200,'msg'=> $data_arr ]);

        // 1. 轮询班级 hGetAll
        $redis  = MyRedis::get_instance();
        $members = $redis->hGetAll($data_arr['class_id']);

        // 向成员发送新消息
        if(!empty($members)){
            foreach( array_keys($members) as $fid ){
                if($serv->exist($fid)){
                    $serv->push($fid , $msgStr);
                }else{
                    $redis->hDel($data_arr['class_id'], $fid);
                    $redis->hDel('online', $fid);
                }
            } 
        }

        //2. 把聊天写入缓存
        //[classid, message_id, [user_id,message_id, message]]
        $message_id = $data_arr['msg_id'];
        $sendMsg = json_encode([
            'msg_id' => $data_arr['msg_id'],
            'user_id' => $data_arr['user_id'],
            'data' => $data_arr['data'],
            'created_at' => $data_arr['created_at']
        ]);
        $redis->hset('history-'.$data_arr['class_id'], $data_arr['msg_id'], $sendMsg);
    }


    // 聊天撤回事件
    public static function sendback($serv, $frame){
        $data_arr = json_decode($frame->data, true);

        $data_arr['code'] = 200;
        $msgStr = json_encode($data_arr);
        //$msgStr = json_encode(['code'=>200,'msg'=> $data_arr ]);

        // 1. 轮询班级 hGetAll
        $redis  = MyRedis::get_instance();
        $members = $redis->hGetAll($data_arr['class_id']);

        // 向成员发送新消息
        if(!empty($members)){
            foreach( array_keys($members) as $fid ){
                if($serv->exist($fid)){
                    $serv->push($fid , $msgStr);
                }else{
                    $redis->hDel($data_arr['class_id'], $fid);
                    $redis->hDel('online', $fid);
                }
            } 
        }
        
        //2. 缓存删除聊天
        $redis->hDel('history-'.$data_arr['class_id'], $data_arr['msg_id']);
        
    }


    // 点名 禁言 动作，默认直接向该班级转播
    public static function bordcast($serv, $frame){
        $data_arr = json_decode($frame->data, true);

        $data_arr['code'] = 200;
        $msgStr = json_encode($data_arr);

        // 1. 轮询班级 hGetAll
        $redis  = MyRedis::get_instance();
        $members = $redis->hGetAll($data_arr['class_id']);

        // 向成员发送新消息
        if(!empty($members)){
            foreach( array_keys($members) as $fid ){
                if($serv->exist($fid)){
                    $serv->push($fid , $msgStr);
                }else{
                    $redis->hDel($data_arr['class_id'], $fid);
                    $redis->hDel('online', $fid);
                }
            } 
        }
    }

    // 退出房间动作
    public static function leaveRoom($serv, $fd){
        // 1. 准备数据
        $redis  = MyRedis::get_instance();

        // 根据fid得到班级和用户id
        $class_id= $redis->hGet('online', $fd);
        $user_id = $redis->hGet($class_id, $fd);

        //$data_arr = ['type' => 'logout', 'user_id' => $user_id];
        $msgStr = json_encode(['code'=>200, 'type' => 'logout', 'user_id' => $user_id]);

        // 2. 当前学员踢出班级缓存 和 在线缓存
        $redis->hDel($class_id, $fd);
        $redis->hDel('online', $fd);

        // 3. 轮询班级 hGetAll
        $members = $redis->hGetAll($class_id);

        // 4. 向已有学员发送进入班广播 (swoole 异步处理)
        if(!empty($members)){
            foreach( array_keys($members) as $fid ){
                if($serv->exist($fid)){
                    $serv->push($fid , $msgStr);
                }else{
                    $redis->hDel($data_arr['class_id'], $fid);
                    $redis->hDel('online', $fid);
                }
            } 
        }
    }


}



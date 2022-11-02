<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://xc-haomooc.oss-cn-hangzhou.aliyuncs.com/xc_js/bootstrap.min.css" rel="stylesheet">
    <title>聊天室</title>
</head>
<body>

<div class="container">
    <div class="row mt-5">
        <div class="col">
            <div class="d-flex justify-content-between pb-3">
                <h5> 聊天框</h5>
                <div class="text-end">
                    <button class="btn btn-sm btn-warning mb-3" onClick="takeRollBtn(this)">点名</button>
                    <a class="btn btn-sm btn-danger mb-3"  onClick="denyChatBtn(this)">禁言</a>
                </div>
            </div>
            <div class="list-group bg-light py-3 overflow-auto" id="chatlist" style="max-height: 40vh; height:30vh">
            </div>

            <hr/>
            <h5> 用户列表</h5>
            <div class="list-group bg-light py-3 overflow-auto" id="userlist" style="max-height: 20vh;">
            </div>

            <div class="row g-3 py-3 shadow bg-light my-3">
                <div class="col-12">
                    <button class="btn btn-outline-danger mb-3 w-100 visually-hidden" id="checkRollBtn" onClick="checkRollBtn(this)">点名确认</button>
                </div>
                
                <div class="col">
                    <label for="inputPassword2" class="visually-hidden">Password</label>
                    <input type="text" class="form-control" placeholder="输入文字">
                </div>
                <div class="col-auto user-select-none">
                    <button class="btn btn-primary mb-3" data-id='0' id="sendBtn"  onClick="sendBtnHandler(this)">发送</button>
                </div>
            </div>
        </div>
    </div>
</div>



<?php
$uid = uniqid();
$token = md5(sha1($uid));
?>


<script>
var ws;//websocket实例
var lockReconnect = false;//避免重复连接
var wsUrl = "wss://zh.haomooc.com:9502?uid=<?=$uid?>&token=<?= $token ?>";

// 创建链接
function createWebSocket(url) {
    try {
        ws = new WebSocket(url);
        initEventHandle();
    } catch (e) {
        reconnect(url);
    }
}

// 初始化
function initEventHandle() {
    ws.onclose = function () {
        reconnect(wsUrl);
        console.log("onclose");
    };
    ws.onerror = function () {
        reconnect(wsUrl);
        console.log("on error");
    };
    ws.onopen = function () {
        //心跳检测重置
        heartCheck.reset().start();
        send("login","login")
            console.log("Connected to WebSocket server.");
    };
    ws.onmessage = function (event) {
        //如果获取到消息，心跳检测重置
        //拿到任何消息都说明当前连接是正常的
        heartCheck.reset().start();
        let obj = JSON.parse(event.data)
            onMessageCallBack(obj)//逻辑处理
    }
}


// 重新链接
function reconnect(url) {
    if(lockReconnect) return;
    lockReconnect = true;
    //没连接上会一直重连，设置延迟避免请求过多
    setTimeout(function () {
        createWebSocket(url);
        lockReconnect = false;
    }, 5000);
}

//心跳检测
var heartCheck = {
    timeout: 60000,//60秒
    timeoutObj: null,
    serverTimeoutObj: null,
    reset: function(){
        clearTimeout(this.timeoutObj);
        clearTimeout(this.serverTimeoutObj);
        return this;
    },
    start: function(){
        var self = this;
        this.timeoutObj = setTimeout(function(){
            console.log("client send heartCheck")
                //这里发送一个心跳，后端收到后，返回一个心跳消息，
                //onmessage拿到返回的心跳就说明连接正常
                send("","heartCheck");
            self.serverTimeoutObj = setTimeout(function(){//如果超过一定时间还没重置，说明后端主动断开了
                ws.close();//如果onclose会执行reconnect，我们执行ws.close()就行了.如果直接执行reconnect 会触发onclose导致重连两次
            }, self.timeout);
        }, this.timeout);
    }
}

createWebSocket(wsUrl);


// 发送群聊回调
function onMessageCallBack(data){
    switch(data.type){
    case "login":
        // 新用户登录,渲染在线列表
        addNewMember(data.user_id)
        break;
    case "logout":
        // 用户登出,渲染在线列表
        removeMember(data.user_id)
        break;
    case "members": 
        // 新用户渲染在线成员列表
        document.querySelector("#userlist").innerHTML = "" 
        data.members.forEach(function(member){
            addNewMember(member)
        })
        break;
    case "history":
        // 新用户渲染聊天记录
        document.querySelector("#chatlist").innerHTML = "" 
        data.history.forEach((item)=>{
            drawChatList(JSON.parse(item))
        })
        break;
    case "group_chat":
        // 收到聊天,渲染聊天对话框  msg_id : [ msg_id, user_id, message, timestamp ]
        drawChatList(data); 
        break;
    case "sendback":
        // 撤回聊天
        sendback(data); 
        break;
    case "take_roll":
        // 收到点名
        take_roll(data, true)
        break;
    case "check_roll":
        //确认点名
        take_roll(data, false)
        break;
    case "deny_chat":
        // 禁言
        deny_chat(data);
        break;
    default: //heartCheck
        console.log(data.msg)
    }
    //console.log(data)
}

// 发送班级聊天按钮
function sendBtnHandler(evt){
    let parentNode = evt.closest('.row')
    let words = parentNode.querySelector('input').value.trim()
    if(words != ""){
        send(words)
        parentNode.querySelector('input').value = ""
    }
}

// 点名按钮
function takeRollBtn(evt){
    send("", "take_roll")
    evt.classList.add("disabled")
    setTimeout(()=>{
        evt.classList.remove("disabled")
    }, 5000)
}

// 发送或撤回群聊动作 type : group_chat/sendback
function send(message, type="group_chat"){
    let jsonobj = {
        user_id: "<?=$uid?>",
        class_id: "classid_13",
        type:  type,
        identity: "student",
    }
    if(type == "group_chat"){
        jsonobj.msg_id = Math.random().toString(16).slice(2)
        jsonobj.data = message
    }
    if(type == "sendback" || type == "deny_chat"){
        jsonobj.msg_id = message
    }

    let msg = JSON.stringify(jsonobj)
    ws.send(msg)
}

// 渲染聊天框
function drawChatList(data){
    let wrapDom =document.querySelector("#chatlist")
    let temp = `<button type="button" class="list-group-item list-group-item-action" data-id="${data.msg_id}" onClick="sendbackHandler(this)" >
        ${data.user_id} : ${data.data} - time: ${data.created_at}
    </button>`
    wrapDom.insertAdjacentHTML('beforeend',temp )
    wrapDom.scroll({ top: wrapDom.scrollHeight, behavior: "smooth"})
}


// 撤回聊天点击动作
function sendbackHandler(evt){
    let message_id = evt.getAttribute('data-id')
    send(message_id, "sendback" )
    console.log("撤回消息")
}

// 收到撤回消息渲染聊天框动作
function sendback(data){
    document.querySelector(`#chatlist [data-id="${data.msg_id}"]`).remove()
}

// 新登录渲染用户列表
function addNewMember(userId){
    let wrapDom = document.querySelector("#userlist")
    let temp = `<button type="button" class="list-group-item list-group-item-action" data-id="${userId}" >根据现有数据列表渲染userId- ${userId} </button>`
    wrapDom.insertAdjacentHTML('beforeend',temp )
    wrapDom.scroll({ top: wrapDom.scrollHeight, behavior: "smooth"})
}

// 删除用户列表已登出用户
function removeMember(userId){
    document.querySelector(`#userlist [data-id="${userId}"]`).remove()
}

// 收到点名 receive 为true 确认点名 false
function take_roll(data , receive=true){
    let userId = data.user_id
    if(receive == true){
        document.querySelectorAll('#userlist button').forEach((item)=>{
            document.querySelector("#checkRollBtn").classList.remove("visually-hidden")
            item.classList.add("text-muted")
        })
    }else{
        document.querySelector(`#userlist [data-id="${userId}"]`).classList.remove("text-muted")
        document.querySelector(`#userlist [data-id="${userId}"]`).classList.add("text-danger")
    }
}

// 确认点名按钮
function checkRollBtn(evt){
    evt.classList.add("visually-hidden")
    send("", "check_roll")
}

// 禁言按钮
function denyChatBtn(evt){
    let sendBtnDom = document.querySelector("#sendBtn")
    let deny_stats = sendBtnDom.getAttribute('data-id')
    
    if( deny_stats == '0' ){
        sendBtnDom.setAttribute('data-id', "1")
        evt.classList.remove('btn-danger')
        evt.classList.add('btn-info')
        evt.innerText = "解除"
    }else{
        sendBtnDom.setAttribute('data-id', "0")
        evt.classList.add('btn-danger')
        evt.classList.remove('btn-info')
        evt.innerText = "禁言"
    }
    send(deny_stats, "deny_chat") //发送禁言
}

// 禁言逻辑
function deny_chat(data){
    if(data.msg_id=='0'){
        document.querySelector("#sendBtn").setAttribute('data-id', "1")
        document.querySelector("#sendBtn").classList.add('disabled')
    }else{
        document.querySelector("#sendBtn").classList.remove('disabled')
        document.querySelector("#sendBtn").setAttribute('data-id', "0")
    }
}

</script>

</body>
</html>

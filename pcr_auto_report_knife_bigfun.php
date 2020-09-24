<?php
// 连接redis服务器
$redis = new Redis();
$redis->connect("127.0.0.1");
$redis->select(1);

// 检查cd，里面设定是300秒内重复访问则直接结束程序，避免频繁请求bigfun接口，嫖人接口要呵护（
if (!checkCD()) die();

// 设置bigfun请求头
$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"Host: www.bigfun.cn\r\n" .
              "Connection: keep-alive\r\n" .
              "Pragma: no-cache\r\n" .
              "Cache-Control: no-cache\r\n" .
              "Accept: application/json, text/plain, */*\r\n" .
              "Sec-Fetch-Site: same-origin\r\n" .
              "Sec-Fetch-Mode: cors\r\n" .
              "Sec-Fetch-Dest: empty\r\n" .
              "Referer: https://www.bigfun.cn/tools/pcrteam/d_report\r\n" .
              "Accept-Encoding: gzip, deflate, br\r\n" .
              "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8\r\n" .
              "Cookie: 填写你的曲奇\r\n" .
              "User-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36\r\n" .
              "x-csrf-token: 填写你的token"
  )
);

$context = stream_context_create($opts);

// 去bigfun接口拿数据
$content = file_get_contents('https://www.bigfun.cn/api/feweb?target=gzlj-clan-day-report%2Fa&size=30', false, $context);
$json = json_decode($content, true);

// 获取上次遍历到的最新一次的出刀时间
$lastTime = $redis->get("pcr_bigfun_lastKnifeTime");;

// 这里存放新增加的出刀
$arr = array();

// 今日已出刀数统计
$totalKnifeCount = 0;

// bigfun的成功码好像是0
if ($json['code'] == 0) {
  for ($i=0; $i < count($json['data']); $i++) {

    // 当前遍历玩家出的是第几刀
    $knifeCount = 0;

    // 遍历这个玩家今天的出刀记录
    for ($j=0; $j < count($json['data'][$i]['damage_list']); $j++) {
      // 尾刀或者剩余刀，则今日已出刀数+0.5，否则+1
      if ($json['data'][$i]['damage_list'][$j]['reimburse'] == 1 || $json['data'][$i]['damage_list'][$j]['kill'] == 1) {
        $totalKnifeCount += 0.5;
      } else {
        $totalKnifeCount += 1;
      }
      // 除了剩余刀外其它类型的刀，当前玩家刀数+1
      if ($json['data'][$i]['damage_list'][$j]['reimburse'] != 1) {
        $knifeCount++;
      }
      // 出刀时间在最后一次遍历的最新出刀记录时间之后，认为是新增加的出刀记录
      if ($json['data'][$i]['damage_list'][$j]['datetime'] > $lastTime) {
        // 记录一些数据，保存起来
        $d['num'] = $knifeCount; // 玩家今日刀数
        $d['time'] = $json['data'][$i]['damage_list'][$j]['datetime']; // 出刀时间
        $d['playername'] = $json['data'][$i]['name']; // 玩家名称
        $d['bossname'] = $json['data'][$i]['damage_list'][$j]['boss_name']; // boss名称
        $d['damage'] = $json['data'][$i]['damage_list'][$j]['damage']; // 伤害
        $d['kill'] = $json['data'][$i]['damage_list'][$j]['kill']; // 是否击破(尾刀)
        $d['lapnum'] = $json['data'][$i]['damage_list'][$j]['lap_num']; // 第几周目
        $d['reimburse'] = $json['data'][$i]['damage_list'][$j]['reimburse']; // 是否是剩余刀
        array_push($arr, $d);
      }
    }
  }
}



// 对新增加的出刀记录按时间进行升序排序，下面这段排序代码在网上抄的
$sort = array(
  'direction' => 'SORT_ASC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
  'field' => 'time', //排序字段
);
$arrSort = array();
foreach($arr AS $uniqid => $row){
    foreach($row AS $key=>$value){
        $arrSort[$key][$uniqid] = $value;
    }
}
if($sort['direction']){
    array_multisort($arrSort[$sort['field']], constant($sort['direction']), $arr);
}

//print_r($arr);

// 输出报刀文字
for ($i=0; $i<count($arr); $i++) {
    if ($arr[$i]['kill']==1) {
        $knifeName = "尾刀";
    } else if ($arr[$i]['reimburse']==1) {
        $knifeName = "剩余刀";
    } else {
        $knifeName = "正常刀";
    }

    // 故意换行的
    echo  $arr[$i]['playername']." 对 ". $arr[$i]['bossname'] ."(".$arr[$i]['lapnum']."周目) 造成 ".$arr[$i]['damage']. " 伤害". ($arr[$i]['kill']==1? '并击破':'') . "(第".$arr[$i]['num']."刀, ".$knifeName.")
";
}

// 如果有新增加的报刀记录
if (count($arr)>0) {

    // 查当前boss讨伐进度，和行会排名
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"Host: www.bigfun.cn\r\n" .
                  "Connection: keep-alive\r\n" .
                  "Pragma: no-cache\r\n" .
                  "Cache-Control: no-cache\r\n" .
                  "Accept: application/json, text/plain, */*\r\n" .
                  "Sec-Fetch-Site: same-origin\r\n" .
                  "Sec-Fetch-Mode: cors\r\n" .
                  "Sec-Fetch-Dest: empty\r\n" .
                  "Referer: https://www.bigfun.cn/tools/pcrteam/d_report\r\n" .
                  "Accept-Encoding: gzip, deflate, br\r\n" .
                  "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8\r\n" .
                  "Cookie: 填写你的曲奇\r\n" .
                  "User-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36\r\n"
      )
    );
    
    $context = stream_context_create($opts);

    $content = file_get_contents('https://www.bigfun.cn/api/feweb?target=gzlj-clan-day-report-collect%2Fa', false, $context);
    $T = json_decode($content, true);


    // 硬编码，其实bigfun也有接口获取，懒得搞了
    if ($T['code'] == 0) {
      if ($T['data']['boss_info']['name'] == "双足飞龙") {
        $bossNo = 1;
      } else if ($T['data']['boss_info']['name'] == "野性狮鹫") {
        $bossNo = 2;
      } else if ($T['data']['boss_info']['name'] == "支配者") {
        $bossNo = 3;
      } else if ($T['data']['boss_info']['name'] == "独眼巨人") {
        $bossNo = 4;
      } else {
        $bossNo = 5;
      }


    echo "------------
今日出刀数统计: ".$totalKnifeCount."/90
讨伐进度: ".$T['data']['boss_info']['name']."(".$T['data']['boss_info']['lap_num']."周目".$bossNo."王)
HP: ". $T['data']['boss_info']['current_life']."/". $T['data']['boss_info']['total_life']."
排名: ".$T['data']['clan_info']['last_ranking'];
    }

    // 保存遍历到的最新一条出刀记录的时间
    $redis->set("pcr_bigfun_lastKnifeTime", $arr[count($arr)-1]['time']);
}


// cd检查，避免频繁请求bigfun接口
function checkCD()
{
  global $redis;

  $time = $redis->get("pcr_bigfun_lastCheckCD"); 
  if (time() - $time > 300) {
    $redis->set("pcr_bigfun_lastCheckCD", time());
    return true;
  }
  return false;
}
?>
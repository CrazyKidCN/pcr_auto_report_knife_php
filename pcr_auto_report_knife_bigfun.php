<?php
/**
 * 公主连结国服自动报刀接口
 * 一个调用 bigfun 接口获取出刀数据并将其转换为报刀语句的 php 脚本
 * @author CrazyKid
 * @since 2020年9月26日11:40:13
 *
 * https://github.com/CrazyKidCN/pcr_auto_report_knife_php
 */

// 连接redis服务器
$redis = new Redis();
$redis->connect("127.0.0.1");
$redis->select(1);

// 检查cd，避免频繁请求bigfun接口，嫖人接口要呵护（
if (!checkCD()) die();

// 设置bigfun请求头
$opts = array(
    'http' => array(
        'method' => "GET",
        'header'=>"Host: api.bigfun.cn\r\n" .
            "Connection: keep-alive\r\n" .
            "Pragma: no-cache\r\n" .
            "User-Agent: BigFun/3.7.2 (cn.bigfun.firebird; build:3; iOS 14.2.1) Alamofire/4.9.1\r\n" .
            "Accept: application/vnd.api+json\r\n" .
            "BF-Client-Version: 3.7.2\r\n" .
            "Accept-Encoding: gzip;q=1.0, compress;q=0.5\r\n" .
            "Accept-Language: zh-Hans-MO;q=1.0, en-MO;q=0.9\r\n" .
            "BF-Client-Type: BF-IOS\r\n"
    )
);

$context = stream_context_create($opts);

// 去bigfun接口拿数据
$content = file_get_contents(getApiAddress(), false, $context);
$json = json_decode($content, true);

// 获取上次遍历到的最新一次的出刀时间
$lastTime = $redis->get("pcr_bigfun_lastKnifeTime");;

// 这里存放新增加的出刀
$arr = array();

// 今日已出刀数统计
$totalKnifeCount = 0;

// bigfun的成功码好像是0
if ($json['code'] == 0) {
    for ($i = 0; $i < count($json['data']); $i++) {

        // 当前遍历玩家出的是第几刀
        $knifeCount = 0;

        // 遍历这个玩家今天的出刀记录
        for ($j = 0; $j < count($json['data'][$i]['damage_list']); $j++) {
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
foreach ($arr as $uniqid => $row) {
    foreach ($row as $key => $value) {
        $arrSort[$key][$uniqid] = $value;
    }
}
if ($sort['direction']) {
    array_multisort($arrSort[$sort['field']], constant($sort['direction']), $arr);
}

//print_r($arr);

// 输出报刀文字
for ($i = 0; $i < count($arr); $i++) {
    if ($arr[$i]['kill'] == 1) {
        $knifeName = "尾刀";
    } else if ($arr[$i]['reimburse'] == 1) {
        $knifeName = "剩余刀";
    } else {
        $knifeName = "完整刀";
    }

    // 故意换行的
    echo $arr[$i]['playername'] . " 对 " . $arr[$i]['bossname'] . "(" . $arr[$i]['lapnum'] . "周目) 造成 " . $arr[$i]['damage'] . " 伤害" . ($arr[$i]['kill'] == 1 ? '并击破' : '') . "(第" . $arr[$i]['num'] . "刀, " . $knifeName . ")
";
}

if (count($arr) > 0) {
    echo "------------
今日出刀数统计: " . $totalKnifeCount . "/90";
    // 保存遍历到的最新一条出刀记录的时间
    $redis->set("pcr_bigfun_lastKnifeTime", $arr[count($arr) - 1]['time']);
}


// 查当前boss讨伐进度，和行会排名
// 请自行在手机客户端打开【公会日报】任意页，使用抓包工具记录访问地址，写在下方，关键词“gzlj-clan-day-report-collect”，下面的url仅为示例
$content = file_get_contents('https://api.bigfun.cn/webview/iphone?target=gzlj-clan-day-report-collect/a&device_number=xxxxx&ts=xxxxx&access_token=xxxxx&sign=xxxxx', false, $context);
$T = json_decode($content, true);


if ($T['code'] == 0) {
    // 根据 boss 名称获取 boss 是第几个王
    $bossNo = getBossNumByName($T['data']['boss_info']['name']);

    $lastBossHP = $redis->get("pcr_bigfun_lastBossHP");
    // boss血量相比上次有变化才输出当前进度，因为 bigfun 的 boss 状态更新好像跟出刀记录不同步
    if ($lastBossHP != $T['data']['boss_info']['current_life']) {
        // 如果有新增的出刀数据，则在报刀语句后面附加当前的讨伐进度
        if (count($arr) > 0) {
            echo "
讨伐进度: " . $T['data']['boss_info']['name'] . "(" . $T['data']['boss_info']['lap_num'] . "周目" . $bossNo . ")
HP: " . $T['data']['boss_info']['current_life'] . "/" . $T['data']['boss_info']['total_life'] . "
排名: " . $T['data']['clan_info']['last_ranking'];
        } else {
            // 如果没有出刀数据，那就通知 boss 血量发生了变更
            echo "BOSS状态有更新
------------
讨伐进度: " . $T['data']['boss_info']['name'] . "(" . $T['data']['boss_info']['lap_num'] . "周目" . $bossNo . ")
HP: " . $T['data']['boss_info']['current_life'] . "/" . $T['data']['boss_info']['total_life'] . "
排名: " . $T['data']['clan_info']['last_ranking'];

        }
        // 保存当前boss血量
        $redis->set("pcr_bigfun_lastBossHP", $T['data']['boss_info']['current_life']);
    } else {
        if (count($arr) > 0) {
            echo "
(BOSS状态对比上次报刀没有变更,以下结果可能有延迟)
讨伐进度: " . $T['data']['boss_info']['name'] . "(" . $T['data']['boss_info']['lap_num'] . "周目" . $bossNo . ")
HP: " . $T['data']['boss_info']['current_life'] . "/" . $T['data']['boss_info']['total_life'] . "
排名: " . $T['data']['clan_info']['last_ranking'];
        }
    }
}

/**
 * cd检查，避免频繁请求bigfun接口
 * @return bool true/还在CD中 false/CD已结束
 */
function checkCD()
{
    global $redis;

    $time = $redis->get("pcr_bigfun_lastCheckCD");
    if (time() - $time > 160) {
        $redis->set("pcr_bigfun_lastCheckCD", time());
        return true;
    }
    return false;
}

/**
 * 根据boss名称获取是第几个王
 * @param $bossName boss名称
 * @return string 空字符串代表获取失败，否则返回 “X王"
 */
function getBossNumByName($bossName)
{
    global $context;

    // 请自行在手机客户端打开【boss报表】，使用抓包工具记录访问地址，写在下方，关键词“gzlj-clan-boss-report-collect”，下面的url仅为示例
    $content = file_get_contents('https://api.bigfun.cn/webview/iphone?target=gzlj-clan-boss-report-collect/a&device_number=xxxxx&ts=xxxxx&access_token=xxxxx&sign=xxxxx', false, $context);
    $T = json_decode($content, true);

    $bossJson = array();

    if ($T['code'] == 0) {
        $bossJson = $T['data']['boss_list'];
    }

    if (count($bossJson) > 0) {
        for ($i = 0; $i < count($bossJson); $i++) {
            if ($bossJson[$i]['boss_name'] == $bossName) {
                return $i + 1 . "王";
            }
        }
    }
    return "";

    /**
     * 获取当日的出刀记录API查询地址
     * @return string 出刀记录API地址
     */
    function getApiAddress() {
        $day = date('d');
        $hour = date('H');

        // 游戏是凌晨5点跨日，因此取前一天
        if ($hour < 5) {
            $day = date("d", strtotime("-1 day"));
        }

        // 请自行在手机客户端浏览【公会日报】【每一天】的出刀记录【按成员】，使用抓包工具记录接口访问地址，写在下方，关键词“gzlj-clan-day-report”
        // 关于sign的生成方式我不懂也懒得弄懂，就这样随随便便写一下吧，能用就行，希望 bigfun 高抬贵手
        // 请勿在手机客户端中登出账号，否则接口也将失效。

        if ($day == 14) {
            // 仅为示例url，请替换为实际抓到的url，下同
            return "https://api.bigfun.cn/webview/iphone?size=30&date=2020-12-14&target=gzlj-clan-day-report/a&device_number=随机字符&ts=随机字符&access_token=随机字符&sign=随机字符";
        } else if ($day == 15) {
            return "";
        } else if ($day == 16) {
            return "";
        } else if ($day == 17) {
            return "";
        } else if ($day == 18) {
            return "";
        } else if ($day == 19) {
            return "";
        } else {
            die("");
        }
    }
}


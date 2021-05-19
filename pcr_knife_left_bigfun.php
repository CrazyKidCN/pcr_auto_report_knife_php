<?php
/**
 * 公主连结国服查剩余刀接口
 * 一个调用 bigfun 接口获取出刀数据并将其转换为成员剩余刀语句的 php 脚本
 * @author CrazyKid
 * @since 2020年12月15日20:19:49
 *
 * https://github.com/CrazyKidCN/pcr_auto_report_knife_php
 */

// 设置bigfun请求头
$opts = array(
    'http' => array(
        'method' => "GET",
        'header' => "Host: api.bigfun.cn\r\n" .
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
                $knifeCount += 0.5;
            } else {
                $totalKnifeCount += 1;
                $knifeCount += 1;
            }
        }

        // 记录一些数据，保存起来
        $d['knifeLeft'] = 3 - $knifeCount; // 玩家今日刀数
        $d['playername'] = $json['data'][$i]['name']; // 玩家名称
        if ($d['knifeLeft'] > 0) {
            array_push($arr, $d);
        }
    }
}


// 对剩余刀数进行降序排序，下面这段排序代码在网上抄的
$sort = array(
    'direction' => 'SORT_DESC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
    'field' => 'knifeLeft', //排序字段
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
$str = "今日余刀:
";
for ($i = 0; $i < count($arr); $i++) {
    // 故意换行的
    $playerName = $arr[$i]['playername'];
    $str .= "剩" . $arr[$i]['knifeLeft'] . "刀, " . $playerName . "
";
}

if (count($arr) > 0) {
    $str .= "=========
今日出刀数统计: " . $totalKnifeCount . "/90
数据来源于bigfun，可能有延迟。";
}


echo $str;


// 如果遇到消息发不出去 (通常是公会成员名称可能触发了敏感词导致)，可以转换成图片后发送，注释掉上面的echo，解除注释下面这段代码。
// 仅适用于Windows

/*
$size = 12;//字体大小
$font = "c:/windows/fonts/SIMHEI.TTF";//字体类型，这里为黑体，具体请在windows/fonts文件夹中，找相应的font文件
$img = imagecreate(240, count($arr) * 24 + 40);//创建一个长为500高为16的空白图片
imagecolorallocate($img, 0xff, 0xff, 0xff);//设置图片背景颜色，这里背景颜色为#ffffff，也就是白色
$black = imagecolorallocate($img, 0x00, 0x00, 0x00);//设置字体颜色，这里为#000000，也就是黑色
imagettftext($img, $size, 0, 0, 16, $black, $font, $str);//将ttf文字写到图片中
header('Content-Type: image/png');//发送头信息
imagepng($img);//输出图片，输出png使用imagepng方法，输出gif使用imagegif方法
*/


/**
 * 获取当日的出刀记录API查询地址
 * @return string 出刀记录API地址
 */
function getApiAddress()
{
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

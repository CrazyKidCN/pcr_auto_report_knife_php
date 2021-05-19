# pcr_auto_report_knife_php
公主连结国服自动报刀接口

## 项目地址
https://github.com/CrazyKidCN/pcr_auto_report_knife_php

## 简介
一个调用 `bigfun` 接口获取出刀数据并将其转换为报刀语句的php

## 使用方法
- ~~打开 bigfun 查刀页面 (https://www.bigfun.cn/tools/pcrteam/d_report) ，登录游戏账号，按F12查询请求接口的 `cookie` 和 `x-csrf-token` ，并填写在源码相应位置~~
- 射手座公会战起实时的出刀数据只能在 APP 里查看，因此需要在手机端使用抓包工具获得接口地址，填写在源码对应位置，详见源码内注释。(iOS 我使用的是`surge`)
- 将 .php 扔到服务器里，注意服务器需安装 redis 以及 php 要安装 redis 扩展。
- 用你的 QQ 机器人后端轮询该接口，判断返回内容不为空的时候直接往QQ群发信息。轮询频率随意，源码内已经限制请求 bigfun 接口的频率


## 效果图
![image](https://github.com/CrazyKidCN/pcr_auto_report_knife_php/blob/master/preview.png)

## 结语
写的比较简陋，后面我有空了(有生之年系列)再慢慢完善，如果有人能发起 pull request 一起完善就更好了，欢迎随意转载引用，请注明出处，如果帮助到你了请点个 Star 或 Watch 支持一下，感谢！


## 更新日志
##### 2020/12/15
- 新增查刀接口，输出公会成员剩余刀数。一样是填接口地址到对应源码里，机器人去请求然后发到群里即可。
- 由于本人不看好这种接口调用方式还能长久使用，部分重复代码的地方就懒得抽公用了。
- 效果图为转换成图片后发送，为了规避公会成员名称导致的腾讯的敏感词过滤，详见源码。
- ![image](https://github.com/CrazyKidCN/pcr_auto_report_knife_php/blob/master/preview_knifeLeft.jpg)

##### 2020/12/14
- 修改请求 header 为 bigfun 手机客户端内 header
- 修改 api 路径相关代码
- bigfun 已限制出刀数据只能在 APP 里做到实时查看，网页版的改为了每天0点更新，因此如要继续使用本php，需自行到 bigfun 手机客户端内抓取当期会战每一天的出刀记录的接口地址，填写在源码里对应位置，详见注释。

##### 2020/9/26
- 格式化了代码
- 把 `cookie` 和 `x-csrf-token` 配置项提取出来为常量
- 由于 bigfun 那边 boss 状态和出刀记录可能并不是同步更新，现在报刀时将顺便判断boss状态(血量)是否变更
- 现在 boss 是第几个王将通过 bigfun 接口获取, 不再是硬编码
- 略微降低请求 bigfun 接口的冷却时间 `300秒 -> 160秒` (因为增加了boss血量状态的判断)

##### 2020/9/24
- 初次发布
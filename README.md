# pcr_auto_report_knife_php
公主连结国服自动报刀接口

## 简介
一个调用 `bigfun` 接口获取出刀数据并将其转换为报刀语句的php

## 使用方法
- 打开 bigfun 查刀页面 (https://www.bigfun.cn/tools/pcrteam/d_report) ，登录游戏账号，按F12查询请求接口的 `cookie` 和 `x-csrf-token` ，并填写在源码相应位置 (注意 cookie 有两处要写)
- 将 .php 扔到服务器里，注意服务器需安装 redis 以及 php 要安装 redis 扩展。
- 用你的 QQ 机器人后端轮询该接口，判断返回内容不为空的时候直接往QQ群发信息。


## 效果图
![image](https://github.com/CrazyKidCN/pcr_auto_report_knife_php/blob/master/preview.png)

## 结语
写的比较简陋，还有些硬编码的地方，后面我有空了再慢慢完善，如果有人能发起 pull request 一起完善就更好了，欢迎随意转载引用，请注明出处，如果帮助到你了请点个 Star 支持一下，感谢！

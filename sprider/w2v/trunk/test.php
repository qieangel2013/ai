<?php
function distance_pre($keyword) {
	exec ( dirname ( __FILE__ ) . "/distancecli " . dirname ( __FILE__ ) . "/vectors.bin " . $keyword, $outputArray );
	if (isset ( $outputArray[0] )) {
		return $outputArray;
	} else {
		return array();
	}
}
function distance($keyword) {
	exec ( dirname ( __FILE__ ) . "/distancecli " . dirname ( __FILE__ ) . "/vectors.bin " . $keyword, $outputArray );
	$resultdata=array();
	if (isset ( $outputArray[0] )) {
		$tmparr=explode(",",$outputArray[0]);
		$tmpssr=distance_pre($tmparr[0]);
		foreach ($tmpssr as $k => $v) {
			$tmpdata=explode(",",$v);
			if($tmpdata[0]==$keyword){
				$resultdata=$tmpdata;
				break;
			}
		}
		return $resultdata;
	} else {
		return array();
	}
}

$TxtFileName = "sample.txt";
$arr=array(
	'6' =>'主流币 行情分析 比特 山寨币 比特币 数字货币行情分析 比特币行情分析 交易币 币 火币 炒币 虚拟币 币圈行情分析 ETH后市表现 盘面 破位减仓 抄底 EOS行情 看空 长线 长线操作 短线 短线操作 涨了 跌了 利好 利空 做空 散户 散户 BCH ETH 走势 跌破 ETH走势 EOS走势 币圈投资 比特币行情分析 玩客币行情 开普币今日行情 比特币今日行情分析 比特币今日价格行情 狗狗币行情 比特币最新行情 瑞波币行情 瑞波币技术分析 比特币近期价格分析 数字币行情分析 牛市 熊市 行情回顾 BTC 区间震荡 数字币交易 市场情绪 BCH放量 涨幅 回调 压力区 反弹走势 日线 止盈位 止损位 横盘阶段 大盘走势 建仓 ZIL K线 震荡 震荡反弹 日K线 行情预测 大阳线 数字货币投资 艾达币 以太坊 ADA 行情研判 瑞波币报价 阴线 莱特币 LTC PNT 区块链投资',
	'9'=>'专访 采访 表示 创始人 CEO 对话 坦言 代表人物 他 认为 创立 战略领袖 灵魂人物 掌舵者 我们 见解 联合创始人 介绍 拜访 精通 经验 坦言 “” ： ？我 阐述 心得',
	'10'=>'产品 发布 团队 平台 去中心化平台 区块链 圈 区块 链圈 区块圈 区链 块链 blockchain 分布式平台 区块链平台 公有链 公链 加密代币 发行 模型 区块链技术 区块链项目 加密货币 项目测评 项目解析 项目剖析 优质项目 白皮书 生态布局 项目介绍 代币模型 预售 区块链应用 不可篡改 区块链技术应用 溯源 基于区块链技术 使用区块链技术 布局区块链 入局区块链 基于区块链技术 快捷 安全 透明 进军区块链 钱包 数字货币 区块链初创公司 区块链领域 以太坊 数字货币资产 数字资产 区块链小程序 智能合约 区块链手游 区块链游戏 区块链金融 数字货币交易所 数字货币产业链 共识机制 新项目 首个 首次 推出 落地 宣布 应用落地 生态系统 可扩展性 社区平台 社区 社群 社区建设 加密社区 运作模式 底层平台 支付系统 数据经济 解决方案 物联网区块链接项目 AI区块链项目 功能 工具 场景 应用场景 规划 核心团队 Token 发行 核心价值 交易所 虚拟平台 区块链+ 链接 激励 去中心化资产交易 数字身份 ICO 区块链项目评级',
	'11'=>'矿机制造商 矿机生产商 嘉楠耘智 加密货币 矿机 阿瓦隆 Avalon 台 比特大陆 ASIC矿机 ASIC芯片 挖矿业 挖矿 挖矿行业 采矿 矿场 电力 回报率 GPU矿场 区块奖励 采矿设备 电费 挖矿芯片 区块链手机 区块链挖矿机 云挖矿 挖矿硬件 加密货币挖矿 挖矿木马 黄金矿区 数字货币产业链'
	);
foreach ($arr as $k => $v) {
	$tmparr=explode(' ',$v);
	foreach ($tmparr as $kk => $vv) {
		$tmpdata=distance($vv);
		if(!empty($tmpdata)){
			file_put_contents($TxtFileName,$tmpdata[1]*1000000 .":1:".$k.PHP_EOL,FILE_APPEND);
		}else{
			file_put_contents($TxtFileName,'1000000:1:6'.PHP_EOL,FILE_APPEND);
		}
	}
}



//print_r(distance("中国"));

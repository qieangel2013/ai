### 核心特性
	1.基于swoole实现爬取数据
	2.基于dom实现清洗数据
	3.基于word2vec获取词向量
	4.基于phpml和样本数据实现推荐
### 服务启动
	需要php以cli模式运行/server/server.php
        php server.php start
        php server.php stop
        php server.php restart
### 使用方式
	1、语料
	首先准备数据：采用网上博客上推荐的全网新闻数据(SogouCA)，大小为2.1G。 

	从ftp上下载数据包SogouCA.tar.gz：
	 wget ftp://ftp.labs.sogou.com/Data/SogouCA/SogouCA.tar.gz --ftp-user=hebin_hit@foxmail.com --ftp-password=4FqLSYdNcrDXvNDi -r
	解压数据包：

	 gzip -d SogouCA.tar.gz
	 tar -xvf SogouCA.tar
	再将生成的txt文件归并到corpus.txt中，大小为2.7G。

	 cat *.txt > corpus.txt
	2、分词

	安装gensim前要装python,numpy, scipy, 通过pip list检查
	开始安装gensim
	    sudo pip install gensim
	参考文档：http://www.jianshu.com/p/6d542ff65b1e
	    http://kexue.fm/archives/4316/
	文档http://www.jianshu.com/p/6d542ff65b1e上的两个python程序有错误， 我已经改正，内容见python文件
	对文件编码格式处理
	cat news_tensite_xml.dat | iconv -f gbk -t utf-8 -c | grep "<content>"  > corpus.txt
	分词
	python word_segment.py corpus.txt corpus_seg.txt
	3、用word2vec工具训练词向量
	 nohup ./word2vec -train corpus_seg.txt -output vectors.bin -cbow 0 -size 200 -window 5 -negative 0 -hs 1 -sample 1e-3 -threads 12 -binary 1 &
	vectors.bin是word2vec处理corpus_seg.txt后生成的词的向量文件，在实验室的服务器上训练了1个半小时。

	4、分析
	  计算相似的词：
	 ./distance vectors.bin
	 ./distance可以看成计算词与词之间的距离，把词看成向量空间上的一个点，distance看成向量空间上点与点的距离。
	 执行以下方法
	 ./distancecli vectors.bin 区块 
### License

Apache License Version 2.0 see http://www.apache.org/licenses/LICENSE-2.0.html
### 如果你对我的辛勤劳动给予肯定，请给我捐赠，你的捐赠是我最大的动力
![](https://github.com/qieangel2013/zys/blob/master/public/images/pw.jpg)
![](https://github.com/qieangel2013/zys/blob/master/public/images/pay.png)
[项目捐赠列表](https://github.com/qieangel2013/zys/wiki/%E9%A1%B9%E7%9B%AE%E6%8D%90%E8%B5%A0)

#这里是将代码打包到docker，首先先构建swoole的环境
FROM registry.cn-hangzhou.aliyuncs.com/tmtbe/sd-swoole:latest
MAINTAINER Jincheng Zhang 896369042@qq.com
#注意这里会执行composer install，和下载consul，如果不需要的话直接COPY vendor进去
#复制SD的一些文件
COPY . /home/SwooleDistributed
#覆盖supervisord和redis的配置
RUN cp -rf /home/SwooleDistributed/conf.d/supervisord.conf /etc/supervisor/conf.d/sd.conf \
    && cp -rf /home/SwooleDistributed/conf.d/redis.conf /etc/redis/redis.conf
#如果你确定你的依赖都ok就不需要运行这些了
RUN cd /home/SwooleDistributed \
	&& composer install \
	&& cd /home/SwooleDistributed/bin/exec \
	&& wget https://releases.hashicorp.com/consul/0.7.5/consul_0.7.5_linux_amd64.zip --no-check-certificate\
	&& unzip consul_0.7.5_linux_amd64.zip \
	&& rm consul_0.7.5_linux_amd64.zip
#声明端口，和你开启的HTTP，TCP端口保持一致
EXPOSE 8081
EXPOSE 9093
#启动服务
CMD ["supervisord"]



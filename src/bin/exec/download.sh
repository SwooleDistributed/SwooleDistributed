#!/usr/bin/env bash
rm -rf consul_0.9.3_linux_amd64.zip \
&& wget -P `pwd`/bin/exec https://releases.hashicorp.com/consul/0.9.3/consul_0.9.3_linux_amd64.zip \
&& unzip `pwd`/bin/exec/consul_0.9.3_linux_amd64.zip -d `pwd`/bin/exec/
&& wget -p `pwd`/bin/exec https://github.com/fabiolb/fabio/releases/download/v1.5.6/fabio-1.5.6-go1.9.2-linux_amd64 \
&& mv fabio-1.5.6-go1.9.2-linux_amd64 fabio
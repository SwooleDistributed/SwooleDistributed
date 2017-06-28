#!/usr/bin/env bash
rm -rf consul_0.8.5_linux_amd64.zip \
&& wget -P `pwd`/bin/exec http://releases.hashicorp.com/consul/0.8.5/consul_0.8.5_linux_amd64.zip \
&& unzip `pwd`/bin/exec/consul_0.8.5_linux_amd64.zip -d `pwd`/bin/exec/
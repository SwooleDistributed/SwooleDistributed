#!/bin/sh
#sd安装脚本
set -e
command_exists() {
	command -v "$@" > /dev/null 2>&1
}

php_version="7.1.14"
swoole_version="1.10.2"
sd_version="2.8.6"
swoole_configure="--enable-async-redis  --enable-openssl"

if command_exists yum ; then
    curl -o install_os.sh sd.youwoxing.net/install_centos.sh
fi
if command_exists apt ; then
    curl -o install_os.sh sd.youwoxing.net/install_ubuntu.sh
fi

source install_os.sh
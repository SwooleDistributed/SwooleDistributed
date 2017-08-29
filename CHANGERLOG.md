# CHANGELOG
##2.4.6
1.修改了绝大多数由于集群重构导致的API报错问题
2.移除了集群SESSION（设计存在问题，有待优化，暂时移除）
3.增加了集群订阅/发布功能
##2.4.7
1.修改了IPack，IRoute的errorHandle($e, $fd)接口，将异常传入进去
2.支持MQTT规则的订阅树
3.现在访问到被保护或者私有的控制器方法不会报错，会直接转到defaultMethod中去
4.修复了NonJsonPack的一个缓存bug
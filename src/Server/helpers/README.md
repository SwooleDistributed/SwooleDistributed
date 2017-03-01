此目录为框架的help目录,提供了一部分框架使用的帮助函数

请不要在此目录添加任何代码，修改任何代码，如需添加自定义帮助函数请在app目录下操作，并将其添加到composer.json中，运行composer install.

```
 "autoload": {
    "psr-4": {
      "Server\\": "src/Server",
      "app\\": "src/app",
      "test\\": "src/test"
    },
    "files": [
      //在此处添加目录
      "src/Server/helpers/Common.php"
    ]
  },
```

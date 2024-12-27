# 说明
kuga-server-v3 提供API服务

功能点：
- 提供了一个api服务的框架
- 提供网关服务功能，支持api访问日志保存(Redis），根据实际情况可以扩展更多内容
- 默认提供了acc-api服务，接口见config/api/acc部分，主要包括应用管理、用户管理、角色管理、菜单管理以及配置管理，api文档见 https://apidocs.kity.me

# 目录结构说明
```
├─.
├─class
|   ├─KugaApp
|      ├─Controllers
|      └─Application.php
|   ├─composer.json              // 核心类文件的引用请用composer install 来安装
|─config
|   ├─aliyunoss                  // 阿里云oss的权限策略文件配置
|   ├─api                        // API接口约定文件，所有API接口必须编写相应的json文件，通过json文件来验证request请求，同时借助 https://github.com/misnet/apidocs  可以生成apidoc文件。
|   ├─email-tpls                 // 邮件验证码模板
|   ├─local.yaml                 // 本地化配置文件，将会覆盖config.yaml中的配置
|   └─config.yaml                // 默认配置文件
|      
├─langs
|   ├─en_US                      // 英文语言包，可用poedit来读取po文件，生成翻译mo文件
|   ├─zh_CN                      // 中文语言包，可用poedit来读取po文件，生成翻译mo文件
|   └─_common                    // 对应class/vendor/Kuga 文件内容，可用poedit来扫描与生成
├─public                         // documentRoot目录，nginx/apache请解析到这里
├─var/tmp                        // 系统生成的临时文件都在这里
├─var/tmp/meta                   // 数据结构的缓存文件，数据结构变化时，需要删除这里的文件
├─var/tmp/logs                   // 日志文件
├─var/tmp/cache                  // 缓存文件
```


# 安装

运行环境要求：
- 需要PHP7.4以上 + MySQL 支持
- 需要安装的PHP扩展有：Phalcon5.6、Exif、GD、GetText、Yaml、Redis

1、目录设权限，下载项目相关类文件
```
mkdir var/tmp
chmod +777 var -R
cd class
php composer.phar install -vvv
```

2、复制config.yaml，另存一份local.yaml，然后配好与config.yaml不同的内容，主要需要配置内容包括：
-- 数据库: 在dbread和dbwrite
-- API：在api部分，支持多个，需要appkey和appsecret，这个需要和应用管理的数据一致，如果客户端使用v3/gateway访问，
3、配置好nginx，例：
```
server {
    listen       80;
    server_name  acc-api.kuga.wang;
    access_log /dev/null common;
    error_log /dev/null;
    set $root_path '/opt/kuga-server-v3/public';
    root $root_path;
    try_files $uri $uri/ @rewrite;

    location @rewrite {
        rewrite ^/(.*)$ /index.php?_url=/$1;
        #try_files $uri $uri/ /index.php;
    }
    location ~ .*\.(php|phtml)?$ {
        fastcgi_split_path_info       ^(.+\.php)(/.+)$;
        fastcgi_param PATH_INFO       $fastcgi_path_info;
        fastcgi_param PATH_TRANSLATED $document_root$fastcgi_path_info;
        fastcgi_pass   127.0.0.1:9000;
        if ($request_filename ~* (.*)\.php) {
            set $php_url $1;
        }
        if (!-e $php_url.php) {
            return 403;
        }
    }
    location ~* \.(eot|ttf|svg|woff)$ {
         add_header Access-Control-Allow-Origin *;
    }
    location ~ /\.git {
        deny all;
    }
}
```
# 访问：
- v3方式：http://acc-api.kuga.wang/v3/gateway/api接口名称
需要客户端在请求头中加入Appkey和Sign（签名），Access_Token，支持Locale、Version等参数

各参数说明以及v3的签名算法参考资料：https://github.com/misnet/kuga-openapi-core

- v4方式：http://acc-api.kuga.wang/v4/gateway/api接口名称
需要客户端在请求头中加入Appkey和Access-Token-Type=JWT，Authorization（JWT认证），支持Locale、Version等参数。例子见 https://acc.kity.me

- v3和v4的区别：v4使用JWT认证，v3使用KUGA认证，v4的需要在header中传Authorization参数让服务端来识别用户身份，v3的签名算法是KUGA的签名算法，需要客户端自己生成签名串，使用到的秘钥就是应用管理中应用的秘钥

# 编写自己的API接口

1、如果不是通过composer安装的，可以修改一下env.php文件
```
//你的类文件目录，遵循psr4规范，例如
$classPath = realpath(__DIR__.'/class/src');
define('QING_CLASS_PATH',$classPath);

$loader->registerNamespaces(array(
    'Example\\Model'=>QING_CLASS_PATH.'/Model',
    'Example\\Service'=>QING_CLASS_PATH.'/Service'
));
$loader->register();
```

2、编写API请参照 https://github.com/misnet/acc-api 下的写法

3、编写完API后，必须在config/api目录里定义好自己的API json文件，文件约定了API的服务端处理接口、以及入参类型、要求等，具体可参见config/api/acc。
如果没有定义API json文件会导致编写的api无法访问。有关api接口json文件的编写规范见[https://github.com/misnet/apidocs]

4、建议编写自己的api，发布到github或者自己的私有仓库，然后通过composer来安装，这样可以方便管理和更新

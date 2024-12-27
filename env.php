<?php
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Storage\SerializerFactory;
/**
 * 运行环境相关设置
 * @author Donny
 */
include_once 'class/vendor/autoload.php';
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods:POST, GET, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type,Access-Token-Type,Authorization,Version,Locale,Appkey,Sign,Access_Token,Format,x-sign,x-message-id,x-topic-key");
header('Access-Control-Allow-Credentials:true');

define('DS',DIRECTORY_SEPARATOR);
define('QING_ROOT_PATH', realpath(__DIR__));
define('QING_PUBLIC_PATH',QING_ROOT_PATH.DS.'public');
define('QING_TMP_PATH',QING_ROOT_PATH.DS.'var'.DS.'tmp');

$di = new Phalcon\Di\FactoryDefault();
\Kuga\Init::setTmpDir(QING_TMP_PATH);
\Kuga\Init::setup($di);

$loader = new Phalcon\Autoload\Loader();
$loader->addNamespace('KugaApp',QING_ROOT_PATH.DS.'class/KugaApp')->register();
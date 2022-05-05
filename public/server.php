<?php
    //禁用错误报告
    //ini_set("display_errors", "On");
    //error_reporting(0);
    //error_reporting(E_ALL ^ E_NOTICE);
    ########################
    define('APPLICATION_PATH', dirname(__FILE__) . "/..");
    $config = new Yaf_Config_Ini(APPLICATION_PATH . '/conf/application.ini', ini_get('yaf.environ'));
    //版本控制
    define("IS_VERSION_ON",$config["is_version_on"]);
    define("ZK_VERSION",$config["zk_version"]);
    //加载自定义路由
    $routeConfig = include_once APPLICATION_PATH.'/routes/'.strtolower($config["application.modules"]).'.php';
    Yaf_Registry::set('route_config', $routeConfig);
    //加载composer
    include_once APPLICATION_PATH.'/vendor/autoload.php';

    $application  = new Yaf_Application(APPLICATION_PATH . "/conf/application.ini");
    //数据库

    //进程池
    $pool = new Swoole\Process\Pool($config["swoole.worker_num"], SWOOLE_IPC_NONE, 0, true);

    //进程启用前操作
    $pool->on("workerStart",function ($pool,$id) use ($config,$application){
        $http = new Swoole\Coroutine\Http\Server($config["swoole.host"], $config["swoole.port"], $config["use_ssl"]?$config["use_ssl"]:false,true);
        $http->set([
            'daemonize' => $config["swoole.daemonize"]
        ]);
        if ($config["use_ssl"]){
            $http->set([
                'ssl_cert_file' =>   $config["sslCertFile"],
                'ssl_key_file' =>   $config["sslKeyFile"],
            ]);
        }
        $http->handle("/",function ($request,$response) use ($http,$application){
            $request_uri = str_replace("/index.php", "", $request->server['request_uri']);

            $yaf_request = new Yaf_Request_Http($request_uri);
            $application->getDispatcher()->setRequest($yaf_request);

            //使用context接受参数，防止协程切换污染变量
            \Swoole\Coroutine::getContext()["swoole_req"] = $request;
            \Swoole\Coroutine::getContext()["swoole_res"] = $response;

            // yaf 会自动输出脚本内容，因此这里使用缓存区接受交给swoole response 对象返回
            ob_start();
            $application->getDispatcher()->disableView();
            $application->bootstrap()->run();
            $data = ob_get_clean();
            $response->end($data);
        });

        $http->start();
    });

    $pool->start();





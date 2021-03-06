<?php
/**
 * PHP 5.0 以上
 *
 * @package         Wavephp
 * @author          许萍
 * @copyright       Copyright (c) 2016
 * @link            https://github.com/xpmozong/wavephp2
 * @since           Version 2.0
 *
 */

/**
 * Wavephp Application Route Class
 *
 * route类
 *
 * @package         Wavephp
 * @subpackage      core
 * @author          许萍
 *
 */
class Route
{
    private $isDebuger          = false;    // 是否开启日志输出
    private $isSmarty           = false;    // 是否用Smarty模板
    private $defaultControl     = '';       // 默认控制层

    public $version             = '';
    public $className           = '';
    public $actionName          = '';

    /**
     * 初始化
     */
    function __construct()
    {
        $app = Wave::app();
        if (isset($app->config['smarty'])) {
            $this->isSmarty = $app->config['smarty']['is_on'];
        }
        if (isset($app->config['debuger'])) {
            $this->isDebuger = $app->config['debuger'];
        }
        $this->pathInfo         = $app->request->pathInfo;
        $this->defaultControl   = $app->defaultControl;
    }

    /**
     * 过滤危险字符
     *
     * @return String
     *
     */
    private function filterStr($str)
    {
        $preg = '/(\~)|(\!)|(\@)|(\#)|(\$)|(\%)
                |(\^)|(\*)|(\()|(\))|(\-)
                |(\+)|(\[)|(\])|(\')|(\")|(\<)
                |(\>)|(\?)|(\.)|(\&)|(\|)/';

        return preg_replace($preg, '', $str);
    }

    /**
     * route 处理
     *
     * 例如 index.php/site/index
     * 会使用SiteController.php这个文件，调用actionIndex这个方法
     * 例如 index.php/site/index/a/b
     * 会使用SiteController.php这个文件，调用actionIndex($a, $b)这个方法
     *
     * 默认使用SiteController.php这个文件，调用actionIndex这个方法
     *
     */
    public function route()
    {
        $callarray = array();
        $rpathInfo = $this->pathInfo;
        $c = $this->defaultControl;
        $f = 'actionIndex';
        if (!empty($rpathInfo) && $rpathInfo !== '/') {
            $pos = strpos($rpathInfo, '?');
            if ($pos !== false) {
                $rpathInfo = substr($rpathInfo, 0, $pos);
            }
            $rpathInfo = trim($rpathInfo, '/');
            if (!empty($rpathInfo)) {
                $rpathInfo = $this->filterStr($rpathInfo);
                $pathInfoArr = explode('/', $rpathInfo);
                $index = 0;
                $c = $pathInfoArr[$index];
                if (!empty($pathInfoArr[$index + 1])) {
                    $f = 'action'.ucfirst($pathInfoArr[$index + 1]);
                }
                if (count($pathInfoArr) > ($index + 2)) {
                    for ($i = 0; $i < ($index + 2); $i++) { 
                        array_shift($pathInfoArr);
                    }
                    $callarray = $pathInfoArr;
                }
            }
        }
        $c = ucfirst($this->version).ucfirst($c).'Controller';

        $this->className = $c;
        $this->actionName = $f;
        if (class_exists($c)) {
            try {
                $cc = new $c;
                if (method_exists($cc, $f)) {
                    if (!empty($callarray)) {
                        // call_user_func_array(array($cc, $f), $callarray);
                        $cc->$f(...$callarray);
                    } else {
                        $cc->$f();
                    }
                    $cc->debuger();
                    if ($this->isSmarty) {
                        if (Wave::$mode !== 'CLI') {
                            $cc->display();
                        }
                    }
                } else {
                   $this->error404();
                }
            } catch (Exception $e) {
                WaveCommon::exportResult((int)$e->getCode(), $e->getMessage());
            }
        } else {
            $this->error404();
        }
    }

    /**
     * url错误返回404
     */
    private function error404()
    {
        $c = ucfirst($this->defaultControl).'Controller';
        $f = 'actionError404';
        if (class_exists($c)) {
            $cc = new $c;
            if (method_exists($cc, $f)) {
                $cc->$f();die;
            }
        }
        echo '<h2>Error 404</h2>';
        echo 'Unable to resolve the request "'.$this->pathInfo.'".';
    }

    /**
     * 获取控制器版本
     */
    public function getClassVersion()
    {
        return strtolower($this->version);
    }

    /**
     * 获取控制器名
     */
    public function getClassName()
    {
        return strtolower(str_replace('Controller', '', $this->className));
    }

    /**
     * 获取控制器方法名
     */
    public function getActionName()
    {
        return strtolower(str_replace('action', '', $this->actionName));
    }
}
?>
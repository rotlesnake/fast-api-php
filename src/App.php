<?php
namespace FastApiPHP;

use \Illuminate\Support\Str;

class App
{
    public $ROOT_PATH;
    public $APP_PATH;
    public $ROOT_URL;
    private static $instance = null;
    public $app_settings = null;
    public $request = null;
    public $response = null;
    public $duration = null;
    public $auth = null;
    public $allModels = null;


    public function __construct($ROOT_PATH=null, $APP_PATH=null, $ROOT_URL="/") {
        $this->ROOT_PATH = str_replace("/", DIRECTORY_SEPARATOR, realpath($ROOT_PATH)."/");
        $this->APP_PATH = str_replace("/", DIRECTORY_SEPARATOR, realpath($APP_PATH)."/");
        $this->ROOT_URL = $ROOT_URL;
        static::$instance = $this;
        $this->request = new Request($this);
        $this->response = new Response($this);
        $this->duration = new Duration($this);
        $this->duration->start("app_run");
    }

    public static function getInstance() {
        if (!static::$instance) { return null; }
        return static::$instance;
    }


    public function initDB($settings) {
        $this->app_settings = $settings;

        if ($this->app_settings["database"]["driver"]=='sqlite') { 
           if (!file_exists($this->app_settings["database"]["file"])) file_put_contents($this->app_settings["database"]["file"], '');
        }

        $this->DB = new \Illuminate\Database\Capsule\Manager();
        $this->DB->addConnection($this->app_settings["database"], "default");
        $this->DB->setEventDispatcher(new \Illuminate\Events\Dispatcher(new \Illuminate\Container\Container));
        $this->DB->setAsGlobal();
        $this->DB->bootEloquent();
        $this->connectDB();
    }

    public function connectDB() {
        try {
           $this->DB->connection()->getPdo();
        } catch (\Exception $e) {
           echo "Could not connect to the database. Please check your configuration. Error:";
           echo "<pre>";
           echo $e;
           echo "</pre>";
           die();
        }
    }

    public function addDatabse($settings, $name="external") {
        $this->DB->addConnection($settings, $name);
    }
    public function getDatabse($name="external") {
        $this->DB->connection($name);
    }
  

    public function initAuth() {
        $this->auth = new Auth($this);
        if (method_exists($this->auth, "autoLogin")) {
            $this->auth->autoLogin( $this->request );
        }
    }

    public function init($settings) {
        $this->initDB($settings);
        $this->initAuth();
    }



    public function run() {
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($uri, '?')) { $uri = substr($uri, 0, $pos); }
        $uri = rawurldecode($uri);
        $uri = substr($uri, strlen($this->ROOT_URL));
        $uri = trim($uri, '/');

        [$className, $action, $args] = $this->findController($uri);

        if (!$className) { 
            $this->response->sendError(null, 404, ["code"=>404, "message"=>"controller not found"]); 
        }

        $controllerClass = new $className($this, $this->request, $this->response, $args);

        if (!$this->auth->user && $controllerClass->requireAuth!==false) {
            $this->response->sendError(null, 401, ["code"=>401, "message"=>"user not found"]);
        }

        if (method_exists($controllerClass, $action)) {
            $this->response->body = $controllerClass->$action($this->request, $this->response, $args);
        } else {
            if (method_exists($controllerClass, "anyAction")) {
                $this->response->body = $controllerClass->anyAction($this->request, $this->response, substr($action,0,-6), $args);
            } else {
                $this->response->sendError(null, 404, ["code"=>404, "message"=>"action not found"]); 
            }
        }
 
        $this->duration->finish("app_run");
        $this->response->send();
    }//run



    public function findController($uri) {
        $routes = explode("/",$uri);
        $module     = "";
        $folder     = "";
        $controller = "IndexController";
        $action     = "indexAction";
        $args       = [];
        foreach ($routes as $k=>$v) {
            if (strlen($v)==0) continue;
            if ($k==0) $module     = Str::ucfirst( Str::camel($v) );
            if ($k==1) $folder     = Str::ucfirst( Str::camel($v) );
            if ($k==1) $controller = Str::ucfirst( Str::camel($v) )."Controller";
            if ($k==2) $action     = Str::camel($v)."Action";
            if ($k>=3) $args[]     = $v;
        }

        $className = "\\App\\".$module."\\Controllers\\".$controller;
        if (class_exists($className)) return [$className, $action, $args];
        $className = "\\App\\".$module."\\Controllers\\AnyController";
        if (class_exists($className)) return [$className, "anyAction", array_slice($routes,1)];
        if (count($routes) < 3) return [null, null, null];

        //subfolder
        $controller = Str::ucfirst( Str::camel($routes[2]) )."Controller";
        $action     = "indexAction";
        if (count($routes) > 3) $action = Str::camel($routes[3])."Action";
        if (count($args) > 0) array_shift($args);

        $className = "\\App\\".$module."\\Controllers\\".$folder."\\".$controller;
        if (class_exists($className)) return [$className, $action, $args];
        $className = "\\App\\".$module."\\Controllers\\".$folder."\\AnyController";
        if (class_exists($className)) return [$className, "anyAction", array_slice($routes,2)];

        return [null, null, null];
    }


    public function log($str, $object=null, $log_filename="app_log.txt") {
        if (defined('APP_DISABLE_LOG') && APP_DISABLE_LOG===true) return;
        $backtrace = debug_backtrace();
        $log_str = date("Y-m-d H:i:s") . " | line: ".$backtrace[0]["line"]." | from: ".$backtrace[0]["file"]."\r\n";
        $log_str .= $str."\r\n";
        if ($object) $log_str .= "---- data ----\r\n".print_r($object,true)."\r\n";
        $log_str .= "--------------------------------\r\n";
        file_put_contents($this->APP_PATH.$log_filename, $log_str, FILE_APPEND);
    }


    public function getModulesList($details=false) {
        $result=[];
        if ($dir = opendir($this->APP_PATH)) {
            while (($file = readdir($dir)) !== false) {
                if ($file != "." && $file != ".." && is_dir($this->APP_PATH."/".$file)) array_push($result, $file);
            }
            closedir($dir);
        }
        return $result;
    }

    public function getModuleModels($module, $details=false) {
        $result=[];
        if (!file_exists($this->APP_PATH.$module."/Models/")) return $result;

        $files = glob($this->APP_PATH.$module."/Models/*.php");
        $dirs = glob($this->APP_PATH.$module."/Models/*");
        foreach ($dirs as $dir) {
            if (is_dir($dir)) $files = array_merge($files, glob($dir."/*.php"));
        }

        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if ($ext != "php") continue;
            $model = str_replace([$this->APP_PATH.$module."/Models/",".php"],["",""],$file);
            $class = "\\App\\$module\\Models\\".str_replace("/","\\", $model);
            if (!class_exists($class)) continue;
            if (!method_exists($class,"modelInfo")) continue;
            if ($details) { $model = ["name"=>$model, "class"=>$class, "table"=>$class::modelInfo()["table"] ]; }
            $result[] = $model;
        }
        return $result;
    }

    public function getModuleSeeds($module, $details=false) {
        $result=[];
        if (!file_exists($this->APP_PATH.$module."/Seeds/")) return $result;

        $files = glob($this->APP_PATH.$module."/Seeds/*.php");
        foreach ($files as $file) {
            $model = str_replace([$this->APP_PATH.$module."/Seeds/",".php"],["",""],$file);
            $class = "\\App\\$module\\Seeds\\".str_replace("/","\\", $model);
            if (!class_exists($class)) continue;
            if (!method_exists($class,"run")) continue;
            if ($details) { $model = ["name"=>$model, "class"=>$class ]; }
            $result[] = $model;
        }
        return $result;
    }

    public function getAllModels() {
        if ($this->allModels) return $this->allModels;
        $this->allModels = [];
        $modules = $this->getModulesList(true);
        foreach($modules as $module) {
            $models = $this->getModuleModels($module, true);
            foreach($models as $model) {
                $this->allModels[] = $model;
            }
        }
        return $this->allModels;
    }


    public function findTable($tableName) {
        //return array_find($this->getAllModels(), function($value, $key) { return $value["table"] == $tableName; });
        foreach($this->getAllModels() as $model) {
            if ($model["table"] == $tableName) return $model;
        }
        return null;
    }


    public function migrate($onlyModule=null, $onlyModel=null) {
        $migrate = new Migrate($this);
        return $migrate->migrate($onlyModule, $onlyModel);
    }

    public function seeds($onlyModule=null, $onlyModel=null) {
        $seeds = new Seeds($this);
        return $seeds->run($onlyModule, $onlyModel);
    }

}//class
<?php
namespace FastApiPHP;


class Seeds {

    public $APP = null;
    public $aclList = [];
	
    public function __construct($APP=null)
    {
        $this->APP = $APP;
    }


    public function run($onlyModule=null, $onlyModel=null) {
        if ($onlyModule && !is_array($onlyModule)) $onlyModule = explode(",",$onlyModule);
        if ($onlyModel && !is_array($onlyModel)) $onlyModel = explode(",",$onlyModel);
        $result = [];

        $modules = $this->APP->getModulesList(true);
        foreach($modules as $module) {
            if ($onlyModule && !in_array($module,$onlyModule)) continue;

            $moduleSeeds = [];
            $models = $this->APP->getModuleSeeds($module, true);
            foreach($models as $model) {
                if ($onlyModel && !in_array($model["name"],$onlyModel)) continue;
                $moduleSeeds[] = $model;
            }
            $result[$module] = $this->doSeeds($moduleSeeds);
        }
        return $result;
    }


    //Выполнить засев данными
    public function doSeeds($models) {
        $result = [];
        $this->APP->DB->schema()->disableForeignKeyConstraints();
        foreach ($models as $model) {
            try {
                $class = $model["class"];
                $result[] = "run $class";
                $result[] = $class::run();
            } catch (\Exception $e) { $result[] = "Ошибка ".$model["class"]."";  $result[] = "<font color=red>".$e->getMessage()."</font>"; }
        }//foreach models
        $this->APP->DB->schema()->enableForeignKeyConstraints();
        return $result;
    }//doMigrateAll()


 

}//class

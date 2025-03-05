<?php
namespace FastApiPHP;


class Migrate {

    public $APP = null;
    public $aclList = []; //[{ module:'App', desc:'', acl:[{code:desc}] }]
	
    public function __construct($APP=null)
    {
        $this->APP = $APP;
    }


    public function migrate($onlyModule=null, $onlyModel=null) {
        if ($onlyModule && !is_array($onlyModule)) $onlyModule = explode(",",$onlyModule);
        if ($onlyModel && !is_array($onlyModel)) $onlyModel = explode(",",$onlyModel);
        if (class_exists("\\App\\Settings")) $this->aclList[] = ["module"=>"App", "desc"=>\App\Settings::$description, "acl"=>\App\Settings::$acl];

        $result = [];
        $modules = $this->APP->getModulesList(true);
        foreach($modules as $module) {
            if ($onlyModule && !in_array($module,$onlyModule)) continue;
            $moduleSettings = "\\App\\$module\\Settings";
            if (class_exists($moduleSettings)) $this->aclList[] = ["module"=>$module, "desc"=>$moduleSettings::$description, "acl"=>$moduleSettings::$acl];

            $models = $this->APP->getModuleModels($module, true);
            $moduleModels = [];
            foreach($models as $model) {
                if ($onlyModel && !in_array($model["name"], $onlyModel) && !in_array($model["table"], $onlyModel)) continue;
                $moduleModels[] = $model;
            }
            $result[$module] = $this->doMigrate($module, $moduleModels);
        }


        //Import acl list
        if (count($this->aclList) > 0) $this->importAcls($this->aclList);
        return $result;
    }

    public function importAcls($aclList) {
        if (!is_array($aclList)) return;
        foreach ($aclList as $module) {
            $parent_id = 0;
            $parent_module = $this->APP->DB::table("app_access_list")->where("module",$module["module"])->where("name",$module["module"])->first();
            if ($parent_module) {
                $parent_id = $parent_module->id;
            } else {
                $parent_id = $this->APP->DB::table("app_access_list")->insertGetId(["parent_id"=>"0", "module"=>$module["module"], "name"=>$module["module"], "description"=>$module["desc"]]);
            }

            foreach ($module["acl"] as $name=>$desc) {
                $oldRow = $this->APP->DB::table("app_access_list")->where("name",$name)->first();
                if ($oldRow) {
                    if ($desc != $oldRow->description) $this->APP->DB::table("app_access_list")->where("name",$name)->update(["description"=>$desc]);
                } else {
                    $this->APP->DB::table("app_access_list")->insertGetId(["parent_id"=>$parent_id, "module"=>$module["module"], "name"=>$name, "description"=>$desc]);
                }
            }
        }
    }

    //Выполнить миграцию
    public function doMigrate($module, $models) {
        $result = [];
        
        foreach ($models as $model) {
            $class = $model["class"];
            $tableInfo = $class::modelInfo();
            $tableName = $tableInfo["table"];
            if (isset($tableInfo["acl"]) && is_array($tableInfo["acl"])) $this->aclList[] = ["module"=>$module, "desc"=>"", "acl"=>$tableInfo["acl"]];

            $result[] = $this->doMigrateColumns($tableInfo);
            $result[] = $this->doMigrateIndexes($tableInfo);
        }//foreach models

        foreach ($models as $model) {
            $class = $model["class"];
            $tableInfo = $class::modelInfo();
            $result[] = $this->doMigrateRelationsTable($tableInfo);
        }//foreach models

        foreach ($models as $model) {
            $class = $model["class"];
            $tableInfo = $class::modelInfo();
            $result[] = $this->doMigrateForeign($tableInfo);
        }//foreach models

        return $result;
    }//doMigrateAll()

    public function hasIndex($tableName, $indexName, $indexType) {
        if (is_array($indexName)) $indexName = implode("_", $indexName);
        $indexName = $tableName."_".$indexName."_".$indexType;
        $indexesFound = $this->APP->DB->schema()->getIndexes($tableName);
        foreach($indexesFound as $ndx) {
            if ($ndx["name"]==$indexName) return true;
        }
        return false;
    }
    
    public function hasForeign($tableName, $keyName) {
        $foreignKeys = $this->APP->DB->schema()->getForeignKeys($tableName);
        $keyName = $tableName."_".$keyName."_foreign";
        foreach($foreignKeys as $ndx) {
            if ($ndx["name"]==$keyName) return true;
        }
        return false;
    }

    //*********************************************************************************************************************************
    //Выполнить миграцию колонок
    public function doMigrateColumns($tableInfo) {
        $result = [];
        
        try {
            $tableName = $tableInfo["table"];
            $result[] = "Миграция columns <b>".$tableName."</b> <br>\r\n";
            //если нет таблицы то создаем
            if (!$this->APP->DB->schema()->hasTable($tableName)) {
                $result[] = " - Создаем таблицу (<b>".$tableName."</b>) <br>\r\n";
                $this->APP->DB->schema()->create($tableName, function($table) use($tableName, $tableInfo){
                    $table->increments('id');
                    $table->integer('created_by_user')->unsigned()->nullable()->default(0);
                    $table->timestamps();
                    if ($this->APP->app_settings && isset($this->APP->app_settings["database"]["engine"])) $table->engine = $this->APP->app_settings["database"]["engine"];
                });
            }//---hasTable---

            //Создаем новые поля
            $this->APP->DB->schema()->table($tableName, function($table) use($tableName, $tableInfo, &$result) {
                foreach ($tableInfo["columns"] as $x=>$y) {
                    //колонка системная тогда ничего не делаем
                    if (in_array($x, ["created_at","updated_at","created_by_user"])) { continue; }

                    $columnExists = false;
                    if ($this->APP->DB->schema()->hasColumn($tableName, $x)) { 
                        $columnExists = true;
                        $fldType = strtolower( $this->APP->DB::getSchemaBuilder()->getColumnType($tableName, $x) );
                        if ($fldType == "varchar") $fldType = "string";
                        if ($fldType == "int") $fldType = "integer";
                        if ($fldType == strtolower($y["type"][0])) continue;
                    }

                    if ($x == "id") { 
                        if (strtolower($y["type"][0]) == "string") $table->string('id', $y["type"][1] ?? 255)->change();
                        if (strtolower($y["type"][0]) == "integer") $table->increments('id')->change();
                        continue;
                    }
                    //колонка уже есть тогда проверяем тип, если тип не поменялся тогда ничего не делаем
                    if ($x == "deleted_at" && !$columnExists) { 
                        $table->softDeletes('deleted_at');
                        continue;
                    }
                    if (strtolower($y["type"][0]) == "virtual") continue;

                    $fldArgs = $y["type"];
                    $fldType = array_shift($fldArgs);
                    array_unshift($fldArgs, $x);
                    if ($columnExists) {
                        $result[] = " - Меняем поле (".$x.")(".$fldType.") <br>\r\n";
                    } else {
                        $result[] = " - Добавляем поле (".$x.")(".$fldType.") <br>\r\n";
                    }

                    $fld = call_user_func_array(array($table, $fldType), $fldArgs);
                    $fld->nullable();

                    if (isset($y["default"])) $fld->default($y["default"]);
                    if (isset($y["unsigned"])) $fld->unsigned();

                    if (isset($y["index"])) { 
                        if ($y["index"]=="index" && !$this->hasIndex($tableName, $x, "index")) { $fld->index(); }
                        if ($y["index"]=="unique" && !$this->hasIndex($tableName, $x, "unique")) { 
                            $fld->unique(); 
                        }
                    }
                    if (isset($y["linkTable"]) && strlen($y["linkTable"]) > 0) {
                        if (!$this->hasIndex($tableName, $x, "index")) $fld->index();
                        $fld->unsigned();
                    }

                    if ($columnExists) { $fld->change(); }
                }//foreach columns
            });//schema()->table()

 
        } catch (\Exception $e) { $result[] = "Ошибка миграции -> ".$tableName."<br>\r\n";  $result[] = "<font color=red>".$e->getMessage()."</font>\r\n\r\n"; }

        return $result;
    }//doMigrateColumns()


    //Выполнить миграцию индексов
    public function doMigrateIndexes($tableInfo) {
        $tableName = $tableInfo["table"];
        $result = [];
        $result[] = "Миграция indexes <b>".$tableName."</b> <br>\r\n";
        if (!isset($tableInfo["indexes"])) return $result;
        
        try {
            $this->APP->DB->schema()->table($tableName, function($table) use($tableName, $tableInfo, &$result) {
                foreach ($tableInfo["indexes"] as $ndx) {
                    if ($ndx["type"] == "index" && !$this->hasIndex($tableName,$ndx["fields"],"index")) { 
                        $table->index($ndx["fields"]);
                    }
                    if ($ndx["type"] == "unique" && !$this->hasIndex($tableName,$ndx["fields"],"unique")) { 
                        $table->unique($ndx["fields"]);
                    }
                }//foreach indexes
            });//schema()->table()
        } catch (\Exception $e) { $result[] = "Ошибка миграции -> ".$tableName."<br>\r\n";  $result[] = "<font color=red>".$e->getMessage()."</font>\r\n\r\n"; }

        return $result;
    }//doMigrateIndexes()


    
    //Выполнить миграцию связей
    public function doMigrateRelationsTable($tableInfo) {
        $result = [];
        
        foreach ($tableInfo["columns"] as $x=>$y) {
            if ($y["type"][0] != "virtual" || !isset($y["multiple"]) || !isset($y["linkTable"])) {
                if ($y["type"][0] == "virtual" && !isset($y["linkTable"])) $result[] = $tableInfo["table"]." - empty linkTable in ".$x;
                continue;
            }
            if (!isset($y["multiple"]["viaTable"])) {
                $y["multiple"] = [];
                $y["multiple"]["viaTable"] = "relations_".$tableInfo["table"]."_".$y["linkTable"];
                $y["multiple"]["self"] = $tableInfo["table"];
                $y["multiple"]["link"] = $y["linkTable"];
            }

            $tableName = $y["multiple"]["viaTable"];
            $fldSelf = $y["multiple"]["self"];
            $fldLink = $y["multiple"]["link"];
            $selfTable = $tableInfo["table"];
            $linkTable = $y["linkTable"];
            $result[] = "Миграция relations <b>".$tableName."</b> <br>\r\n";

            //если нет таблицы то создаем
            if (!$this->APP->DB->schema()->hasTable($tableName)) {
                $result[] = " - Создаем таблицу (<b>".$tableName."</b>) <br>\r\n";
                $this->APP->DB->schema()->create($tableName, function($table) use($tableName, $tableInfo){
                    $table->increments('id');
                    $table->integer('created_by_user')->unsigned()->nullable()->default(0);
                    $table->timestamps();
                    if ($this->APP->app_settings && isset($this->APP->app_settings["database"]["engine"])) $table->engine = $this->APP->app_settings["database"]["engine"];
                });
            }//---hasTable---

            //меняем
            try {
                $this->APP->DB->schema()->table($tableName, function($table) use($tableName, $tableInfo, $fldSelf, $fldLink, $selfTable, $linkTable, &$result) {
                    if (!$this->APP->DB->schema()->hasColumn($tableName, $fldSelf)) { 
                        $fld = $table->integer($fldSelf)->unsigned()->index()->nullable();
                        $table->foreign($fldSelf)->references('id')->on($selfTable);
                    }
                    if (!$this->APP->DB->schema()->hasColumn($tableName, $fldLink)) { 
                        $fld = $table->integer($fldLink)->unsigned()->index()->nullable();
                        $table->foreign($fldLink)->references('id')->on($linkTable);
                    }
                });//schema()->table()
            } catch (\Exception $e) { $result[] = "Ошибка миграции -> ".$tableName."<br>\r\n";  $result[] = "<font color=red>".$e->getMessage()."</font>\r\n\r\n"; }
        }//columns

        return $result;
    }//doMigrateRelationsTable()



    //Выполнить миграцию связей
    public function doMigrateForeign($tableInfo) {
        $result = [];
        
        $tableName = $tableInfo["table"];
        $result[] = "Миграция foreign <b>".$tableName."</b> <br>\r\n";

        try {
            $this->APP->DB->schema()->table($tableName, function($table) use($tableName, $tableInfo, &$result) {
                if (isset($tableInfo["foreign"])) {
                    foreach($tableInfo["foreign"] as $ndx) {
                    }//foreach foreign
                }

                foreach ($tableInfo["columns"] as $x=>$y) {
                    if (in_array($y["type"][0], ["integer", "string"]) && isset($y["linkTable"]) && strlen($y["linkTable"]) > 0 && $x != "created_by_user" && !$this->hasForeign($tableName, $x)) {
                        $table->foreign($x)->references('id')->on($y["linkTable"]);
                    }
                }//foreach columns
            });//schema()->table()
        } catch (\Exception $e) { $result[] = "Ошибка миграции -> ".$tableName."<br>\r\n";  $result[] = "<font color=red>".$e->getMessage()."</font>\r\n\r\n"; }
 
        return $result;
    }//doMigrateForeign()


}//class

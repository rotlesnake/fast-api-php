<?php
namespace FastApiPHP;

use \Illuminate\Database\Eloquent\Model as EloquentModel;


class Model extends EloquentModel
{

    public $modelInfo = null;

    public static function boot()
    {
        parent::boot();
        
        
        static::creating(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (array_key_exists("created_by_user", $model->toArray())) $model->created_by_user = $APP->auth && $APP->auth->user ? $APP->auth->user->id : null;
            if (method_exists($model, "beforeAdd")) return $model->beforeAdd($model);
        });
        static::created(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (method_exists($model, "afterAdd")) return $model->afterAdd($model);
        });


        static::updating(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (array_key_exists("updated_by_user", $model->toArray())) $model->updated_by_user = $APP->auth && $APP->auth->user ? $APP->auth->user->id : null;
            if (method_exists($model, "beforeEdit")) return $model->beforeEdit($model);
        });
        static::updated(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (method_exists($model, "afterEdit")) return $model->afterEdit($model);
        });
        

        static::deleting(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (array_key_exists("deleted_by_user", $model->toArray())) {
              $model->deleted_by_user = $APP->auth && $APP->auth->user ? $APP->auth->user->id : null;
              $model->save();
            }
            if (method_exists($model, "beforeDelete")) return $model->beforeDelete($model);
        });
        static::deleted(function($model) {
            $APP = \FastApiPHP\App::getInstance();
            if (method_exists($model, "afterDelete")) return $model->afterDelete($model);
        });
    }//boot------------------------------------


    public static function getModelInfo() {
        $APP = \FastApiPHP\App::getInstance();
        $modelInfo = self::modelInfo();
        foreach ($modelInfo["columns"] as $x=>$y) {
            if (!isset($y["read"])) { $modelInfo["columns"][$x]["read"] = $modelInfo["read"];  $y["read"] = $modelInfo["read"]; }
            if (!isset($y["add"]))  { $modelInfo["columns"][$x]["add"]  = $modelInfo["add"];   $y["add"] = $modelInfo["add"]; }
            if (!isset($y["edit"])) { $modelInfo["columns"][$x]["edit"] = $modelInfo["edit"];  $y["edit"] = $modelInfo["edit"]; }
            if (!isset($y["delete"])) { $modelInfo["columns"][$x]["delete"] = $modelInfo["delete"];  $y["delete"] = $modelInfo["delete"]; }

            if (!$APP->auth->hasAcl($y["read"])) { unset($modelInfo["columns"][$x]); continue; }
            if (!$APP->auth->hasAcl($y["edit"])) { $modelInfo["columns"][$x]["protected"]=true; }
            $modelInfo["columns"][$x]["name"]=$x;
        }
        return $modelInfo;
    }


    public function scopeFindInSet($query, $field, $values)
    {
        return $query->whereRaw("FIND_IN_SET(?, ".$field.") > 0", [$values]);
    }
    public function scopeOrFindInSet($query, $field, $values)
    {
        return $query->orWhereRaw("FIND_IN_SET(?, ".$field.") > 0", [$values]);
    }

}//Class

<?php
namespace FastApiPHP;

use \App\Auth\Models\Users;
use \App\Auth\Models\UserTokens;
use \App\Auth\Models\Roles;

class Auth
{

    public $APP;
    public $user = null;
    public $user_token = null;
    public $user_roles = null;
    public $user_acl = null;
	
    public function __construct($APP=null)
    {
        $this->APP = $APP;
    }
	

    //Пытаемся авторизоваться по данным из кукисов или хедеров
    public function autoLogin($request) {
           if (!$this->user && $request->hasHeader("Authorization")) {
               $token = $request->getHeader("Authorization");
               $this->login(["token"=>str_replace("Bearer ","", $token)]);
           }
           if (!$this->user && $request->hasHeader("authorization")) {
               $token = $request->getHeader("authorization");
               $this->login(["token"=>str_replace("Bearer ","", $token)]);
           }
           if (!$this->user && $request->hasHeader("token")) {
               $token = $request->getHeader("token");
               $this->login(["token"=>$token]);
           }
           if (!$this->user && $request->hasCookie("token")) {
               $token = $request->getCookie("token");
               if (!(defined('APP_DISABLE_COOKIE') && APP_DISABLE_COOKIE===true)) {
                   $this->login(["token"=>$token]);
               }
           }
           //Если гость и есть логин пароль в basic auth то авторизуемся логином и паролем
           if (!$this->user && isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
               $this->login(["login"=>$_SERVER["PHP_AUTH_USER"], "password"=>$_SERVER["PHP_AUTH_PW"]]);
           }
    }

    //Авторизуемся в системе входные параметры [login, password, refresh_token, token]
    public function login($credentials) {
        $this->user = null;
        $this->user_token = null;
        $this->user_roles = null;
        $this->user_acl = null;

        //Удаляем просроченные токены
        $this->APP->DB::table("user_tokens")->where("expire", "<=", date("Y-m-d"))->delete();

        if (isset($credentials["login"]) && isset($credentials["password"])) {
            $tmpuser = Users::where("login", $credentials["login"])->where("status", "active")->first();
            if ($tmpuser && password_verify($credentials["password"], $tmpuser->password)) {
                $this->appendToken($tmpuser, isset($credentials["token_hours"]) ? $credentials["token_hours"] : 3);
                return true;
            }
        }
    
        if (isset($credentials["token"])) {
                $tmptoken = UserTokens::where("token", $credentials["token"])->first();
                if ($tmptoken && strlen($tmptoken->token)>10 && strtotime("now") < strtotime($tmptoken->expire) ) { 
                    $tmpuser = Users::find($tmptoken->user);
                    if ($tmpuser && $tmpuser->status == "active") {
                        $this->user_token = $tmptoken;
                        $this->setUser($tmptoken->user);
                        return true;
                    }
                }
        }

        return false;
    }


    public function appendToken($tmpuser, $hours_token=3, $hours_refresh_token=96) {
        $tmptoken = new UserTokens();
        $tmptoken->user = $tmpuser->id;
        $tmptoken->token = sha1($tmpuser->login . $tmpuser->password . time());
        $tmptoken->expire = date("Y-m-d H:i:s", strtotime("now +".$hours_token." hours"));
        $tmptoken->browser_ip    = \FastApiPHP\Utils::getRemoteIP();
        $tmptoken->browser_agent = isset($this->APP->request) ? $this->APP->request->getHeader("user-agent") : "";
        $tmptoken->save();

        $this->user_token = $tmptoken;
        $this->setUser($tmpuser->id);
        return true;
    }

    public function setUser($id) {
        $this->user = null;
        $this->user_acl = null;
        $this->user_roles = null;
		
        $tmpuser = Users::where("id", $id)->where("status", "active")->first();
        if ($tmpuser) {
            unset($tmpuser->password);
            $this->user = $tmpuser;
            $this->user_acl = $this->getUserAcl();
            $this->user_roles = $this->getUserRoles();
            if (!(defined('APP_DISABLE_COOKIE') && APP_DISABLE_COOKIE===true)) {
                if ($this->user_token) setcookie("token", $this->user_token->token, strtotime($this->user_token->expire), $this->APP->ROOT_URL, $_SERVER["SERVER_NAME"]);
            }
            return true;
        }
        return false;
    }

    //Забываем данные авторизации, становимся гостем
    public function logout() {
        $this->user = null;
        $this->user_acl = null;
        if ($this->user_token) $this->user_token->delete();
        $this->user_token = null;
        setcookie("token", "", time()-3600, "/", "");
    }

    //Регистрация пользователя
    public function register($login, $password, $status, $role_id, $fields=[]) {
        $tmpuser = Users::where("login", $login)->first();
        if ($tmpuser) return false;

        $user = new Users();
        //$user = $user->fillRow("add", $fields);
        $user->login = $login;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->role = $role_id;
        $user->status = $status;
        if (!$user->save()) return false;
        //$user->fillRow("add", $fields);
        return $user;
    }

    //изменить пароль
    public function changePassword($password) {
        if (!$this->user) return false;
        $this->user->password = password_hash($password, PASSWORD_DEFAULT);
        return $this->user->save();
    }


    public function getUserAcl() {
        if ($this->user && !$this->user_acl) {
            $this->user_acl = $this->APP->DB::table("relations_role_appacl")->
                                                join("roles", "relations_role_appacl.role","=","roles.id")->
                                                join("app_access_list", "relations_role_appacl.acl","=","app_access_list.id")->
                                                join("relations_user_role", "relations_user_role.role","=","roles.id")->
                                                join("users", "relations_user_role.user","=","users.id")->
                                                where("users.id", $this->user->id)->
                                                select("app_access_list.id","app_access_list.name","app_access_list.description")->
                                                get()->pluck("name")->toArray();
        }
        return $this->user_acl;
    }

    public function getUserRoles() {
        if ($this->user && !$this->user_roles) {
            $this->user_roles = $this->APP->DB::table("relations_user_role")->
                                                join("roles", "relations_user_role.role","=","roles.id")->
                                                join("users", "relations_user_role.user","=","users.id")->
                                                where("users.id", $this->user->id)->
                                                select("roles.id","roles.name","roles.description")->
                                                get()->pluck("id")->toArray();
        }
        return $this->user_roles;
    }


    public function hasAcl($checkList=[]) {
        if (gettype($checkList)!="array") $checkList = explode(",", $checkList);
        if (count($checkList)==0) return false;

        $acl = $this->getUserAcl();
        if (!$acl) return null;
        foreach ($checkList as $v) {
            if ($v=="*") return true;
            if (in_array($v, $acl)) return true;
        }
        return false;
    }

    public function hasRoles($checkList=[]) {
        if (gettype($checkList)!="array") $checkList = explode(",", $checkList);
        if (count($checkList)==0) return false;

        $roles = $this->getUserRoles();
        if (!$roles) return null;
        foreach ($checkList as $v) {
            if ((int)$v>0 && in_array((int)$v, $roles)) return true;
        }
        return false;
    }


}//class

<?php

namespace Cockpit\Controller;

class Accounts extends \Cockpit\Controller {

    public function index() {

        $current  = $this->user["_id"];
        $accounts = $this->data->cockpit->accounts->find($this->user["group"]=="admin" ? null:["_id"=>$current])->sort(["user" => 1])->toArray();

        foreach ($accounts as &$account) {
            $account["md5email"] = md5(@$account["email"]);
        }

        return $this->render('cockpit:views/accounts/index.php', compact('accounts', 'current'));
    }


    public function account($uid=null) {

        if(!$uid) {
            $uid = $this->user["_id"];
        }

        $account = $this->data->cockpit->accounts->findOne([
            "_id" => $uid
        ]);

        if(!$account) {
            return false;
        }

        unset($account["password"]);

        $languages = $this->getLanguages();
        $groups    = $this->getGroups();

        return $this->render('cockpit:views/accounts/account.php', compact('account', 'uid', 'languages', 'groups'));
    }

    public function create() {

        $uid     = null;
        $account = ["user"=>"", "email"=>"", "active"=>1, "group"=>"", "i18n"=>$this->app->helper("i18n")->locale];

        $languages = $this->getLanguages();
        $groups    = $this->getGroups();

        return $this->render('cockpit:views/accounts/account.php', compact('account', 'uid', 'languages', 'groups'));
    }

    public function save() {

        if($data = $this->param("account", false)) {


            if(isset($data["password"])) {
                if(strlen($data["password"])){
                    $data["password"] = $this->app->hash($data["password"]);
                } else {
                    unset($data["password"]);
                }
            }

            $this->data->cockpit->accounts->save($data);

            if($data["_id"] == $this->user["_id"]) {
                $this->app->helper("session")->write('app.auth', $data);
            }

            return json_encode($data);
        }

        return false;

    }

    public function remove() {

        if($data = $this->param("account", false)) {

            // user can't delete himself
            if($data["_id"] != $this->user["_id"]) {

                $this->data->cockpit->accounts->remove(["_id" => $data["_id"]]);

                return '{"success":true}';
            }
        }

        return false;
    }

    public function groups() {

        if($this->user["group"]!="admin") return false;


        $acl = $this->getAcl();

        return $this->render('cockpit:views/accounts/groups.php', compact('acl'));
    }


    public function addOrEditGroup() {

        if($this->user["group"]!="admin") return false;

        if($name = $this->app->param("name", false)) {
            
            if($name!="admin") {
                $groups = $this->app->memory->get("cockpit.acl.groups", []);
                
                if($oldname = $this->app->param("oldname", false)) {
                    if(isset($groups[$oldname])) unset($groups[$oldname]);
                }

                $groups[$name] = false;

                $this->app->memory->set("cockpit.acl.groups", $groups);
            }
        }

        $acl = $this->getAcl();

        return json_encode($acl);
    }

    public function deleteGroup() {

        if($this->user["group"]!="admin") return false;

        if($name = $this->app->param("name", false)) {
            
            if($name!="admin") {
                $groups = $this->app->memory->get("cockpit.acl.groups", []);
                
                if(isset($groups[$name])) unset($groups[$name]);

                $this->app->memory->set("cockpit.acl.groups", $groups);
            }
        }

        $acl = $this->getAcl();

        return json_encode($acl);
    }

    public function saveAcl() {

        if($this->user["group"]!="admin") return false;

        if($acl = $this->app->param("acl", false)) {
            $this->app->memory->set("cockpit.acl.rights", $acl);
        }

        return '{"success":true}';
    }

    protected function getLanguages() {

        $languages = [];

        foreach ($this->app->helper("fs")->ls('*.php', 'cockpit:i18n') as $file) {

            $lang = include($file->getRealPath());
            $i18n = $file->getBasename('.php');
            $language = isset($lang['@meta']['language']) ? $lang['@meta']['language'] : $i18n;

            $languages[] = ["i18n" => $i18n, "language"=> $language];

        }

        return $languages;
    }

    protected function getGroups() {

        $groups = ['admin'];

        foreach ($this->app->memory->get("cockpit.acl.groups", []) as $group => $isadmin) {
            $groups[] = $group;
        }

        return $groups;
    }

    protected function getAcl() {

        $acl = [];

        foreach ($this->app->helper("acl")->getGroups() as $group => $isadmin) {
            
            $acl[$group] = [];

            foreach ($this->app->helper("acl")->getResources() as $resource => $actions) {
                $acl[$group][$resource] = [];

                foreach ($actions as $action) {
                    $acl[$group][$resource][$action] = $this->app->helper("acl")->hasaccess($group, $resource, $action);
                }
            }
        }

        return $acl;
    }

}
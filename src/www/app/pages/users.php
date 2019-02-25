<?php

namespace App\Pages;

use \Zippy\Html\DataList\DataView;
use \App\Entity\User;
use \App\System;
use \App\Application as App;
use \Zippy\Html\Form\CheckBox;
use \Zippy\Html\Form\DropDownChoice;
use \Zippy\Html\Form\Form;
use \Zippy\Html\Form\Button;
use \Zippy\Html\Form\TextArea;
use \Zippy\Html\Form\TextInput;
use \Zippy\Html\Label;
use \Zippy\Html\Link\ClickLink;
use \Zippy\Html\Panel;
use \Zippy\Binding\PropertyBinding as Bind;

class Users extends \App\Pages\Base
{

    public $user = null;

    public function __construct() {
        parent::__construct();
        if (System::getUser()->userlogin !== 'admin') {
            System::setErrorMsg('Пользователями может  управлять только  admin');
            App::RedirectHome();
            return;
        }




        $this->add(new Panel("listpan"));
        $this->listpan->add(new ClickLink('addnew', $this, "onAdd"));
        $this->listpan->add(new DataView("userrow", new UserDataSource(), $this, 'OnAddUserRow'))->Reload();

        $this->add(new Panel("editpan"))->setVisible(false);
        $this->editpan->add(new Form('editform'));
        $this->editpan->editform->add(new TextInput('editlogin'));
        $this->editpan->editform->add(new TextInput('editpass'));
        $this->editpan->editform->add(new TextInput('editemail'));
        $this->editpan->editform->add(new DropDownChoice('editacl'))->onChange($this, 'onAcl');
        ;
        $this->editpan->editform->add(new CheckBox('editonlymy'));


        $this->editpan->editform->add(new CheckBox('editwplanned'));
        $this->editpan->editform->add(new CheckBox('editwdebitors'));


        $this->editpan->editform->onSubmit($this, 'saveOnClick');
        $this->editpan->editform->add(new Button('cancel'))->onClick($this, 'cancelOnClick');

        $this->editpan->editform->add(new Panel('metaaccess'))->setVisible(false);
        $this->editpan->editform->metaaccess->add(new DataView('metarow', new \ZCL\DB\EntityDataSource("\\App\\Entity\\MetaData", "", "meta_type"), $this, 'metarowOnRow'));
    }

    public function onAdd($sender) {
        $this->listpan->setVisible(false);
        $this->editpan->setVisible(true);
        // Очищаем  форму
        $this->editpan->editform->clean();

        $this->user = new User();
    }

    public function onEdit($sender) {
        $this->listpan->setVisible(false);
        $this->editpan->setVisible(true);


        $this->user = $sender->getOwner()->getDataItem();
        $this->editpan->editform->editemail->setText($this->user->email);
        $this->editpan->editform->editlogin->setText($this->user->userlogin);
        $this->editpan->editform->editacl->setValue($this->user->acltype);
        $this->editpan->editform->editonlymy->setChecked($this->user->onlymy);

        $this->editpan->editform->metaaccess->setVisible($this->user->acltype == 2);
        $this->editpan->editform->metaaccess->metarow->Reload();



        if (strpos($this->user->widgets, 'wplanned') !== false)
            $this->editpan->editform->editwplanned->setChecked(true);
        if (strpos($this->user->widgets, 'wdebitors') !== false)
            $this->editpan->editform->editwdebitors->setChecked(true);
    }

    public function saveOnClick($sender) {

        $this->user->email = $this->editpan->editform->editemail->getText();
        $this->user->userlogin = $this->editpan->editform->editlogin->getText();

        $user = User::getByLogin($this->user->userlogin);
        if ($user instanceof User) {
            if ($user->user_id != $this->user->user_id) {
                $this->setError('Неуникальный логин');
                return;
            }
        }
        if ($this->user->email != "") {
            $user = User::getByEmail($this->user->email);
            if ($user instanceof User) {
                if ($user->user_id != $this->user->user_id) {
                    $this->setError('Неуникальный email');
                    return;
                }
            }
        }
        $this->user->acltype = $this->editpan->editform->editacl->getValue();
        $this->user->onlymy = $this->editpan->editform->editonlymy->isChecked() ? 1 : 0;

        $pass = $this->editpan->editform->editpass->getText();
        if (strlen($pass) > 0) {
            $this->user->userpass = (\password_hash($pass, PASSWORD_DEFAULT));
            ;
        }
        if ($this->user->user_id == 0 && strlen($pass) == 0) {
            $this->setError("Введите пароль нового пользователя");
            return;
        }

        $varr = array();
        $earr = array();

        foreach ($this->editpan->editform->metaaccess->metarow->getDataRows() as $row) {
            $item = $row->getDataItem();
            if ($item->viewacc == true)
                $varr[] = $item->meta_id;
            if ($item->editacc == true)
                $earr[] = $item->meta_id;
        }
        $this->user->aclview = implode(',', $varr);
        $this->user->acledit = implode(',', $earr);

        $widgets = "";

          if ($this->editpan->editform->editwplanned->isChecked())
            $widgets = $widgets . ',wplanned';
        if ($this->editpan->editform->editwdebitors->isChecked())
            $widgets = $widgets . ',wdebitors';


        $this->user->widgets = trim($widgets, ',');
        $this->user->save();
        $this->listpan->userrow->Reload();
        $this->listpan->setVisible(true);
        $this->editpan->setVisible(false);
        $this->editpan->editform->editpass->setText('');
    }

    public function cancelOnClick($sender) {
        $this->listpan->setVisible(true);
        $this->editpan->setVisible(false);
    }

    public function onAcl($sender) {

        $this->editpan->editform->metaaccess->setVisible($sender->getValue() == 2);
        $this->editpan->editform->metaaccess->metarow->Reload();
    }

    //удаление  юзера
    public function OnRemove($sender) {
        $user = $sender->getOwner()->getDataItem();
        User::delete($user->user_id);
        $this->listpan->userrow->Reload();
    }

    public function OnAddUserRow($datarow) {
        $item = $datarow->getDataItem();
        $datarow->add(new \Zippy\Html\Label("userlogin", $item->userlogin));

        $datarow->add(new \Zippy\Html\Label("created", date('d.m.Y', $item->createdon)));
        $datarow->add(new \Zippy\Html\Label("email", $item->email));
        $datarow->add(new \Zippy\Html\Link\ClickLink("edit", $this, "OnEdit"))->setVisible($item->userlogin != 'admin');
        $datarow->add(new \Zippy\Html\Link\ClickLink("remove", $this, "OnRemove"))->setVisible($item->userlogin != 'admin');
        return $datarow;
    }

    public function metarowOnRow($row) {
        $item = $row->getDataItem();
        switch ($item->meta_type) {
            case 1:
                $title = "Документ";
                break;
            case 2:
                $title = "Отчет";
                break;
            case 3:
                $title = "Журнал";
                break;
            case 4:
                $title = "Справочник";
                break;
            case 5:
                $title = "Каталог";
                break;
        }
        $earr = @explode(',', $this->user->acledit);
        if (is_array($earr)) {
            $item->editacc = in_array($item->meta_id, $earr);
        }
        $varr = @explode(',', $this->user->aclview);
        if (is_array($varr)) {
            $item->viewacc = in_array($item->meta_id, $varr);
        }

        $row->add(new Label('description', $item->description));
        $row->add(new Label('meta_name', $title));

        $row->add(new CheckBox('viewacc', new Bind($item, 'viewacc')));
        $row->add(new CheckBox('editacc', new Bind($item, 'editacc')))->setVisible($item->meta_type == 1 || $item->meta_type == 4);
    }

}

class UserDataSource implements \Zippy\Interfaces\DataSource
{

    //private $model, $db;

    public function getItemCount() {
        return User::findCnt();
    }

    public function getItems($start, $count, $orderbyfield = null, $desc = true) {
        return User::find('', $orderbyfield, $count, $start);
    }

    public function getItem($id) {
        return User::load($id);
    }

}

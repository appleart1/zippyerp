<?php

//todofirst

namespace ZippyERP\ERP\Pages\Doc;

use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Label;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;
use ZippyERP\ERP\Entity\Doc\Document;
use ZippyERP\ERP\Entity\Item;
use ZippyERP\ERP\Entity\Stock;
use ZippyERP\ERP\Entity\Store;
use ZippyERP\ERP\Helper as H;
use Zippy\WebApplication as App;

/**
 * Страница  ввода  гарантийного талона
 */
class Warranty extends \ZippyERP\ERP\Pages\Base
{

    public $_tovarlist = array();
    private $_doc;
    private $_basedocid = 0;
    private $_rowid = 0;
    private $_opt = 0;

    public function __construct($docid = 0, $basedocid = 0) {
        parent::__construct();

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));
        $this->docform->add(new Date('document_date'))->setDate(time());
        $this->docform->add(new TextInput('customer'));


        $this->docform->add(new DropDownChoice('store', Store::findArray("storename", "store_type = " . Store::STORE_TYPE_RET . " or store_type = " . Store::STORE_TYPE_OPT, "store_type  ")))->onChange($this, 'OnChangeStore');
        $this->docform->store->selectFirst();

        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');

        $this->add(new Form('editdetail'))->setVisible(false);

        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new TextInput('editsn'));
        $this->editdetail->add(new TextInput('editwarranty'));

        $this->editdetail->add(new AutocompleteTextInput('edittovar'))->onText($this, 'OnAutoItem');
        $this->editdetail->edittovar->onChange($this, 'OnChangeItem', true);


        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');

        if ($docid > 0) {    //загружаем   содержимок  документа настраницу
            $this->_doc = Document::load($docid);
            if ($this->_doc == null)
                App::RedirectError('Докумен не найден');
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->customer->setText($this->_doc->headerdata['customer']);
            $this->docform->document_date->setDate($this->_doc->document_date);


            foreach ($this->_doc->detaildata as $item) {
                $item = new Item($item);
                $this->_tovarlist[$item->item_id] = $item;
            }
        } else {
            $this->_doc = Document::create('Warranty');
            $this->docform->document_number->setText($this->_doc->nextNumber());
            if ($basedocid > 0) {  //создание на  основании
                $basedoc = Document::load($basedocid);
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                    if ($basedoc->meta_name == 'RetailIssue') {
                        $this->docform->store->setValue($basedoc->headerdata['store']);
                    }
                    if ($basedoc->meta_name == 'RetailIssue' || $basedoc->meta_name == 'GoodsIssue') {
                        $this->docform->customer->setText($basedoc->headerdata['customername']);


                        foreach ($basedoc->detaildata as $item) {
                            $item = new Item($item);
                            $this->_tovarlist[$item->item_id] = $item;
                        }
                    }
                }
            }
        }

        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_tovarlist')), $this, 'detailOnRow'))->Reload();


        $this->_opt = Store::load($this->docform->store->getValue())->store_type == Store::STORE_TYPE_OPT;
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('tovar', $item->itemname));
        $row->add(new Label('sn', $item->sn));
        $row->add(new Label('warranty', $item->warranty));
        $row->add(new Label('quantity', $item->quantity / 1000));
        $row->add(new Label('price', H::fm($item->price)));
        $row->add(new Label('amount', H::fm(($item->quantity / 1000) * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
    }

    public function deleteOnClick($sender) {
        $tovar = $sender->owner->getDataItem();
        // unset($this->_tovarlist[$tovar->tovar_id]);

        $this->_tovarlist = array_diff_key($this->_tovarlist, array($tovar->item_id => $this->_tovarlist[$tovar->item_id]));
        $this->docform->detail->Reload();
    }

    public function editOnClick($sender) {
        $item = $sender->owner->getDataItem();
        $this->editdetail->edittovar->setKey($item->stock_id);
        $this->editdetail->edittovar->setText($item->itemname);


        $this->editdetail->editprice->setText(H::fm($item->price));



        $this->editdetail->editquantity->setText($item->quantity / 1000);
        $this->editdetail->editwarranty->setText($item->warranty);
        $this->editdetail->editsn->setText($item->sn);
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = $item->stock_id;
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = 0;
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');
    }

    public function saverowOnClick($sender) {
        $id = $this->editdetail->edittovar->getKey();
        if ($id == 0) {
            $this->setError("Не вибраний товар");
            return;
        }
        $stock = Stock::load($id);
        $stock->quantity = 1000 * $this->editdetail->editquantity->getText();
        $stock->price = $this->editdetail->editprice->getText() * 100;
        $stock->sn = $this->editdetail->editsn->getText();
        $stock->warranty = $this->editdetail->editwarranty->getText();


        $this->_tovarlist[$stock->stock_id] = $stock;
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();

        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editsn->setText("");
        $this->editdetail->editwarranty->setText("");
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editsn->setText("");
        $this->editdetail->editwarranty->setText("");
    }

    public function savedocOnClick($sender) {
        if ($this->checkForm() == false) {
            return;
        }


        $this->_doc->headerdata = array(
            'customer' => $this->docform->customer->getText()
        );
        $this->_doc->detaildata = array();
        foreach ($this->_tovarlist as $tovar) {
            $this->_doc->detaildata[] = $tovar->getData();
        }

        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $isEdited = $this->_doc->document_id > 0;

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $this->_doc->save();
            if ($sender->id == 'execdoc') {
                $this->_doc->updateStatus(Document::STATE_EXECUTED);
            } else {
                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);
            }

            // если   создан на  основании
            if ($this->_basedocid > 0) {
                $this->_doc->AddConnectedDoc($this->_basedocid);
                $this->_basedocid = 0;
            }

            $conn->CommitTrans();
            App::RedirectBack();
        } catch (\Exception $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError("Помилка запису документу. Деталізація в лог файлі  ");
    
            $logger->error($ee);
            return;
        }
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm() {

        if (count($this->_tovarlist) == 0) {
            $this->setError("Не введений ні один  товар");
        }
        return !$this->isError();
    }

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function OnChangeStore($sender) {
        //очистка  списка  товаров
        $this->_tovarlist = array();
        $this->docform->detail->Reload();
        $store_id = $this->docform->store->getValue();

        $this->_opt = Store::load($store_id)->store_type == Store::STORE_TYPE_OPT;
    }

    public function OnAutoItem($sender) {
        $r = array();
        $store_id = $this->docform->store->getValue();

        $text = $sender->getText();
        $list = Stock::findArrayEx("store_id={$store_id} and closed <> 1 and (itemname like " . Stock::qstr('%' . $text . '%') . " or item_code like " . Stock::qstr('%' . $text . '%') . "  )");
        foreach ($list as $k => $v) {
            $r[$k] = $v;
        }
        return $r;
    }

    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $stock = Stock::load($id);

        if ($this->_opt) {
            $item = Item::load($stock->item_id);
            $this->editdetail->editprice->setText(H::fm($item->getOptPrice($stock->price)));
        } else {
            $this->editdetail->editprice->setText(H::fm($stock->price));
        }


        $this->updateAjax(array('editprice', 'editquantity'));
    }

}

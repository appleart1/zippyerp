<?php

//todofirst

namespace ZippyERP\ERP\Pages\Doc;

use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;
use ZippyERP\ERP\Entity\Customer;
use ZippyERP\ERP\Entity\Employee;
use ZippyERP\ERP\Entity\Doc\Document;
use ZippyERP\ERP\Entity\Stock;
use ZippyERP\ERP\Entity\Item;
use ZippyERP\ERP\Helper as H;
use Zippy\WebApplication as App;

/**
 * Страница документа заказ  покупателя
 */
class CustomerOrder extends \ZippyERP\ERP\Pages\Base
{

    public $_itemlist = array();
    private $_doc;
    private $_discount;

    public function __construct($docid = 0) {
        parent::__construct();

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));
        $this->docform->add(new Date('document_date'))->setDate(time());
        $this->docform->add(new Date('timeline'))->setDate(time() + 3 * 24 * 3600);
        $this->docform->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');
        $this->docform->customer->onChange($this, 'OnChangeCustomer');

        $this->docform->add(new DropDownChoice('emp', Employee::findArray("shortname", "", 'shortname')));

        $this->docform->add(new DropDownChoice('orderstate', \ZippyERP\ERP\Entity\Doc\CustomerOrder::getStatesList()));
        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');


        $this->docform->add(new Label('total'));
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');
        $this->add(new Form('editdetail'))->setVisible(false);


        $this->editdetail->add(new AutocompleteTextInput('edititem'))->onText($this, 'OnAutoItem');
        $this->editdetail->edititem->onChange($this, 'OnChangeItem', true);


        $this->editdetail->add(new TextInput('editquantity'));
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new SubmitButton('saverow'))->onClick($this, 'saverowOnClick');
        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new Label('qtystore'));

        if ($docid > 0) {    //загружаем   содержимок  документа настраницу
            $this->_doc = Document::load($docid);
            $this->docform->document_number->setText($this->_doc->document_number);


            $this->docform->document_date->setDate($this->_doc->document_date);
            $this->docform->timeline->setDate($this->_doc->headerdata['timeline']);
            $this->docform->customer->setKey($this->_doc->headerdata['customer']);
            $this->docform->customer->setText($this->_doc->headerdata['customername']);
            $this->docform->emp->setValue($this->_doc->headerdata['emp']);


            $this->docform->orderstate->setValue($this->_doc->headerdata['orderstate']);

            foreach ($this->_doc->detaildata as $item) {
                $item = new Item($item);
                $this->_itemlist[$item->item_id] = $item;
            }
        } else {
            $this->_doc = Document::create('CustomerOrder');
        }

        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'))->Reload();
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('item', $item->itemname));
        $row->add(new Label('measure', $item->measure_name));
        $row->add(new Label('quantity', $item->quantity / 1000));
        $row->add(new Label('price', H::fm($item->price)));
        $row->add(new Label('amount', H::fm(($item->quantity / 1000) * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function deleteOnClick($sender) {
        $item = $sender->owner->getDataItem();
        // unset($this->_itemlist[$item->item_id]);

        $this->_itemlist = array_diff_key($this->_itemlist, array($item->item_id => $this->_itemlist[$item->item_id]));
        $this->docform->detail->Reload();
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
    }

    public function savedocOnClick($sender) {
        if ($this->checkForm() == false) {
            return;
        }

        $this->calcTotal();
        $old_state = $this->_doc->headerdata['orderstate'];
        $new_state = $this->docform->orderstate->getValue();

        $this->_doc->headerdata = array(
            'customer' => $this->docform->customer->getKey(),
            'customername' => $this->docform->customer->getText(),
            'emp' => $this->docform->emp->getValue(),
            'empname' => $this->docform->emp->getValueName(),
            'orderstate' => $new_state,
            'timeline' => $this->docform->timeline->getDate(),
        );
        $this->_doc->detaildata = array();
        foreach ($this->_itemlist as $item) {
            $this->_doc->detaildata[] = $item->getData();
        }

        $this->_doc->amount = 100 * $this->docform->total->getText();
        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $this->_doc->order_state = $this->docform->orderstate->getValue();
        $this->_doc->datatag = $this->docform->customer->getKey();

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $this->_doc->save();
            if ($new_state != $old_state) {
                $this->_doc->updateStatus($new_state);
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

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function saverowOnClick($sender) {
        $id = $this->editdetail->edititem->getKey();
        if ($id == 0) {
            $this->setError("Не вибраний товар");
            return;
        }
        $item = Item::load($id);
        $item->quantity = $this->editdetail->editquantity->getText() * 1000;
        $item->price = $this->editdetail->editprice->getText() * 100;


        $this->_itemlist[$item->item_id] = $item;
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();

        //очищаем  форму
        $this->editdetail->edititem->setKey(0);
        $this->editdetail->edititem->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->qtystore->setText("");
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
    }

    public function beforeRender() {
        parent::beforeRender();

        $this->calcTotal();
    }

    private function checkForm() {

        if (count($this->_itemlist) == 0) {
            $this->setError("Не введений ні один  товар");
        }
        if ($this->docform->customer->getKey() == 0) {
            $this->setError("Не вибраний  постачальник");
        }

        return !$this->isError();
    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal() {
        $total = 0;
        foreach ($this->_itemlist as $item) {
            $total = $total + $item->price * ($item->quantity / 1000);
        }
        $this->docform->total->setText(H::fm($total));
    }

    public function OnChangeCustomer($sender) {
        $this->_discount = 0;
        $customer_id = $this->docform->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);
            $this->_discount = $customer->discount;
        }
        $this->calcTotal();
    }

    public function OnAutoItem($sender) {

        $text = Item::qstr('%' . $sender->getText() . '%');
        return Item::findArrayEx("(itemname like {$text} or item_code like {$text}) and item_type <>" . Item::ITEM_TYPE_RETSUM);
    }

    public function OnChangeItem($sender) {

        $item = Item::load($sender->getKey());
        $stock = Stock::getFirst("item_id={$item->item_id} and store_id in(select store_id from erp_store where  store_type=1)", "  store_id desc");
        $price = $item->getOptPrice($stock->price > 0 ? $stock->price : 0);
        $price = $price - $price / 100 * $this->_discount;
        $this->editdetail->editprice->setText(H::fm($price));


        $this->editdetail->qtystore->setText("" . Item::getQuantity($item->item_id, $this->docform->timeline->getDate()) / 1000);
        $this->updateAjax(array('editprice', 'qtystore'));
    }

    public function OnAutoCustomer($sender) {
        $text = Customer::qstr('%' . $sender->getText() . '%');
        return Customer::findArray("customer_name", "Customer_name like " . $text);
    }

}

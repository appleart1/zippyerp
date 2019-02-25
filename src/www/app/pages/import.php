<?php

namespace App\Pages;

use Zippy\Html\DataList\DataView;
use App\Entity\User;
use App\Entity\Item;
use App\Entity\Store;
use App\Entity\Category;
use App\Helper as H;
use App\System;
use Zippy\WebApplication as App;
use \ZCL\DB\EntityDataSource;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Panel;
use \Zippy\Html\Form\DropDownChoice;
use \Zippy\Html\Form\Form;
use \Zippy\Html\Form\CheckBox;
use \Zippy\Html\Form\SubmitButton;
use \Zippy\Html\Form\TextInput;

class Import extends \App\Pages\Base
{

    public function __construct() {
        parent::__construct();
        if (System::getUser()->acltype == 2) {
            App::Redirect('\App\Pages\Error', 'У вас нет права  импорта');
        }

        $form = $this->add(new Form("iform"));


        $form->add(new DropDownChoice("encode", array(1 => 'UTF8', 2 => 'win1251'), 0));
        $form->add(new DropDownChoice("price", Item::getPriceTypeList()));
        $form->add(new DropDownChoice("store", Store::getList(), H::getDefStore()));
        $form->add(new TextInput("sep", ';'));
        $form->add(new \Zippy\Html\Form\File("filename"));
        $cols = array(0 => '-', 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10);
        $form->add(new DropDownChoice("colname", $cols));
        $form->add(new DropDownChoice("colcode", $cols));
        $form->add(new DropDownChoice("colgr", $cols));

        $form->add(new DropDownChoice("colprice", $cols));

        $form->add(new DropDownChoice("colmsr", $cols));
        $form->add(new CheckBox("preview"));
        $form->add(new SubmitButton("load"))->onClick($this, "onImport");



        $this->_tvars['preview'] = false;
    }

    public function onType($sender) {
        $t = $sender->getValue();


        $this->iform->store->setVisible($t == 1);
        $this->iform->colinprice->setVisible($t == 1);
    }

    public function onImport($sender) {

        $store = $this->iform->store->getValue();
        $pt = $this->iform->price->getValue();
        $encode = $this->iform->encode->getValue();
        $preview = $this->iform->preview->isChecked();
        $this->_tvars['preview'] = false;

        $colname = $this->iform->colname->getValue();
        $colcode = $this->iform->colcode->getValue();
        $colgr = $this->iform->colgr->getValue();

        $colprice = $this->iform->colprice->getValue();

        $colmsr = $this->iform->colmsr->getValue();
        $sep = $this->iform->sep->getText();

        if ($encode == 0) {
            $this->setError('Не выбрана  кодировка');
            return;
        }
        if ($colname == 0) {
            $this->setError('Не указан столбец  с  наименованием');
            return;
        }

        $file = $this->iform->filename->getFile();
        if (strlen($file['tmp_name']) == 0) {
            $this->setError('Не  выбран  файл');
            return;
        }

        $data = array();
        if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 0, $sep)) !== FALSE) {
                $data[] = $row;
            }
        }
        fclose($handle);

        if ($preview) {

            $this->_tvars['preview'] = true;
            $this->_tvars['list'] = array();
            foreach ($data as $row) {
                $itemname = $row[$colname - 1];
                if ($encode == 2)
                    $itemname = mb_convert_encoding($itemname, "utf-8", "windows-1251");

                $this->_tvars['list'][] = array(
                    'colname' => $itemname,
                    'colcode' => $row[$colcode - 1],
                    'colgr' => $row[$colgr - 1],
                    'colmsr' => $row[$colmsr - 1],
                    'colprice' => $row[$colprice - 1]
                );
            }
            return;
        }



        $newitems = array();
        foreach ($data as $row) {


            $catname = $row[$colgr - 1];
            if (strlen($catname) > 0) {
                $cat = Category::getFirst('cat_name=' . Category::qstr($catname));
                if ($cat == null) {
                    $cat = new Category();
                    $cat->cat_name = $catname;
                    $cat->save();
                }
            }
            $itemname = $row[$colname - 1];
            if (strlen($itemname) > 0) {
                if ($encode == 2)
                    $itemname = mb_convert_encoding($itemname, "utf-8", "windows-1251");

                $item = Item::getFirst('itemname=' . Item::qstr($itemname));
                if ($item == null) {
                    $price = str_replace(',', '.', $row[$colprice - 1]);


                    $item = new Item();
                    $item->itemname = $itemname;
                    if (strlen($row[$colcode - 1]) > 0)
                        $item->item_code = $row[$colcode - 1];
                    if (strlen($row[$colmsr - 1]) > 0)
                        $item->msr = $row[$colmsr - 1];
                    if ($price > 0)
                        $item->{$pt} = $price;


                    if ($cat->cat_id > 0)
                        $item->cat_id = $cat->cat_id;


                    $item->save();
                }
            }
        }

        $this->setSuccess('Импорт завершен');

        $this->iform->clean();
    }

    public function getPageInfo() {

        return "Страница предназначена для  импорта данных из  CSV файла
                <br>Для указания расположения данных необходимо  указать номера  соответствующих столбцов.
                Может  быть  выполнен  импорт номенклатьуры  в справочник или  если  указано количество  оприходование на  склад.
                в этом  случае  создается документ Ручная операция. 
                <br>Также  есть  возможность предварительного просмотра данных.";
    }

}

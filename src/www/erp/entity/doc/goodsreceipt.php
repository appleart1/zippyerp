<?php

namespace ZippyERP\ERP\Entity\Doc;

use \ZippyERP\System\System;
use \ZippyERP\ERP\Entity\Item;
use \ZippyERP\ERP\Entity\SubConto;
use \ZippyERP\ERP\Entity\Entry;
use \ZippyERP\ERP\Helper as H;

/**
 * Класс-сущность  документ приходная  накладая
 *
 */
class GoodsReceipt extends Document
{

    public function generateReport()
    {

        // $customer = \ZippyERP\ERP\Entity\Customer::load($this->headerdata["customer"]);

        $i = 1;

        $detail = array();
        foreach ($this->detaildata as $value) {
            $detail[] = array("no" => $i++,
                "itemname" => $value['itemname'],
                "measure" => $value['measure_name'],
                "quantity" => $value['quantity'] / 1000,
                "price" => H::fm($value['price']),
                "pricends" => H::fm($value['pricends']),
                "totalnds" => H::fm($this->headerdata["totalnds"]),
                "amount" => H::fm($value['amount'])
            );
        }

        $header = array('date' => date('d.m.Y', $this->document_date),
            "customer" => $this->headerdata["customername"],
            "document_number" => $this->document_number,
            "totalnds" => H::fm($this->headerdata["totalnds"]),
            "total" => H::fm($this->headerdata["total"])
        );



        $report = new \ZippyERP\ERP\Report('goodsreceipt.tpl');

        $html = $report->generate($header, $detail);

        return $html;
    }

    public function Execute()
    {
        $types = array();

        //аналитика
        foreach ($this->detaildata as $item) {
            $stock = \ZippyERP\ERP\Entity\Stock::getStock($this->headerdata['store'], $item['item_id'], $item['price'], true);

            $sc = new SubConto($this->document_id, $this->document_date, $item['type']);
            $sc->setStock($stock->stock_id);
            $sc->setQuantity($item['quantity']);
            $sc->setAmount($item['amount'] - $item['nds']);
            $sc->save();

            //группируем по синтетическим счетам
            if ($types[$item['type']] > 0) {
                $types[$item['type']] = $types[$item['type']] + $item['amount'] - $item['nds'];
            } else {
                $types[$item['type']] = $item['amount'] - $item['nds'];
            }
        }

        foreach ($types as $acc => $value) {
            Entry::AddEntry($acc, "63", $value, $this->document_id, $this->document_date);
            $sc = new SubConto($this->document_id, $this->document_date, 63);
            $sc->setCustomer($this->headerdata["customer"]);
            $sc->setAmount(0 - $value);
            $sc->save();
        }

        $total = $this->headerdata['total'];

        if ($this->headerdata['cash'] == true) {

            $cash = MoneyFund::getCash();
            Entry::AddEntry("63", "30", $total, $this->document_id, $this->document_date);
            $sc = new SubConto($this->document_id, $this->document_date, 63);
            $sc->setCustomer($this->headerdata["customer"]);
            $sc->setAmount( $total);
             $sc->save();
            $sc = new SubConto($this->document_id, $this->document_date, 30);
            $sc->setMoneyfund($cash->id);
            $sc->setAmount(0-$total);
            // $sc->save();
        }

        //налоговый кредит
        if ($this->headerdata['totalnds'] > 0) {
            Entry::AddEntry("644", "63", $this->headerdata['totalnds'], $this->document_id, 0, $customer_id);
            $sc = new SubConto($this->document_id, $this->document_date, 63);
            $sc->setCustomer($this->headerdata["customer"]);
            $sc->setAmount(0 - $this->headerdata['totalnds']);
            $sc->save();
            $sc = new SubConto($this->document_id, $this->document_date, 644);
            $sc->setExtCode(TAX_NDS);
            $sc->setAmount($this->headerdata['totalnds']);
            //$sc->save();
        }







        return true;
    }

    public function getRelationBased()
    {
        $list = array();
        $list['TaxInvoiceIncome'] = 'Входящая НН';
        $list['ReturnGoodsReceipt'] = 'Возврат поставщику';

        return $list;
    }

}
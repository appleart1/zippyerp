<?php

namespace App\Entity\Doc;

use App\Entity\AccountEntry;
use App\Entity\Entry;
use App\Helper as H;

/**
 * Класс-сущность  документ   оприходование  с  производства
 *
 */
class ProdReceipt extends Document
{

    public function generateReport() {


        $i = 1;

        $detail = array();
        foreach ($this->detaildata as $value) {
            $detail[] = array("no" => $i++,
                "itemname" => $value['itemname'],
                "itemcode" => $value['item_code'],
                "quantity" => H::fqty($value['quantity']),
                "price" => H::famt($value['price']),
                "msr" => $value['msr'],
                "amount" => H::famt($value['amount'])
            );
        }

        $header = array('date' => date('d.m.Y', $this->document_date),
            "_detail" => $detail,
            "document_number" => $this->document_number,
            "total" => H::famt($this->headerdata["total"])
        );


        $report = new \App\Report('prodreceipt.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        $types = array();
        $common = \App\System::getOptions("common");

        //аналитика
        foreach ($this->detaildata as $row) {
            $stock = \App\Entity\Stock::getStock($this->headerdata['store'], $row['item_id'], $row['price'], $this->headerdata['itemtype'], true);


            $sc = new Entry($this->document_id, $this->headerdata['itemtype'], $row['amount'], $row['quantity']);
            $sc->setStock($stock->stock_id);

            $sc->save();
        }


        AccountEntry::AddEntry($this->headerdata['itemtype'], 23, $this->headerdata['total'], $this->document_id);

        return true;
    }

}

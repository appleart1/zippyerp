<?php

namespace ZippyERP\ERP\Pages\Report;

use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\RedirectLink;
use Zippy\Html\Panel;
use ZippyERP\ERP\Entity\Item;
use ZippyERP\ERP\Entity\Store;
use ZippyERP\ERP\Helper as H;

/**
 * Движение товара
 */
class ItemActivity extends \ZippyERP\ERP\Pages\Base
{

    public function __construct() {
        parent::__construct();

        $this->add(new Form('filter'))->onSubmit($this, 'OnSubmit');
        $this->filter->add(new Date('from', time() - (7 * 24 * 3600)));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new DropDownChoice('store', Store::findArray("storename", "")));

        $this->filter->store->selectFirst();
        $this->filter->add(new AutocompleteTextInput('item'))->onText($this, 'OnAutoItem');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new RedirectLink('print', "movereport"));
        $this->detail->add(new RedirectLink('html', "movereport"));
        $this->detail->add(new RedirectLink('word', "movereport"));
        $this->detail->add(new RedirectLink('excel', "movereport"));
        $this->detail->add(new Label('preview'));
    }

    public function OnAutoItem($sender) {
        $r = array();


        $text = Item::qstr('%' . $sender->getText() . '%');
        $list = Item::findArray('itemname', " (itemname like {$text} or item_code like {$text} ) and item_type in (" . Item::ITEM_TYPE_RETSUM . "," . Item::ITEM_TYPE_STUFF . ")");
        foreach ($list as $k => $v) {
            $r[$k] = $v;
        }
        return $r;
    }

    public function OnSubmit($sender) {
        $itemid = $this->filter->item->getKey();
        // $item = Item::load($itemid);
        if ($item == null) {
            //  $this->setError('Не вибраний ТМЦ');
            // return;
        }
        $html = $this->generateReport();
        $this->detail->preview->setText($html, true);
        \ZippyERP\System\Session::getSession()->printform = "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $html . "</body></html>";

        // \ZippyERP\System\Session::getSession()->storereport = "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $html . "</body></html>";
        $reportpage = "ZippyERP/ERP/Pages/ShowReport";
        $reportname = "movereport";

         $this->detail->preview->setText($html, true);

        $this->detail->print->pagename = $reportpage;
        $this->detail->print->params = array('print', $reportname);
        $this->detail->html->pagename = $reportpage;
        $this->detail->html->params = array('html', $reportname);
        $this->detail->word->pagename = $reportpage;
        $this->detail->word->params = array('doc', $reportname);
        $this->detail->excel->pagename = $reportpage;
        $this->detail->excel->params = array('xls', $reportname);

        $this->detail->setVisible(true);
    }

    private function generateReport() {

        $storeid = $this->filter->store->getValue();
        $itemid = $this->filter->item->getKey();

        $it = $itemid > 0 ? "st.item_id=" . $itemid : "1=1";
        $from = $this->filter->from->getDate();
        $to = $this->filter->to->getDate();

        $header = array('datefrom' => date('d.m.Y', $from),
            'dateto' => date('d.m.Y', $to),
            "store" => Store::load($storeid)->storename
        );


        $i = 1;
        $detail = array();
        $conn = \ZDB\DB::getConnect();

        $sql = "
            SELECT
              t.*,
              (SELECT
                  COALESCE(SUM(u.`quantity`), 0)
                FROM erp_account_subconto u
                WHERE u.`document_date` < t.dt
                AND u.`item_id` = t.`item_id`) AS begin_quantity
            FROM (
            SELECT
                st.item_id, st.itemname,st.item_code,
                price,
                DATE(sc.document_date) AS dt,
                SUM(
                CASE WHEN quantity > 0 THEN quantity ELSE 0 END) AS obin,
                SUM(
                CASE WHEN quantity < 0 THEN 0 - quantity ELSE 0 END) AS obout,
                GROUP_CONCAT(dc.document_number) AS docs
              FROM
               erp_account_subconto  sc join erp_stock_view  st on  sc.stock_id = st.stock_id
               join erp_document  dc  on sc.document_id = dc.document_id

              WHERE {$it}  
              AND st.store_id = {$storeid}
              AND DATE(sc.document_date) >= " . $conn->DBDate($from) . "
              AND DATE(sc.document_date) <= " . $conn->DBDate($to) . "
              GROUP BY st.item_id,
                       st.price,
                       DATE(sc.document_date)) t
            ORDER BY dt  
        ";

        $rs = $conn->Execute($sql);

        foreach ($rs as $row) {
            $detail[] = array(
                "code" => $row['item_code'],
                "name" => $row['itemname'],
                "date" => date("d.m.Y", strtotime($row['dt'])),
                "documents" => str_replace(',', '<br>', $row['docs']),
                "price" => H::fm($row['price']),
                "in" => $row['begin_quantity'] / 1000,
                "obin" => $row['obin'] / 1000,
                "obout" => $row['obout'] / 1000,
                "out" => ($row['begin_quantity'] + $row['obin'] - $row['obout']) / 1000
            );
        }


        $report = new \ZippyERP\ERP\Report('itemactivity.tpl');

        $html = $report->generate($header, $detail);

        return $html;
    }

}

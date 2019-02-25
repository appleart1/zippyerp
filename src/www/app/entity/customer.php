<?php

namespace App\Entity;

/**
 * Класс-сущность  контрагент
 *
 * @table=customers
 * @view=customers_view
 * @keyfield=customer_id
 */
class Customer extends \ZCL\DB\Entity
{

    protected function init() {
        $this->customer_id = 0;
    }

    protected function beforeSave() {
        parent::beforeSave();
        //упаковываем  данные в detail
        $this->detail = "<detail><code>{$this->code}</code>";
        $this->detail .= "<discount>{$this->discount}</discount>";
        $this->detail .= "<type>{$this->type}</type>";
        $this->detail .= "<jurid>{$this->jurid}</jurid>";
        $this->detail .= "<nds>{$this->nds}</nds>";
        $this->detail .= "<inn>{$this->inn}</inn>";
        $this->detail .= "<bank>{$this->bank}</bank>";
        $this->detail .= "<address><![CDATA[{$this->address}]]></address>";
        $this->detail .= "<comment><![CDATA[{$this->comment}]]></comment>";
        $this->detail .= "<edrpou><![CDATA[{$this->edrpou}]]></edrpou>";
        $this->detail .= "<bankaccount><![CDATA[{$this->bankaccount}]]></bankaccount>";
        $this->detail .= "</detail>";

        return true;
    }

    protected function afterLoad() {
        //распаковываем  данные из detail
        $xml = simplexml_load_string($this->detail);

        $this->discount = doubleval($xml->discount[0]);
        $this->type = (int) ($xml->type[0]);
        $this->jurid = (int) ($xml->jurid[0]);
        $this->nds = (int) ($xml->nds[0]);
        $this->bank = (int) ($xml->bank[0]);
        $this->inn = (string) ($xml->inn[0]);
        $this->address = (string) ($xml->address[0]);
        $this->comment = (string) ($xml->comment[0]);
        $this->edrpou = (string) ($xml->edrpou[0]);
        $this->bankaccount = (string) ($xml->bankaccount[0]);

        parent::afterLoad();
    }

    public function beforeDelete() {

        $conn = \ZDB\DB::getConnect();
        $sql = "  select count(*)  from  entrylist where   customer_id = {$this->customer_id}";
        $cnt = $conn->GetOne($sql);
        if ($cnt > 0)
            return false;
        $sql = "  select count(*)  from  documents where   customer_id = {$this->customer_id}  ";
        $cnt = $conn->GetOne($sql);
        if ($cnt > 0)
            return false;
        return true;
        ;
    }

    /**
     * список   для комбо
     * 
     */
    public static function getList() {
        return Customer::findArray("customer_name", "");
    }

}

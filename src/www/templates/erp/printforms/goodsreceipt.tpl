
    
        <table class="ctable" border="0" cellspacing="0" cellpadding="2">
            <tr>
                <th width="30">&nbsp;</th>
                <th width="100">&nbsp;</th>
                <th width="130">&nbsp;</th>
                <th width="50">&nbsp;</th>
                <th width="50">&nbsp;</th>
                <th width="60">&nbsp;</th>
                <th width="80">&nbsp;</th>
            </tr>

            <tr>
                <td></td>
                <td>Постачальник</td>
                <td colspan="5">{{customer_name}}</td>
            </tr>

            <tr>
                <td style="font-weight: bolder;font-size: larger;" align="center" colspan="7" valign="middle">
                    <br><br> Накладна № {{document_number}} від {{date}} <br><br><br>
                </td>
            </tr>

            <tr style="font-weight: bolder;">
                <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" width="30">№</th>
                <th colspan="2" width="230" style="border-top:1px #000 solid;border-bottom:1px #000 solid;">Найменування</th>
                <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" width="50">Од.виміру</th>
                <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" width="50">Кіл.</th>
                <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" width="60">Ціна</th>
                <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;" width="80">Сума</th>
            </tr>
            {{#_detail}}
            <tr>
                <td align="right">{{no}}</td>
                <td colspan="2">{{itemname}}</td>
                <td>{{measure}}</td>
                <td align="right">{{quantity}}</td>
                <td align="right">{{price}}</td>
                <td align="right">{{amount}}</td>
            </tr>
            {{/_detail}}
            <tr style="font-weight: bolder;">
                <td style="border-top:1px #000 solid;" colspan="6" align="right">Всього:</td>
                <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
            </tr>
            {{#totalnds}}
            <tr style="font-weight: bolder;">
                <td colspan="6" align="right">В т.ч. ПДВ:</td>
                <td align="right">{{totalnds}}</td>
            </tr>
            {{/totalnds}}

        </table>
        <br> <br>
    


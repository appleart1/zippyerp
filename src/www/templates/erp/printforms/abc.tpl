 

        <table class="ctable" cellspacing="0" cellpadding="2">
            <tr colspan="4" style="font-weight: bolder;">
            <h3 style="font-size: 16px;">АВС аналіз '{{type}}' з {{from}} по {{to}}</h3>

        </tr>
        <tr style="font-weight: bolder;">

            <th width="300px" style="border-bottom:1px #000 solid;">Назва</th>
            <th width="100px" style="border-bottom:1px #000 solid;">Знач., тис.</th>
            <th width="50px" style="border-bottom:1px #000 solid;">%</th>
            <th width="20px" style="border-bottom:1px #000 solid;"></th>
        </tr>
        {{#_detail}}
        <tr>

            <td width="300px" style="background-color: {{color}} ;">{{name}}</td>
            <td width="100px" style="background-color: {{color}} ;" align="right">{{value}} &nbsp;</td>
            <td width="50px" style="background-color: {{color}} ;" align="right">{{perc}} &nbsp;</td>
            <td width="20px" style="background-color: {{color}} ;">{{group}}</td>

        </tr>
        {{/_detail}}


    </table>

    <br>
 
